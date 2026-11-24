<?php
declare(strict_types=1);

/* Suivi des campagnes (ouvertures, clics) et désinscription. Aucune connexion requise. */

function campaign_recipient(string $token): ?array
{
    if (!preg_match('~^[A-Za-z0-9_-]{8,40}$~', $token)) return null;
    return one('SELECT r.*, c.organizer_id FROM campaign_recipients r JOIN campaigns c ON c.id = r.campaign_id WHERE r.token = ?', [$token]);
}

function campaign_open(string $token): never
{
    if ($r = campaign_recipient($token)) q('UPDATE campaign_recipients SET opens = opens + 1, opened_at = COALESCE(opened_at, NOW()) WHERE id = ?', [$r['id']]);
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, max-age=0');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

function campaign_click(string $token): never
{
    $url = (string)($_GET['u'] ?? '');
    $r = campaign_recipient($token);
    if (!$r || !preg_match('~^https?://~i', $url) || !Campaigns::checkSig($token, $url, (string)($_GET['s'] ?? ''))) redirect('/');
    q('UPDATE campaign_recipients SET clicks = clicks + 1, clicked_at = COALESCE(clicked_at, NOW()), opened_at = COALESCE(opened_at, NOW()) WHERE id = ?', [$r['id']]);
    header('Location: ' . $url, true, 302);
    exit;
}

function unsubscribe(string $token): string
{
    $r = campaign_recipient($token);
    if (!$r) abort(404, 'Ce lien de désinscription n\'existe pas (ou plus).');
    $org = one('SELECT name FROM organizers WHERE id = ?', [$r['organizer_id']]);
    $done = (bool)val('SELECT 1 FROM email_unsubscribes WHERE organizer_id = ? AND email = ?', [$r['organizer_id'], $r['email']]);
    // POST = bouton de la page, ou désinscription « en un clic » des messageries (List-Unsubscribe-Post)
    if (is_post() || $done) {
        q('INSERT IGNORE INTO email_unsubscribes (organizer_id, email, campaign_id, created_at) VALUES (?, ?, ?, NOW())', [$r['organizer_id'], $r['email'], $r['campaign_id']]);
        q('UPDATE campaign_recipients SET unsubscribed_at = COALESCE(unsubscribed_at, NOW()) WHERE id = ?', [$r['id']]);
        q('UPDATE orders SET marketing_optin = 0 WHERE organizer_id = ? AND LOWER(email) = ?', [$r['organizer_id'], $r['email']]);
        $done = true;
    }
    return view('layout_public', ['title' => 'Désinscription', 'content' => view('public/unsubscribe', ['done' => $done, 'org' => $org, 'email' => $r['email']])]);
}
