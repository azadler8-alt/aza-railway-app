<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** دەبێت لۆگین بووبێت */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function current_user(): array
{
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'username'   => $_SESSION['username'] ?? '',
        'full_name'  => $_SESSION['full_name'] ?? '',
        'role'       => $_SESSION['role'] ?? '',
        'is_cashier' => $_SESSION['is_cashier'] ?? 0,
        'pos_id'     => $_SESSION['pos_id'] ?? null,
    ];
}

/** تەنها ڕۆڵی admin خۆکارانە هەموو بەشەکانی هەیە. ڕۆڵی 'it' وەک هەر یوزەرێکی تر پێویستی بە مۆڵەتی ڕوونە. */
function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** یوزەری کاشێر تەنها دەتوانێت بەشی POS بکاتەوە */
function enforce_cashier_lock(): void
{
    if (!empty($_SESSION['is_cashier'])) {
        $script = basename($_SERVER['SCRIPT_NAME']);
        $allowed = ['pos_screen.php', 'pos_checkout.php', 'logout.php'];
        $inPosFolder = strpos($_SERVER['SCRIPT_NAME'], '/pos/') !== false;
        if (!$inPosFolder && !in_array($script, $allowed, true)) {
            header('Location: ' . BASE_URL . '/pos/pos_screen.php');
            exit;
        }
    }
}

/**
 * پشکنینی مۆڵەت بۆ بەشێک. ئەدمین/ئایتی هەمیشە هەموو شتێکیان هەیە.
 * @param string $module_key e.g. 'purchase', 'purchase_review', 'accounting'
 * @param string $need 'view' | 'edit'
 */
function has_permission(string $module_key, string $need = 'view'): bool
{
    if (is_admin()) return true;
    if (empty($_SESSION['user_id'])) return false;
    static $cache = [];
    $uid = $_SESSION['user_id'];
    if (!isset($cache[$uid])) {
        $stmt = db()->prepare('SELECT module_key, can_view, can_edit FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$uid]);
        $cache[$uid] = [];
        foreach ($stmt->fetchAll() as $row) {
            $cache[$uid][$row['module_key']] = $row;
        }
    }
    $perm = $cache[$uid][$module_key] ?? null;
    if (!$perm) return false;
    return $need === 'edit' ? (bool)$perm['can_edit'] : (bool)$perm['can_view'];
}

function require_permission(string $module_key, string $need = 'view'): void
{
    if (!has_permission($module_key, $need)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center">ببورە، تۆ مۆڵەتی چوونەژوورەوی ئەم بەشەت نیە.</div>');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        die('CSRF token هەڵەیە. دووبارە هەوڵ بدەوە.');
    }
}
