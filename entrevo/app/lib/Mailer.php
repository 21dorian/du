<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/** Envoi des e-mails (SMTP si configuré, sinon fonction mail() de l'hébergement). */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, array $attachments = [], ?string $replyTo = null): void
    {
        $m = new PHPMailer(true);
        $m->CharSet = 'UTF-8';
        if (setting('smtp_host')) {
            $m->isSMTP();
            $m->Host = (string)setting('smtp_host');
            $m->Port = (int)setting('smtp_port', 587);
            $sec = (string)setting('smtp_secure');
            if ($sec === 'ssl') $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            elseif ($sec === 'tls') $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            else { $m->SMTPSecure = ''; $m->SMTPAutoTLS = false; }
            if (setting('smtp_user')) { $m->SMTPAuth = true; $m->Username = (string)setting('smtp_user'); $m->Password = (string)setting('smtp_pass'); }
            $m->Timeout = 20;
        } else {
            $m->isMail();
        }
        $from = (string)(setting('mail_from') ?: setting('smtp_user') ?: ('no-reply@' . preg_replace('~^www\.~', '', $_SERVER['HTTP_HOST'] ?? 'localhost')));
        $m->setFrom($from, (string)(setting('mail_from_name') ?: setting('platform_name')));
        $m->addAddress($to);
        if ($replyTo && is_email($replyTo)) $m->addReplyTo($replyTo);
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body = self::wrap($subject, $html);
        $m->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</tr>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
        foreach ($attachments as $a) $m->addStringAttachment($a['data'], $a['name'], PHPMailer::ENCODING_BASE64, $a['type'] ?? 'application/pdf');
        $m->send();
    }

    private static function wrap(string $title, string $body): string
    {
        $c = e(setting('primary_color', '#5b3df5'));
        return '<!doctype html><html><body style="margin:0;background:#f4f4f8;font-family:Arial,Helvetica,sans-serif;color:#1c1c28">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px 12px">'
            . '<div style="background:' . $c . ';color:#fff;border-radius:12px 12px 0 0;padding:18px 24px;font-size:20px;font-weight:bold">' . e(setting('platform_name')) . '</div>'
            . '<div style="background:#fff;border-radius:0 0 12px 12px;padding:24px;line-height:1.5;font-size:15px">' . $body . '</div>'
            . '<p style="color:#8a8a99;font-size:12px;text-align:center">' . e(setting('platform_name')) . ' · ' . e(abs_url('/')) . '</p>'
            . '</div></body></html>';
    }

    /** Envoie les billets d'une commande payée (PDF en pièce jointe). */
    public static function sendOrder(int $orderId): bool
    {
        $o = one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$o || $o['status'] !== 'paid' || !is_email((string)$o['email'])) return false;
        $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
        $tickets = all("SELECT * FROM tickets WHERE order_id = ? AND status = 'valid' ORDER BY id", [$orderId]);
        if (!$tickets) return false;
        $link = abs_url('/commande/' . $o['ref'], ['t' => $o['token']]);
        $rows = '';
        foreach ($tickets as $t) {
            $rows .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee">' . e(trim($t['first_name'] . ' ' . $t['last_name'])) . '</td>'
                . '<td style="padding:6px 0;border-bottom:1px solid #eee">' . e($t['rate_name']) . '</td>'
                . '<td style="padding:6px 0;border-bottom:1px solid #eee;font-family:monospace">' . e($t['code']) . '</td></tr>';
        }
        $html = '<p>Bonjour ' . e($o['first_name']) . ',</p>'
            . '<p>Merci pour votre commande <b>' . e($o['ref']) . '</b>' . ($o['total'] > 0 ? ' d\'un montant de <b>' . e(money((int)$o['total'])) . '</b>' : '') . '.</p>'
            . '<p style="font-size:18px;margin:18px 0 4px"><b>' . e($ev['name']) . '</b></p>'
            . '<p style="margin:0;color:#555">' . e(fr_date($ev['starts_at'])) . ($ev['venue'] ? '<br>' . e($ev['venue']) . ($ev['city'] ? ', ' . e($ev['city']) : '') : '') . '</p>'
            . '<p><b>Vos billets sont en pièce jointe (PDF).</b> Présentez le QR code à l\'entrée, imprimé ou sur votre téléphone.</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $rows . '</table>'
            . '<p style="margin-top:22px"><a href="' . e($link) . '" style="background:' . e(setting('primary_color', '#5b3df5')) . ';color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;display:inline-block">Voir mes billets</a></p>'
            . '<p style="color:#777;font-size:13px">Une question sur l\'événement ? Contactez l\'organisateur : ' . e($org['name']) . ($org['email'] ? ' — ' . e($org['email']) : '') . '</p>';
        $pdf = TicketPdf::build($o, $ev, $org, $tickets);
        self::send((string)$o['email'], 'Vos billets — ' . $ev['name'], $html, [['data' => $pdf, 'name' => 'billets-' . $o['ref'] . '.pdf']], $org['email'] ?? null);
        update('orders', ['email_sent_at' => now()], 'id = ?', [$orderId]);
        return true;
    }

    /** Envoi sans bloquer la commande si l'e-mail échoue. */
    public static function sendOrderSafe(int $orderId): bool
    {
        try { return self::sendOrder($orderId); }
        catch (Throwable $e) { log_line('emails', 'Commande #' . $orderId . ' : ' . $e->getMessage()); return false; }
    }
}
