<?php
declare(strict_types=1);

/**
 * Vérification des organisateurs (KYC) : pièces justificatives déposées par l'organisateur,
 * contrôlées par le super-admin. Option « kyc_required » : pas de reversement tant que ce n'est pas vérifié.
 * organizers.kyc_status : none (rien), incomplete, pending (tout est déposé), verified, rejected.
 */
final class Kyc
{
    public const KINDS = [
        'id' => ['🪪', 'Pièce d\'identité du représentant légal', 'Carte d\'identité ou passeport en cours de validité (recto et verso).'],
        'legal' => ['🏛️', 'Justificatif de la structure', 'Extrait Kbis de moins de 3 mois, ou statuts + récépissé de déclaration pour une association.'],
        'rib' => ['🏦', 'RIB au nom de la structure', 'Le même IBAN que celui renseigné dans Mon organisation.'],
    ];
    public const STATUS = ['none' => ['À compléter', 'grey'], 'incomplete' => ['Incomplet', 'orange'], 'pending' => ['En cours de vérification', 'orange'], 'verified' => ['Vérifié', 'green'], 'rejected' => ['À corriger', 'red']];
    public const DOC_STATUS = ['pending' => ['En attente', 'orange'], 'approved' => ['Validé', 'green'], 'rejected' => ['Refusé', 'red']];

    public static function badge(string $s, bool $doc = false): string
    {
        [$l, $c] = ($doc ? self::DOC_STATUS : self::STATUS)[$s] ?? [$s, 'grey'];
        return '<span class="badge ' . $c . '">' . e($l) . '</span>';
    }

    public static function required(): bool { return setting('kyc_required') === '1'; }

    /** Dernier document de chaque type. */
    public static function latest(int $orgId): array
    {
        $out = [];
        foreach (all('SELECT * FROM org_documents WHERE organizer_id = ? ORDER BY id DESC', [$orgId]) as $d) if (!isset($out[$d['kind']])) $out[$d['kind']] = $d;
        return $out;
    }

    /** Recalcule le statut global à partir des derniers documents. */
    public static function refresh(int $orgId): string
    {
        $docs = self::latest($orgId);
        $st = array_map(fn($k) => $docs[$k]['status'] ?? null, array_keys(self::KINDS));
        if (in_array('rejected', $st, true)) $s = 'rejected';
        elseif (count(array_filter($st, fn($x) => $x === 'approved')) === count(self::KINDS)) $s = 'verified';
        elseif (!in_array(null, $st, true)) $s = 'pending';
        elseif (array_filter($st)) $s = 'incomplete';
        else $s = 'none';
        $org = one('SELECT kyc_status FROM organizers WHERE id = ?', [$orgId]);
        $up = ['kyc_status' => $s];
        if ($s === 'verified' && ($org['kyc_status'] ?? '') !== 'verified') $up['kyc_verified_at'] = now();
        update('organizers', $up, 'id = ?', [$orgId]);
        return $s;
    }
}
