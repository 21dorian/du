<?php
declare(strict_types=1);

/**
 * 2.2 : conditions commerciales des organisateurs.
 *
 * Commission appliquée, champ par champ, dans cet ordre :
 *   1. contrat actif (dates de début/fin), avec palier facultatif au-delà d'un volume de ventes ;
 *   2. réglage manuel de l'organisateur (ancien système, conservé) ;
 *   3. formule de l'organisateur (ou formule par défaut) ;
 *   4. réglage global de la plateforme.
 * Retenues (org_charges) : abonnement mensuel de la formule, SMS, litiges perdus, ajustements manuels.
 * Elles sont déduites du solde à reverser.
 */
final class Billing
{
    public const MODULES = [
        'campagnes' => 'Campagnes e-mail', 'sms' => 'Campagnes SMS', 'liens' => 'Liens & ambassadeurs', 'boutique' => 'Boutique & merch',
        'cartes-cadeaux' => 'Cartes cadeaux', 'abonnements' => 'Abonnements & pass', 'placement' => 'Plan de salle numéroté', 'guichet' => 'Guichet & caisse',
        'benevoles' => 'Bénévoles & staff', 'artistes' => 'Artistes & planning', 'devis' => 'Groupes & devis', 'pc' => 'PC sécurité', 'api' => 'API & webhooks',
    ];
    public const CHARGE_KINDS = ['formule' => 'Abonnement', 'sms' => 'SMS', 'litige' => 'Litige', 'ajustement' => 'Ajustement', 'avoir' => 'Avoir'];

    private static array $cache = [];

    public static function plans(bool $activeOnly = false): array
    {
        return all('SELECT p.*, (SELECT COUNT(*) FROM organizers o WHERE o.plan_id = p.id) orgs FROM plans p' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort, monthly_price, id');
    }

    public static function defaultPlan(): ?array { return one('SELECT * FROM plans WHERE is_default = 1 AND active = 1 ORDER BY id LIMIT 1'); }

    public static function plan(array $org): ?array
    {
        $id = (int)($org['plan_id'] ?? 0);
        return ($id ? one('SELECT * FROM plans WHERE id = ?', [$id]) : null) ?: self::defaultPlan();
    }

    public static function contract(array $org, ?string $day = null): ?array
    {
        $day ??= date('Y-m-d');
        return one("SELECT * FROM contracts WHERE organizer_id = ? AND status = 'active' AND starts_on <= ? AND (ends_on IS NULL OR ends_on >= ?) ORDER BY starts_on DESC, id DESC LIMIT 1", [(int)$org['id'], $day, $day]);
    }

    /** Volume de ventes en ligne (hors frais) depuis le début du contrat. */
    public static function contractVolume(array $c): int
    {
        return (int)val("SELECT COALESCE(SUM(subtotal - discount), 0) FROM orders WHERE organizer_id = ? AND status = 'paid' AND method = 'online' AND paid_at >= ?" . ($c['ends_on'] ? ' AND paid_at < ? + INTERVAL 1 DAY' : ''),
            $c['ends_on'] ? [$c['organizer_id'], $c['starts_on'], $c['ends_on']] : [$c['organizer_id'], $c['starts_on']]);
    }

    /** @return array{pct:float, fixed:int, cap:?int, source:string, plan:?array, contract:?array} */
    public static function terms(array $org): array
    {
        $k = (int)($org['id'] ?? 0);
        if ($k && isset(self::$cache[$k])) return self::$cache[$k];
        $plan = self::plan($org);
        $c = $k ? self::contract($org) : null;
        $pick = function (string $f) use ($org, $plan, $c) {
            if ($c && $c[$f] !== null) return [$c[$f], 'contrat'];
            if (in_array($f, ['fee_percent', 'fee_fixed'], true) && ($org[$f] ?? null) !== null && $org[$f] !== '') return [$org[$f], 'organisateur'];
            if ($plan && $plan[$f] !== null) return [$plan[$f], 'formule'];
            return [$f === 'fee_cap' ? null : setting($f), 'plateforme'];
        };
        [$pct, $src] = $pick('fee_percent');
        [$fixed] = $pick('fee_fixed');
        [$cap] = $pick('fee_cap');
        if ($c && $c['threshold'] !== null && $c['fee_percent_after'] !== null && self::contractVolume($c) >= (int)$c['threshold']) { $pct = $c['fee_percent_after']; $src = 'contrat (palier)'; }
        $t = ['pct' => (float)$pct, 'fixed' => (int)$fixed, 'cap' => $cap !== null && $cap !== '' ? (int)$cap : null, 'source' => $src, 'plan' => $plan, 'contract' => $c];
        if ($k) self::$cache[$k] = $t;
        return $t;
    }

    public static function forget(): void { self::$cache = []; }

    /** Le module est-il inclus dans la formule de l'organisateur ? (formule sans liste = tout inclus) */
    public static function allows(array $org, string $module): bool
    {
        if (in_array($module, explode(',', (string)setting('disabled_modules', '')), true)) return false;
        $p = self::plan($org);
        if (!$p || $p['modules'] === null || $p['modules'] === '') return true;
        return in_array($module, array_map('trim', explode(',', (string)$p['modules'])), true);
    }

    /** Plans qui incluent un module (pour l'écran « module non inclus »). */
    public static function plansWith(string $module): array
    {
        return array_values(array_filter(self::plans(true), fn($p) => $p['public'] && ($p['modules'] === null || $p['modules'] === '' || in_array($module, explode(',', (string)$p['modules']), true))));
    }

    // ---------- Retenues ----------
    public static function charge(int $orgId, string $kind, string $label, int $amount, ?string $ref = null, ?int $by = null): ?int
    {
        if ($amount === 0) return null;
        if ($ref !== null && val('SELECT id FROM org_charges WHERE kind = ? AND ref = ?', [$kind, $ref])) return null;
        return insert('org_charges', ['organizer_id' => $orgId, 'kind' => $kind, 'label' => mb_substr($label, 0, 255), 'amount' => $amount, 'ref' => $ref, 'created_by' => $by, 'created_at' => now()]);
    }

    public static function chargesTotal(int $orgId): int { return (int)val('SELECT COALESCE(SUM(amount), 0) FROM org_charges WHERE organizer_id = ?', [$orgId]); }

    /** Tâche de fond : abonnement mensuel de la formule, une fois par mois et par organisateur actif. */
    public static function monthly(): int
    {
        $m = date('Y-m'); $n = 0;
        foreach (all("SELECT * FROM organizers WHERE status = 'active' AND (charges_billed_month IS NULL OR charges_billed_month <> ?)", [$m]) as $o) {
            $p = self::plan($o);
            if ($p && (int)$p['monthly_price'] > 0 && self::charge((int)$o['id'], 'formule', 'Formule ' . $p['name'] . ' · ' . month_fr($m), (int)$p['monthly_price'], 'f' . $o['id'] . '-' . $m)) $n++;
            update('organizers', ['charges_billed_month' => $m], 'id = ?', [$o['id']]);
        }
        return $n;
    }

    /** Revenu mensuel récurrent des formules. */
    public static function mrr(): int
    {
        $def = self::defaultPlan();
        return (int)val("SELECT COALESCE(SUM(p.monthly_price), 0) FROM organizers o JOIN plans p ON p.id = COALESCE(o.plan_id, ?) WHERE o.status = 'active'", [$def['id'] ?? 0]);
    }
}

