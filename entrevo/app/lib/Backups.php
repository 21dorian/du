<?php
declare(strict_types=1);

/**
 * 2.3 : sauvegardes automatiques de la base.
 * - Écriture en flux (gzip) dans storage/sauvegardes : pas de limite mémoire même sur une grosse base.
 * - Rotation : on garde les N plus récentes (réglable) + la dernière de chaque mois sur 12 mois.
 * - Copie de sécurité facultative par e-mail (si le fichier est assez léger) pour l'avoir hors du serveur.
 * - Restauration protégée : sauvegarde de l'état actuel juste avant, confirmation écrite.
 */
final class Backups
{
    public static function dir(): string
    {
        $d = STORAGE . '/sauvegardes';
        if (!is_dir($d)) mkdir($d, 0750, true);
        if (!is_file($d . '/.htaccess')) file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
        return $d;
    }

    /** @return array<int, array{name:string, size:int, at:int}> plus récentes d'abord */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/entrevo-*.sql.gz') ?: [] as $f) $out[] = ['name' => basename($f), 'size' => (int)filesize($f), 'at' => (int)filemtime($f)];
        usort($out, fn($a, $b) => $b['at'] <=> $a['at']);
        return $out;
    }

    public static function path(string $name): string
    {
        if (!preg_match('~^entrevo-[\w.-]+\.sql\.gz$~', $name)) throw new RuntimeException('Nom de sauvegarde invalide.');
        $f = self::dir() . '/' . $name;
        if (!is_file($f)) throw new RuntimeException('Sauvegarde introuvable.');
        return $f;
    }

    /** Crée une sauvegarde complète ; renvoie son nom. */
    public static function run(string $why = 'auto'): string
    {
        @set_time_limit(300);
        $name = 'entrevo-' . date('Ymd-His') . '-' . preg_replace('~[^a-z]~', '', $why) . '.sql.gz';
        $tmp = self::dir() . '/.' . $name . '.part';
        $gz = gzopen($tmp, 'wb6');
        if (!$gz) throw new RuntimeException('Impossible d\'écrire dans storage/sauvegardes.');
        gzwrite($gz, "-- Sauvegarde Entrevo " . app_version() . ' du ' . now() . " (" . $why . ")\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $create = db()->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n" . $create . ";\n");
            $st = db()->query("SELECT * FROM `$table`", PDO::FETCH_NUM);
            $buf = []; $len = 0;
            foreach ($st as $row) {
                $buf[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string)$v), $row)) . ')';
                $len += strlen(end($buf));
                if ($len > 500000) { gzwrite($gz, "INSERT INTO `$table` VALUES " . implode(",\n", $buf) . ";\n"); $buf = []; $len = 0; }
            }
            if ($buf) gzwrite($gz, "INSERT INTO `$table` VALUES " . implode(",\n", $buf) . ";\n");
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- fin\n");
        gzclose($gz);
        rename($tmp, self::dir() . '/' . $name);
        set_setting('backup_last', (string)time());
        set_setting('backup_last_name', $name);
        self::rotate();
        if ($why === 'auto' && ($to = trim((string)setting('backup_email'))) && is_email($to)) {
            $size = filesize(self::dir() . '/' . $name);
            if ($size < 15 * 1024 * 1024) {
                try { Mailer::send($to, 'Sauvegarde ' . setting('platform_name') . ' du ' . date('d/m/Y'), '<p>Sauvegarde automatique de la base (' . self::human($size) . ') en pièce jointe. Garde-la précieusement : elle contient des données personnelles.</p>',
                    [['data' => (string)file_get_contents(self::dir() . '/' . $name), 'name' => $name]]); }
                catch (Throwable $e) { log_line('sauvegardes', 'Envoi par e-mail impossible : ' . $e->getMessage()); }
            }
        }
        return $name;
    }

    /** Garde les N dernières + la plus récente de chaque mois (12 mois). */
    public static function rotate(): int
    {
        $keep = max(3, (int)setting('backup_keep', '14'));
        $list = self::list(); $n = 0; $months = [];
        foreach ($list as $i => $b) {
            $m = date('Y-m', $b['at']);
            $monthly = !isset($months[$m]) && count($months) < 12;
            if ($monthly) $months[$m] = true;
            if ($i < $keep || $monthly || str_contains($b['name'], 'avantrestauration')) continue;
            @unlink(self::dir() . '/' . $b['name']); $n++;
        }
        return $n;
    }

    /** Tâche de fond : une sauvegarde par jour, après l'heure choisie. */
    public static function auto(): int
    {
        if (setting('backup_auto', '1') !== '1') return 0;
        $last = (int)setting('backup_last', '0');
        $hour = (int)setting('backup_hour', '4');
        if (date('Y-m-d', $last) === date('Y-m-d') || (int)date('G') < $hour) return 0;
        try { self::run('auto'); return 1; }
        catch (Throwable $e) { log_line('sauvegardes', $e->getMessage()); set_setting('backup_error', mb_substr($e->getMessage(), 0, 250)); return 0; }
    }

    /** Restaure une sauvegarde (après en avoir fait une de l'état actuel). */
    public static function restore(string $name): array
    {
        $file = self::path($name);
        $safety = self::run('avantrestauration');
        @set_time_limit(600);
        $gz = gzopen($file, 'rb');
        $stmt = ''; $n = 0;
        db()->exec('SET FOREIGN_KEY_CHECKS=0');
        while (!gzeof($gz)) {
            $line = (string)gzgets($gz, 1 << 20);
            if ($stmt === '' && (str_starts_with($line, '--') || trim($line) === '')) continue;
            $stmt .= $line;
            if (preg_match('~;\s*$~', $line)) { db()->exec($stmt); $stmt = ''; $n++; }
        }
        gzclose($gz);
        db()->exec('SET FOREIGN_KEY_CHECKS=1');
        return ['statements' => $n, 'safety' => $safety];
    }

    public static function human(int $b): string { return $b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' Mo' : number_format($b / 1024, 0, ',', ' ') . ' Ko'; }
}
