<?php
declare(strict_types=1);

/* Vérification KYC : dépôt côté organisateur, contrôle côté super-admin. */

require_once APP . '/controllers/pro.php';

function kyc_page(): string
{
    $org = require_org(['owner']);
    if (is_post()) {
        $kind = (string)input('kind');
        if (!isset(Kyc::KINDS[$kind])) back(['error' => 'Type de document inconnu.']);
        try { $f = store_private_upload('file', 'kyc/' . $org['id'], 10); } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
        insert('org_documents', ['organizer_id' => $org['id'], 'kind' => $kind, 'file' => $f['file'], 'original_name' => $f['original'], 'mime' => $f['mime'], 'size' => $f['size'], 'status' => 'pending', 'uploaded_at' => now()]);
        $st = Kyc::refresh((int)$org['id']);
        Audit::log('org.updated', 'Justificatif déposé : ' . Kyc::KINDS[$kind][1]);
        if ($st === 'pending') {
            try { foreach (array_column(all("SELECT email FROM users WHERE role = 'superadmin' AND active = 1"), 'email') as $to) Mailer::send($to, 'Pièces à vérifier : ' . $org['name'], '<p>« ' . e($org['name']) . ' » a déposé tous ses justificatifs.</p><p><a href="' . e(abs_url('/admin/kyc/' . $org['id'])) . '">Les vérifier</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
        }
        back(['success' => $st === 'pending' ? 'Tout est déposé ! On vérifie et on te prévient par e-mail.' : 'Document reçu.']);
    }
    $docs = Kyc::latest((int)$org['id']);
    $history = all('SELECT * FROM org_documents WHERE organizer_id = ? ORDER BY id DESC LIMIT 30', [$org['id']]);
    return pro_page('Vérification du compte', 'kyc', compact('org', 'docs', 'history'), 'kyc');
}

function kyc_file(string $id): never
{
    $u = require_login(['owner', 'superadmin']);
    $d = one('SELECT * FROM org_documents WHERE id = ?', [(int)$id]);
    if (!$d) abort(404);
    if ($u['role'] !== 'superadmin' && (int)(current_org()['id'] ?? 0) !== (int)$d['organizer_id']) abort(403);
    send_private_file($d['file'], $d['original_name'] ?: 'document', $d['mime'] ?: 'application/octet-stream');
}

function admin_kyc(): string
{
    require_login(['superadmin']);
    $st = (string)input('st', 'pending');
    if ($st !== 'all' && !isset(Kyc::STATUS[$st])) $st = 'pending';
    $list = all('SELECT o.*, (SELECT COUNT(*) FROM org_documents d WHERE d.organizer_id = o.id) docs, (SELECT MAX(uploaded_at) FROM org_documents d WHERE d.organizer_id = o.id) last_upload
        FROM organizers o WHERE ' . ($st === 'all' ? '1' : 'o.kyc_status = ?') . ' ORDER BY last_upload DESC, o.created_at DESC LIMIT 300', $st === 'all' ? [] : [$st]);
    $counts = array_column(all('SELECT kyc_status s, COUNT(*) n FROM organizers GROUP BY kyc_status'), 'n', 's');
    return view('layout_back', ['title' => 'Vérification KYC', 'area' => 'admin', 'active' => 'kyc', 'content' => view('admin/kyc', compact('list', 'st', 'counts'))]);
}

function admin_kyc_org(string $id): string
{
    $u = require_login(['superadmin']);
    $org = one('SELECT * FROM organizers WHERE id = ?', [(int)$id]);
    if (!$org) abort(404);
    if (is_post()) {
        $d = one('SELECT * FROM org_documents WHERE id = ? AND organizer_id = ?', [(int)input('doc_id'), $org['id']]);
        $ok = input('decision') === 'approve';
        if ($d) update('org_documents', ['status' => $ok ? 'approved' : 'rejected', 'note' => mb_substr(trim((string)input('note')), 0, 500) ?: null, 'reviewed_at' => now(), 'reviewed_by' => $u['id']], 'id = ?', [$d['id']]);
        $before = $org['kyc_status'];
        $st = Kyc::refresh((int)$org['id']);
        if ($st !== $before && in_array($st, ['verified', 'rejected'], true)) {
            $msg = $st === 'verified' ? '<p>Bonne nouvelle : les justificatifs de « ' . e($org['name']) . ' » sont validés Tes reversements peuvent partir.</p>'
                : '<p>Un de tes justificatifs doit être corrigé' . (input('note') ? ' : « ' . e((string)input('note')) . ' »' : '') . '.</p><p><a href="' . e(abs_url('/pro/verification')) . '">Déposer un nouveau document</a></p>';
            try { Mailer::send((string)$org['email'], $st === 'verified' ? 'Ton compte est vérifié' : 'Un justificatif à corriger', $msg); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
        }
        back(['success' => $ok ? 'Document validé.' : 'Document refusé, l\'organisateur est prévenu.']);
    }
    $docs = Kyc::latest((int)$org['id']);
    $history = all('SELECT d.*, u.name reviewer FROM org_documents d LEFT JOIN users u ON u.id = d.reviewed_by WHERE d.organizer_id = ? ORDER BY d.id DESC', [$org['id']]);
    return view('layout_back', ['title' => 'KYC · ' . $org['name'], 'area' => 'admin', 'active' => 'kyc', 'crumbs' => [['Vérification KYC', '/admin/kyc']],
        'content' => view('admin/kyc_org', compact('org', 'docs', 'history'))]);
}
