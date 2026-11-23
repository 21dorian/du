<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }

function val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $params = []): int
{
    $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
    return q("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $params))->rowCount();
}

function in_list(array $ids): string { return $ids ? implode(',', array_map('intval', $ids)) : '0'; }

/** Applique les évolutions de base de données manquantes. */
function run_migrations(): array
{
    $migrations = require APP . '/migrations.php';
    $current = 0;
    try { $current = (int)(val("SELECT v FROM settings WHERE k = 'db_version'") ?? 0); } catch (Throwable $e) { $current = 0; }
    $done = [];
    foreach ($migrations as $num => $statements) {
        if ($num <= $current) continue;
        foreach ($statements as $sql) {
            if (is_callable($sql)) $sql(); else db()->exec($sql);
        }
        q("REPLACE INTO settings (k, v) VALUES ('db_version', ?)", [(string)$num]);
        $done[] = $num;
    }
    return $done;
}

function latest_migration(): int { $m = require APP . '/migrations.php'; return (int)max(array_keys($m)); }
