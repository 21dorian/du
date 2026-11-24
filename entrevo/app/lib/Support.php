<?php
declare(strict_types=1);

/**
 * 2.2 : support. Une demande (ticket) vient du formulaire public /contact, de l'espace « Aide » d'un organisateur
 * ou est créée par l'équipe. Les échanges se font par e-mail : chaque message part avec un lien vers la page
 * de suivi (/support/{jeton}) où la personne lit le fil et répond. Les notes internes ne sont jamais montrées.
 */
final class Support
{
    public const STATUS = ['open' => ['À traiter', 'red'], 'pending' => ['En attente de réponse', 'orange'], 'solved' => ['Résolue', 'green']];
    public const CATEGORIES = ['billets' => 'Mes billets', 'paiement' => 'Paiement / remboursement', 'organisateur' => 'Espace organisateur', 'reversement' => 'Reversements & factures',
        'technique' => 'Problème technique', 'formule' => 'Formule & modules', 'rgpd' => 'Données personnelles', 'autre' => 'Autre'];
    public const PRIORITY = ['low' => 'Basse', 'normal' => 'Normale', 'high' => 'Haute', 'urgent' => 'Urgente'];

    public static function badge(string $s): string { [$l, $c] = self::STATUS[$s] ?? [$s, 'grey']; return '<span class="badge ' . $c . '">' . e($l) . '</span>'; }

    public static function create(array $d, string $body, string $author = 'customer'): array
    {
        do { $ref = 'S' . strtoupper(random_code(6)); } while (val('SELECT id FROM support_tickets WHERE ref = ?', [$ref]));
        $id = insert('support_tickets', [
            'ref' => $ref, 'token' => token(24), 'organizer_id' => $d['organizer_id'] ?? null, 'user_id' => $d['user_id'] ?? null,
            'name' => mb_substr(trim((string)($d['name'] ?? '')), 0, 190) ?: null, 'email' => strtolower(trim((string)$d['email'])),
            'subject' => mb_substr(trim((string)$d['subject']), 0, 190), 'category' => array_key_exists($d['category'] ?? '', self::CATEGORIES) ? $d['category'] : 'autre',
            'priority' => array_key_exists($d['priority'] ?? '', self::PRIORITY) ? $d['priority'] : 'normal', 'status' => 'open',
            'order_ref' => !empty($d['order_ref']) ? strtoupper(mb_substr(trim((string)$d['order_ref']), 0, 20)) : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        insert('support_messages', ['ticket_id' => $id, 'author' => $author, 'user_id' => $d['user_id'] ?? null, 'body' => mb_substr($body, 0, 10000), 'created_at' => now()]);
        $t = one('SELECT * FROM support_tickets WHERE id = ?', [$id]);
        if ($author === 'customer') {
            self::mail($t, 'Nous avons bien reçu ta demande', '<p>Bonjour' . ($t['name'] ? ' ' . e(strtok((string)$t['name'], ' ')) : '') . ',</p><p>Ta demande « ' . e($t['subject']) . ' » est enregistrée sous la référence <b>' . e($t['ref']) . '</b>. Nous te répondons au plus vite, en général sous 24 h ouvrées.</p>');
            $to = trim((string)setting('support_email')) ?: trim((string)setting('contact_email'));
            if ($to) try { Mailer::send($to, '[Support ' . $t['ref'] . '] ' . $t['subject'], '<p><b>' . e((string)$t['name']) . '</b> &lt;' . e($t['email']) . '&gt; · ' . e(self::CATEGORIES[$t['category']]) . '</p><blockquote>' . nl2br(e($body)) . '</blockquote><p><a href="' . e(abs_url('/admin/support/' . $t['id'])) . '">Répondre</a></p>'); } catch (Throwable $e) { log_line('emails', 'Support : ' . $e->getMessage()); }
        }
        return $t;
    }

    /** Message ajouté au fil. $author : customer | staff | note */
    public static function reply(array $t, string $author, string $body, ?int $userId = null, ?string $status = null): void
    {
        insert('support_messages', ['ticket_id' => $t['id'], 'author' => $author, 'user_id' => $userId, 'body' => mb_substr($body, 0, 10000), 'created_at' => now()]);
        $data = ['updated_at' => now()];
        if ($author === 'staff') {
            if (!$t['first_reply_at']) $data['first_reply_at'] = now();
            $data['status'] = $status ?: 'pending';
            self::mail($t, 'Réponse à ta demande ' . $t['ref'], '<div style="white-space:pre-wrap">' . nl2br(e($body)) . '</div>');
        } elseif ($author === 'customer') {
            $data['status'] = 'open';
        } elseif ($status) {
            $data['status'] = $status;
        }
        if (($data['status'] ?? '') === 'solved') $data['solved_at'] = now();
        update('support_tickets', $data, 'id = ?', [$t['id']]);
    }

    private static function mail(array $t, string $subject, string $html): void
    {
        $url = abs_url('/support/' . $t['token']);
        try {
            Mailer::send($t['email'], '[' . setting('platform_name', 'Entrevo') . ' ' . $t['ref'] . '] ' . $subject, $html
                . '<p style="margin-top:24px"><a href="' . e($url) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:11px 20px;border-radius:980px;text-decoration:none">Voir la conversation et répondre</a></p>'
                . '<p style="color:#86868b;font-size:13px">Référence ' . e($t['ref']) . '. Merci de répondre depuis ce lien plutôt que par e-mail.</p>');
        } catch (Throwable $e) { log_line('emails', 'Support ' . $t['ref'] . ' : ' . $e->getMessage()); }
    }

    public static function messages(array $t, bool $withNotes): array
    {
        return all('SELECT m.*, u.name user_name FROM support_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ?' . ($withNotes ? '' : " AND m.author <> 'note'") . ' ORDER BY m.id', [$t['id']]);
    }

    /** Réponses enregistrées (super-admin › support). */
    public static function macros(): array
    {
        $m = json_decode((string)setting('support_macros', ''), true);
        return is_array($m) && $m ? $m : [
            ['Billets renvoyés', "Bonjour,\n\nJe viens de te renvoyer tes billets à l'adresse de la commande. Pense à regarder dans les courriers indésirables.\nTu peux aussi les retrouver à tout moment dans « Mes billets » sur le site.\n\nBelle soirée !"],
            ['Remboursement : voir l\'organisateur', "Bonjour,\n\nLes remboursements sont décidés par l'organisateur de l'événement, qui fixe ses conditions. Je lui transmets ta demande ; tu peux aussi faire la demande directement depuis ta page de commande.\n\nBonne journée !"],
            ['Résolu', "Bonjour,\n\nTout est en ordre de notre côté. N'hésite pas à répondre à ce message si besoin.\n\nBonne journée !"],
        ];
    }

    public static function kpis(): array
    {
        $k = one("SELECT SUM(status = 'open') open, SUM(status = 'pending') pending, SUM(status = 'solved' AND solved_at >= NOW() - INTERVAL 30 DAY) solved30,
            SUM(status <> 'solved' AND priority IN ('high','urgent')) hot,
            AVG(CASE WHEN first_reply_at IS NOT NULL AND created_at >= NOW() - INTERVAL 30 DAY THEN TIMESTAMPDIFF(MINUTE, created_at, first_reply_at) END) frt FROM support_tickets") ?: [];
        return $k;
    }
}
