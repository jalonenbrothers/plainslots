<?php
/**
 * lib/db.php — PDO singleton. Prepared statements only, everywhere.
 * MySQL in production; sqlite fallback for tests (PLAINSLOTS_DB=sqlite:path).
 */
declare(strict_types=1);

if (is_file(__DIR__ . '/bootstrap_extra.php')) require_once __DIR__ . '/bootstrap_extra.php'; // dev-only, gitignored

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit("Setup error: lib/config.php is missing — copy lib/config.example.php to lib/config.php and fill in the database credentials. See README.md.");
        }
        $cfg = require $path;
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (getenv('PLAINSLOTS_DB')) { // tests: sqlite:... — never in production
            $dsn = getenv('PLAINSLOTS_DB');
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->sqliteCreateFunction('BASE62', function () { return ''; }); // no-op placeholder
        } else {
            $cfg = config()['db'];
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true); // consistent int-as-string
    }
    return $pdo;
}

/** Query helper: prepare + execute. */
function dbq(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}