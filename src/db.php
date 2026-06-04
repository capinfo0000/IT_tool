<?php
/**
 * データアクセス層（PDO）。
 * コアサーバーV2 では MySQL、ローカルでは SQLite を同一コードで扱う。
 * Node版 lib/store.js の機能をそのまま移植したもの。
 */

function config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $real = __DIR__ . '/config.php';
    $cfg = is_file($real) ? require $real : require __DIR__ . '/config.sample.php';
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $cfg = config();
    if (($cfg['driver'] ?? 'sqlite') === 'mysql') {
        $m = $cfg['mysql'];
        $dsn = "mysql:host={$m['host']};dbname={$m['dbname']};charset={$m['charset']}";
        $pdo = new PDO($dsn, $m['user'], $m['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $path = $cfg['sqlite_path'];
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function is_mysql(): bool
{
    return (config()['driver'] ?? 'sqlite') === 'mysql';
}

function init_schema(): void
{
    $pk = is_mysql() ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $slug = is_mysql() ? 'VARCHAR(190)' : 'TEXT';

    db()->exec("CREATE TABLE IF NOT EXISTS businesses (
        id $pk,
        slug $slug NOT NULL UNIQUE,
        name TEXT NOT NULL,
        google_review_url TEXT,
        threshold INT NOT NULL DEFAULT 4,
        mode TEXT NOT NULL DEFAULT 'improve',
        created_at TEXT NOT NULL
    )");

    db()->exec("CREATE TABLE IF NOT EXISTS ratings (
        id $pk,
        business_id INT NOT NULL,
        rating INT NOT NULL,
        routed_to TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");

    db()->exec("CREATE TABLE IF NOT EXISTS feedback (
        id $pk,
        business_id INT NOT NULL,
        rating INT NOT NULL,
        message TEXT,
        contact TEXT,
        created_at TEXT NOT NULL
    )");
}

function now_iso(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('c');
}

function slugify(string $name): string
{
    $base = strtolower(trim($name));
    $base = preg_replace('/[^a-z0-9]+/u', '-', $base);
    $base = trim($base, '-');
    if ($base === '' || $base === null) $base = 'shop';
    return $base . '-' . bin2hex(random_bytes(2));
}

// ---- businesses ----

function list_businesses(): array
{
    return db()->query("SELECT * FROM businesses ORDER BY created_at DESC")->fetchAll();
}

function get_business_by_slug(string $slug): ?array
{
    $st = db()->prepare("SELECT * FROM businesses WHERE slug = ?");
    $st->execute([$slug]);
    $row = $st->fetch();
    return $row ?: null;
}

function get_business_by_id($id): ?array
{
    $st = db()->prepare("SELECT * FROM businesses WHERE id = ?");
    $st->execute([(int)$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function create_business(string $name, string $url, int $threshold, string $mode): array
{
    $mode = $mode === 'compliant' ? 'compliant' : 'improve';
    $threshold = $threshold ?: 4;
    $slug = slugify($name);
    $st = db()->prepare("INSERT INTO businesses (slug, name, google_review_url, threshold, mode, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)");
    $st->execute([$slug, trim($name), trim($url), $threshold, $mode, now_iso()]);
    return get_business_by_slug($slug);
}

function update_business($id, string $url, int $threshold, string $mode): ?array
{
    $mode = $mode === 'compliant' ? 'compliant' : 'improve';
    $threshold = $threshold ?: 4;
    $st = db()->prepare("UPDATE businesses SET google_review_url = ?, threshold = ?, mode = ? WHERE id = ?");
    $st->execute([trim($url), $threshold, $mode, (int)$id]);
    return get_business_by_id($id);
}

// ---- ratings / feedback ----

function record_rating(int $businessId, int $rating, string $routedTo): void
{
    $st = db()->prepare("INSERT INTO ratings (business_id, rating, routed_to, created_at) VALUES (?, ?, ?, ?)");
    $st->execute([$businessId, $rating, $routedTo, now_iso()]);
}

function record_feedback(int $businessId, int $rating, string $message, string $contact): void
{
    $st = db()->prepare("INSERT INTO feedback (business_id, rating, message, contact, created_at) VALUES (?, ?, ?, ?, ?)");
    $st->execute([$businessId, $rating, trim($message), trim($contact), now_iso()]);
}

function stats_for(int $businessId): array
{
    $st = db()->prepare("SELECT rating, routed_to FROM ratings WHERE business_id = ?");
    $st->execute([$businessId]);
    $ratings = $st->fetchAll();

    $total = count($ratings);
    $sum = array_sum(array_map(fn($r) => (int)$r['rating'], $ratings));
    $avg = $total ? round($sum / $total, 2) : 0;
    $toGoogle = count(array_filter($ratings, fn($r) => $r['routed_to'] === 'google'));
    $toPrivate = count(array_filter($ratings, fn($r) => $r['routed_to'] === 'private'));

    $fb = db()->prepare("SELECT * FROM feedback WHERE business_id = ? ORDER BY created_at DESC");
    $fb->execute([$businessId]);

    return [
        'total'     => $total,
        'avg'       => $avg,
        'toGoogle'  => $toGoogle,
        'toPrivate' => $toPrivate,
        'feedback'  => $fb->fetchAll(),
    ];
}

function seed_if_empty(): void
{
    $count = (int)db()->query("SELECT COUNT(*) AS c FROM businesses")->fetch()['c'];
    if ($count === 0) {
        create_business('デモ整体院', 'https://search.google.com/local/writereview?placeid=DEMO', 4, 'improve');
    }
}
