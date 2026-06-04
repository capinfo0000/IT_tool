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

init_schema();
seed_if_empty();

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

if ($path === '/admin' && $method === 'GET') {
    send(view_admin(list_businesses(), base_url()));
    return;
}

if ($path === '/admin/business' && $method === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        send(view_admin(list_businesses(), base_url()), 400);
        return;
    }
    create_business(
        $name,
        $_POST['googleReviewUrl'] ?? '',
        (int)($_POST['threshold'] ?? 4),
        $_POST['mode'] ?? 'improve'
    );
    redirect('/admin');
    return;
}

if (preg_match('#^/admin/business/(\d+)$#', $path, $m) && $method === 'GET') {
    $biz = get_business_by_id((int)$m[1]);
    if (!$biz) { send(view_not_found(), 404); return; }
    send(view_dashboard($biz, stats_for((int)$biz['id']), base_url()));
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

        if ($biz['mode'] === 'compliant') {
            record_rating($bizId, $rating, 'google');
            if ($url !== '') { redirect($url); return; }
            send(view_thanks('public'));
            return;
        }

        if ($rating >= (int)$biz['threshold']) {
            record_rating($bizId, $rating, 'google');
            if ($url !== '') { redirect($url); return; }
            send(view_thanks('public'));
            return;
        }

        record_rating($bizId, $rating, 'private');
        send(view_feedback_form($biz, $rating));
        return;
    }
}

// 顧客：非公開フィードバック送信
if (preg_match('#^/r/([^/]+)/feedback$#', $path, $m) && $method === 'POST') {
    $biz = get_business_by_slug(rawurldecode($m[1]));
    if (!$biz) { send(view_not_found(), 404); return; }
    record_feedback(
        (int)$biz['id'],
        (int)($_POST['rating'] ?? 0),
        $_POST['message'] ?? '',
        $_POST['contact'] ?? ''
    );
    send(view_thanks('private'));
    return;
}

send(view_not_found(), 404);
