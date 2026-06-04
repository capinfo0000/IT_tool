<?php
/**
 * フロントコントローラ（ルーティング）。Node版 server.js を移植。
 * コアサーバーV2 では public/ を公開ディレクトリ、src/ をその一つ上に配置する。
 */

declare(strict_types=1);

// ビルトインサーバ（php -S）使用時、実在する静的ファイルはそのまま配信させる
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($p !== '/' && is_file(__DIR__ . $p)) {
        return false;
    }
}

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/views.php';

session_start();
init_schema();
seed_if_empty();

function is_logged_in(): bool
{
    return !empty($_SESSION['auth']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/login');
        exit;
    }
}

function base_url(): string
{
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $proto . '://' . $host;
}

function send(string $html, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

function redirect(string $location): void
{
    http_response_code(302);
    header('Location: ' . $location);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '' ) $path = '/';
if ($path !== '/') $path = rtrim($path, '/');

// --- ルーティング ---

if ($path === '/' && $method === 'GET') {
    send(view_home());
    return;
}

// ログイン
if ($path === '/login' && $method === 'GET') {
    send(view_login());
    return;
}
if ($path === '/login' && $method === 'POST') {
    $pw = (string)($_POST['password'] ?? '');
    if (hash_equals((string)(config()['admin_password'] ?? ''), $pw) && $pw !== '') {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        redirect('/admin');
        return;
    }
    send(view_login('パスワードが違います。'), 401);
    return;
}
if ($path === '/logout') {
    $_SESSION = [];
    session_destroy();
    redirect('/');
    return;
}

if ($path === '/admin' && $method === 'GET') {
    require_login();
    send(view_admin(list_businesses(), base_url()));
    return;
}

if ($path === '/admin/business' && $method === 'POST') {
    require_login();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        send(view_admin(list_businesses(), base_url()), 400);
        return;
    }
    create_business(
        $name,
        $_POST['googleReviewUrl'] ?? '',
        (int)($_POST['threshold'] ?? 4),
        $_POST['mode'] ?? 'improve',
        $_POST['slug'] ?? ''
    );
    redirect('/admin');
    return;
}

if (preg_match('#^/admin/business/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $biz = get_business_by_id((int)$m[1]);
    if (!$biz) { send(view_not_found(), 404); return; }
    send(view_dashboard($biz, stats_for((int)$biz['id']), base_url()));
    return;
}

if (preg_match('#^/admin/business/(\d+)/pop$#', $path, $m) && $method === 'GET') {
    require_login();
    $biz = get_business_by_id((int)$m[1]);
    if (!$biz) { send(view_not_found(), 404); return; }
    send(view_pop($biz, base_url()));
    return;
}

if (preg_match('#^/admin/business/(\d+)$#', $path, $m) && $method === 'POST') {
    require_login();
    $biz = get_business_by_id((int)$m[1]);
    if (!$biz) { send(view_not_found(), 404); return; }
    update_business(
        (int)$m[1],
        $_POST['googleReviewUrl'] ?? '',
        (int)($_POST['threshold'] ?? 4),
        $_POST['mode'] ?? 'improve',
        $_POST['slug'] ?? ''
    );
    redirect('/admin/business/' . (int)$m[1]);
    return;
}

// 顧客：評価ページ / 送信
if (preg_match('#^/r/([^/]+)$#', $path, $m)) {
    $biz = get_business_by_slug(rawurldecode($m[1]));
    if (!$biz) { send(view_not_found(), 404); return; }

    if ($method === 'GET') {
        send(view_rate($biz));
        return;
    }

    if ($method === 'POST') {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        $bizId = (int)$biz['id'];
        $url = trim((string)$biz['google_review_url']);

        // しきい値以上、またはコンプラ重視モードはGoogle誘導
        $toGoogle = ($biz['mode'] === 'compliant') || ($rating >= (int)$biz['threshold']);

        if ($toGoogle) {
            record_rating($bizId, $rating, 'google');
            if ($url === '') { send(view_thanks($biz, 'public')); return; }
            send(view_rate_result($biz, $rating, 'google'));
            return;
        }

        record_rating($bizId, $rating, 'private');
        send(view_rate_result($biz, $rating, 'private'));
        return;
    }
}

// 顧客：非公開フィードバック（GET=フォーム表示 / POST=送信）
if (preg_match('#^/r/([^/]+)/feedback$#', $path, $m)) {
    $biz = get_business_by_slug(rawurldecode($m[1]));
    if (!$biz) { send(view_not_found(), 404); return; }

    if ($method === 'GET') {
        $rating = max(0, min(5, (int)($_GET['rating'] ?? 0)));
        send(view_feedback_form($biz, $rating));
        return;
    }
    if ($method === 'POST') {
        record_feedback(
            (int)$biz['id'],
            (int)($_POST['rating'] ?? 0),
            $_POST['message'] ?? '',
            $_POST['contact'] ?? ''
        );
        send(view_thanks($biz, 'private'));
        return;
    }
}

// 顧客：完了画面（「後で」「スキップ」用）
if (preg_match('#^/r/([^/]+)/done$#', $path, $m) && $method === 'GET') {
    $biz = get_business_by_slug(rawurldecode($m[1]));
    if (!$biz) { send(view_not_found(), 404); return; }
    send(view_thanks($biz, 'public'));
    return;
}

send(view_not_found(), 404);
