<?php
/**
 * Fabrique un paquet de mise à jour Entrevo (maj-X.Y.Z.zip) à déposer dans Super-admin › Mises à jour.
 *
 * Usage (depuis la racine du dépôt) :
 *   php entrevo-outils/creer-maj.php <version> <référence-git-de-départ> "<notes>" [dossier-sortie]
 * Exemple :
 *   php entrevo-outils/creer-maj.php 1.1.0 v1.0.0 "Ajout du plan de salle" livraisons
 *
 * Le paquet contient tous les fichiers de entrevo/ modifiés ou ajoutés depuis la référence git,
 * la liste des fichiers supprimés, et le fichier VERSION mis à jour.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("En ligne de commande uniquement.\n");
[$self, $version, $from, $notes, $out] = array_pad($argv, 5, null);
if (!$version || !$from) exit("Usage : php creer-maj.php <version> <ref-git> \"<notes>\" [dossier-sortie]\n");
if (!preg_match('~^\d+\.\d+\.\d+$~', $version)) exit("Version invalide (format X.Y.Z).\n");
$repo = dirname(__DIR__);
$app = $repo . '/entrevo';
$out = $out ? rtrim($out, '/') : $repo . '/livraisons';
if (!is_dir($out)) mkdir($out, 0755, true);

$current = trim((string)file_get_contents($app . '/VERSION'));
file_put_contents($app . '/VERSION', $version . "\n");

$diff = shell_exec('cd ' . escapeshellarg($repo) . ' && git diff --name-status ' . escapeshellarg($from) . ' -- entrevo/ && git ls-files --others --exclude-standard -- entrevo/');
$changed = [];
$deleted = [];
foreach (preg_split('~\R~', trim((string)$diff)) as $line) {
    if ($line === '') continue;
    $parts = preg_split('~\t~', $line);
    if (count($parts) === 1) { $changed[] = $parts[0]; continue; } // fichier non suivi
    $st = $parts[0][0];
    if ($st === 'D') $deleted[] = $parts[1];
    elseif ($st === 'R') { $deleted[] = $parts[1]; $changed[] = $parts[2]; }
    else $changed[] = $parts[1];
}
$changed[] = 'entrevo/VERSION';
$protected = ['entrevo/config.php', 'entrevo/storage/', 'entrevo/uploads/', 'entrevo/maj-secours.php'];
$keep = fn($p) => !array_filter($protected, fn($x) => $p === rtrim($x, '/') || str_starts_with($p, $x));
$changed = array_values(array_unique(array_filter($changed, $keep)));
$deleted = array_values(array_unique(array_filter($deleted, $keep)));

$zipPath = $out . '/maj-' . $version . '.zip';
@unlink($zipPath);
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE);
$zip->addFromString('maj.json', json_encode([
    'app' => 'entrevo', 'version' => $version, 'version_min' => $current, 'date' => date('c'),
    'notes' => (string)$notes, 'supprimer' => array_map(fn($p) => substr($p, 8), $deleted),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
foreach ($changed as $p) {
    if (!is_file($repo . '/' . $p)) continue;
    $zip->addFile($repo . '/' . $p, 'fichiers/' . substr($p, 8));
}
$zip->close();
echo "Paquet créé : $zipPath\n";
echo "  de $current vers $version — " . count($changed) . " fichier(s), " . count($deleted) . " suppression(s)\n";
foreach ($changed as $p) echo "  + $p\n";
foreach ($deleted as $p) echo "  - $p\n";
