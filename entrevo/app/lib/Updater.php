<?php
declare(strict_types=1);

/**
 * Installation des paquets de mise à jour (.zip) déposés dans Super-admin > Mises à jour.
 *
 * Format d'un paquet :
 *   maj.json      {"app":"entrevo","version":"1.1.0","version_min":"1.0.0","notes":"…","supprimer":["ancien/fichier.php"]}
 *   fichiers/…    fichiers à ajouter ou remplacer (chemins relatifs à la racine d'Entrevo)
 *
 * Avant l'installation, tous les fichiers touchés sont sauvegardés dans storage/maj/ : retour arrière possible.
 * config.php, storage/ et uploads/ (vos données) ne peuvent jamais être modifiés par une mise à jour.
 */
final class Updater
{
    public const APP_ID = 'entrevo';
    private const PROTECTED = ['config.php', 'storage/', 'uploads/', '.git/', 'maj-secours.php'];

    public static function dir(): string
    {
        $d = STORAGE . '/maj';
        if (!is_dir($d)) mkdir($d, 0755, true);
        return $d;
    }

    public static function compare(string $a, string $b): int { return version_compare($a, $b); }

    private static function safe(string $p): string
    {
        $rel = str_replace('\\', '/', $p);
        $rel = ltrim($rel, '/');
        $parts = [];
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') throw new RuntimeException('Chemin interdit dans le paquet : ' . $p);
            $parts[] = $seg;
        }
        $rel = implode('/', $parts);
        if ($rel === '' || preg_match('~^[a-zA-Z]:~', $rel)) throw new RuntimeException('Chemin interdit dans le paquet : ' . $p);
        foreach (self::PROTECTED as $pre) {
            if ($rel === rtrim($pre, '/') || str_starts_with($rel, $pre)) throw new RuntimeException('Le paquet tente de modifier des données protégées : ' . $p);
        }
        return $rel;
    }

    /** Lit un paquet et vérifie son contenu, sans rien installer. */
    public static function inspect(string $zipFile): array
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('L\'extension PHP « zip » n\'est pas active sur l\'hébergement.');
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) throw new RuntimeException('Ce fichier n\'est pas un ZIP valide.');
        $prefix = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('~^([^/]+/)?maj\.json$~', $name, $m)) { $prefix = $m[1] ?? ''; break; }
        }
        if ($prefix === null) { $zip->close(); throw new RuntimeException('Fichier « maj.json » introuvable : ce n\'est pas un paquet de mise à jour Entrevo.'); }
        $man = json_decode((string)$zip->getFromName($prefix . 'maj.json'), true);
        if (!is_array($man)) { $zip->close(); throw new RuntimeException('maj.json illisible.'); }
        if (($man['app'] ?? '') !== self::APP_ID) { $zip->close(); throw new RuntimeException('Ce paquet est destiné à une autre application.'); }
        if (empty($man['version'])) { $zip->close(); throw new RuntimeException('Numéro de version manquant dans maj.json.'); }
        $files = [];
        $base = $prefix . 'fichiers/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!str_starts_with($name, $base) || str_ends_with($name, '/')) continue;
            $files[$i] = self::safe(substr($name, strlen($base)));
        }
        $delete = array_map([self::class, 'safe'], (array)($man['supprimer'] ?? []));
        $zip->close();
        $cur = app_version();
        $warnings = [];
        if (!empty($man['version_min']) && self::compare($cur, (string)$man['version_min']) < 0) {
            throw new RuntimeException('Cette mise à jour nécessite la version ' . $man['version_min'] . ' minimum (installée : ' . $cur . '). Installez d\'abord les mises à jour précédentes, dans l\'ordre.');
        }
        if (self::compare((string)$man['version'], $cur) <= 0) $warnings[] = 'La version du paquet (' . $man['version'] . ') n\'est pas plus récente que la version installée (' . $cur . ').';
        foreach (array_merge($files, $delete) as $rel) {
            $target = ROOT . '/' . $rel;
            $dir = is_file($target) ? $target : dirname($target);
            while (!is_dir($dir) && $dir !== ROOT) $dir = dirname($dir);
            if (!is_writable($dir)) throw new RuntimeException('Droits d\'écriture insuffisants sur ' . $rel . ' (vérifiez les permissions des fichiers chez l\'hébergeur).');
        }
        return ['manifest' => $man, 'files' => $files, 'delete' => $delete, 'current' => $cur, 'warnings' => $warnings];
    }

    public static function install(string $zipFile, bool $force, ?int $userId): array
    {
        $info = self::inspect($zipFile);
        if ($info['warnings'] && !$force) throw new RuntimeException(implode(' ', $info['warnings']) . ' Cochez « Forcer » pour l\'installer quand même.');
        $man = $info['manifest'];

        // 1. sauvegarde des fichiers touchés
        $stamp = date('Ymd-His');
        $backupName = 'avant-' . preg_replace('~[^0-9a-zA-Z.-]~', '', (string)$man['version']) . '-' . $stamp . '.zip';
        $backup = new ZipArchive();
        if ($backup->open(self::dir() . '/' . $backupName, ZipArchive::CREATE) !== true) throw new RuntimeException('Impossible de créer la sauvegarde (storage/maj non accessible en écriture).');
        $created = [];
        foreach (array_unique(array_merge(array_values($info['files']), $info['delete'])) as $rel) {
            $abs = ROOT . '/' . $rel;
            if (is_file($abs)) $backup->addFile($abs, 'fichiers/' . $rel);
            else $created[] = $rel;
        }
        $backup->addFromString('restauration.json', json_encode([
            'version_avant' => $info['current'], 'version_apres' => $man['version'], 'crees' => $created, 'date' => now(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $backup->close();

        // 2. installation (écriture via fichier temporaire puis renommage)
        $zip = new ZipArchive();
        $zip->open($zipFile);
        foreach ($info['files'] as $index => $rel) {
            $abs = ROOT . '/' . $rel;
            if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0755, true);
            $data = $zip->getFromIndex($index);
            if ($data === false) { $zip->close(); throw new RuntimeException('Fichier illisible dans le paquet : ' . $rel); }
            $tmp = $abs . '.maj-tmp';
            file_put_contents($tmp, $data);
            rename($tmp, $abs);
        }
        $zip->close();
        foreach ($info['delete'] as $rel) if (is_file(ROOT . '/' . $rel)) unlink(ROOT . '/' . $rel);
        if (function_exists('opcache_reset')) @opcache_reset();

        // 3. jeton de secours valable 1 heure (retour arrière si le site ne répond plus)
        $rescue = token(24);
        file_put_contents(self::dir() . '/secours.json', json_encode(['token' => $rescue, 'backup' => $backupName, 'version' => (string)$man['version'], 'expires' => time() + 3600]));

        insert('update_log', ['version' => (string)$man['version'], 'previous' => $info['current'], 'action' => 'install',
            'notes' => (string)($man['notes'] ?? ''), 'backup' => $backupName, 'user_id' => $userId, 'created_at' => now()]);

        return ['version' => $man['version'], 'previous' => $info['current'], 'files' => count($info['files']),
            'deleted' => count($info['delete']), 'backup' => $backupName, 'notes' => $man['notes'] ?? '', 'rescue' => $rescue];
    }

    public static function backups(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/avant-*.zip') ?: [] as $f) {
            $meta = [];
            $z = new ZipArchive();
            if ($z->open($f) === true) { $meta = json_decode((string)$z->getFromName('restauration.json'), true) ?: []; $z->close(); }
            $out[] = ['name' => basename($f), 'size' => filesize($f)] + $meta;
        }
        usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    /** Restaure les fichiers d'une sauvegarde (n'utilise pas la base de données). */
    public static function rollback(string $name): array
    {
        if (!preg_match('~^avant-[\w.-]+\.zip$~', $name)) throw new RuntimeException('Nom de sauvegarde invalide.');
        $file = self::dir() . '/' . $name;
        if (!is_file($file)) throw new RuntimeException('Sauvegarde introuvable.');
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) throw new RuntimeException('Sauvegarde illisible.');
        $meta = json_decode((string)$zip->getFromName('restauration.json'), true) ?: [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (!str_starts_with($n, 'fichiers/') || str_ends_with($n, '/')) continue;
            $rel = self::safe(substr($n, 9));
            $abs = ROOT . '/' . $rel;
            if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0755, true);
            file_put_contents($abs, $zip->getFromIndex($i));
        }
        $zip->close();
        foreach ($meta['crees'] ?? [] as $rel) { $abs = ROOT . '/' . self::safe($rel); if (is_file($abs)) unlink($abs); }
        if (function_exists('opcache_reset')) @opcache_reset();
        @unlink(self::dir() . '/secours.json');
        return $meta;
    }

    /** Sauvegarde SQL complète de la base (téléchargeable par le super-admin). */
    public static function dumpDatabase(): string
    {
        $out = "-- Sauvegarde Entrevo " . app_version() . ' du ' . now() . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $create = db()->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
            $out .= "DROP TABLE IF EXISTS `$table`;\n$create;\n";
            $st = db()->query("SELECT * FROM `$table`");
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string)$v), array_values($row));
                $out .= "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n";
            }
            $out .= "\n";
        }
        return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
