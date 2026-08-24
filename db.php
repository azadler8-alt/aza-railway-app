<?php
// ============================================================
// ڕێکخستنی پەیوەندی داتابەیس — environment variables لە production، default بۆ Laragon
// ============================================================
define('DB_HOST', getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'aza_db');
define('DB_USER', getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ناوی کۆمپانیا/سیستەم کە لە هەموو لاپەڕەکان بەکاردێت
define('APP_NAME', 'aza');
$baseUrl = getenv('BASE_URL');
$defaultBaseUrl = getenv('RAILWAY_ENVIRONMENT_NAME') === false ? '/aza' : '';
define('BASE_URL', $baseUrl === false ? $defaultBaseUrl : rtrim($baseUrl, '/'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $adminPassword = getenv('ADMIN_PASSWORD');
            if ($adminPassword !== false && $adminPassword !== '') {
                $stmt = $pdo->query("SELECT password_hash FROM users WHERE username = 'admin' LIMIT 1");
                $admin = $stmt->fetch();
                if ($admin && str_starts_with($admin['password_hash'], '$2y$10$examplehash')) {
                    $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
                    $update->execute([password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die('کێشەی پەیوەندی داتابەیس. تکایە ڕێکخستنەکان بپشکنە.');
        }
    }
    return $pdo;
}
