<?php
declare(strict_types=1);

/* 4.1 : éditeurs visuels — billet en glisser-déposer, page d'événement par blocs. */

require_once APP . '/controllers/pro.php';

function ticket_designer(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        if (input('action') === 'reset') { update('events', ['ticket_layout' => null], 'id = ?', [$ev['id']]); json_out(['ok' => true]); }
        $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $L = TicketDesign::clean((array)($in['layout'] ?? []));
        if (!$L) json_out(['error' => 'Le billet est vide : ajoute au moins un élément.'], 422);
        if (!array_filter($L['elements'], fn($e) => $e['type'] === 'qr')) json_out(['error' => 'Ajoute le QR code : sans lui, le billet ne peut pas être contrôlé.'], 422);
        update('events', ['ticket_layout' => json_encode($L, JSON_UNESCAPED_UNICODE)], 'id = ?', [$ev['id']]);
        Audit::log('ticket.design', 'Maquette de billet enregistrée', (int)$ev['id']);
        json_out(['ok' => true]);
    }
    $layout = TicketDesign::get($ev);
    $presets = TicketDesign::presets($ev);
    $poster = $ev['image'] ? url($ev['image']) : null; $logo = $ev['ticket_logo'] ? url($ev['ticket_logo']) : null;
    return ev_page($ev, 'ticket', 'Visuel des billets', 'ticket_designer', compact('layout', 'presets', 'poster', 'logo'));
}

/** Aperçu PDF avec un faux billet (maquette envoyée ou enregistrée). */
function ticket_preview(string $id): never
{
    $org = require_org();
    $ev = org_event((int)$id);
    $L = is_post() ? TicketDesign::clean((array)(json_decode((string)input('layout'), true) ?: [])) : TicketDesign::get($ev);
    $order = ['ref' => 'EAPERCU1', 'first_name' => 'Camille', 'last_name' => 'Exemple', 'email' => 'camille@exemple.fr', 'token' => ''];
    $rate = one('SELECT name, price FROM rates WHERE event_id = ? ORDER BY sort, id LIMIT 1', [$ev['id']]);
    $t = ['code' => 'APERCU2026X', 'first_name' => 'Camille', 'last_name' => 'Exemple', 'rate_name' => $rate['name'] ?? 'Plein tarif', 'price' => (int)($rate['price'] ?? 2000), 'type' => 'ticket', 'seat' => null, 'session_id' => null];
    $pdf = $L ? TicketDesign::build($L, $order, $ev, $org, [$t]) : TicketPdf::build($order + ['id' => 0, 'phone' => ''], $ev, $org, [$t + ['id' => 0, 'order_id' => 0, 'max_entries' => 1, 'per_day' => 0, 'status' => 'valid', 'answers' => null]]);
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="apercu-billet.pdf"');
    echo $pdf; exit;
}

function page_builder(string $id): string
{
    require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        if (!empty($_FILES['image'])) { try { $p = upload_image('image'); json_out(['src' => $p, 'url' => url($p)]); } catch (RuntimeException $e) { json_out(['error' => $e->getMessage()], 422); } }
        $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $blocks = PageBlocks::clean((array)($in['blocks'] ?? []));
        update('events', ['page_blocks' => $blocks ? json_encode($blocks, JSON_UNESCAPED_UNICODE) : null], 'id = ?', [$ev['id']]);
        Audit::log('page.blocks', count($blocks) . ' bloc(s)', (int)$ev['id']);
        json_out(['ok' => true, 'n' => count($blocks)]);
    }
    $blocks = PageBlocks::get($ev);
    $artists = (int)val('SELECT COUNT(*) FROM artists WHERE event_id = ?', [$ev['id']]);
    return ev_page($ev, 'blocks', 'Page par blocs', 'page_builder', compact('blocks', 'artists'));
}
