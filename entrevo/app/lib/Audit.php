<?php
declare(strict_types=1);

/** Journal d'activité : qui a fait quoi, quand (consultable par l'organisateur et le super-admin). */
final class Audit
{
    public const LABELS = [
        'event.created' => 'Événement créé', 'event.updated' => 'Événement modifié', 'event.status' => 'Statut changé', 'event.deleted' => 'Événement supprimé',
        'rate.saved' => 'Tarif enregistré', 'rate.deleted' => 'Tarif supprimé', 'seatmap.saved' => 'Plan de salle enregistré',
        'order.cancelled' => 'Commande annulée', 'order.refunded' => 'Commande remboursée', 'order.partial_refund' => 'Remboursement partiel',
        'order.resent' => 'Billets renvoyés', 'order.updated' => 'Commande modifiée', 'ticket.cancelled' => 'Billet annulé', 'ticket.renamed' => 'Billet renommé',
        'box.sale' => 'Vente guichet', 'invites.sent' => 'Invitations', 'accred.created' => 'Accréditations', 'message.sent' => 'Message envoyé',
        'cash.closed' => 'Caisse clôturée', 'team.changed' => 'Équipe modifiée', 'org.updated' => 'Organisation modifiée', 'bulk' => 'Action groupée',
        'staff.added' => 'Bénévole ajouté', 'staff.removed' => 'Bénévole supprimé', 'staff.shift' => 'Créneau bénévole',
    ];

    public static function log(string $action, string $details = '', ?int $eventId = null, ?int $orderId = null, ?int $orgId = null): void
    {
        try {
            $u = user();
            $org = $orgId ?? (current_org()['id'] ?? null);
            insert('audit_log', ['organizer_id' => $org, 'event_id' => $eventId, 'order_id' => $orderId, 'user_id' => $u['id'] ?? null,
                'action' => mb_substr($action, 0, 60), 'details' => mb_substr($details, 0, 500) ?: null, 'ip' => mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), 'created_at' => now()]);
        } catch (Throwable $e) { /* jamais bloquant */ }
    }

    public static function label(string $action): string { return self::LABELS[$action] ?? $action; }
}
