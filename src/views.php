<?php
/**
 * 画面（HTML生成）。Node版 lib/views.js を移植。
 */

function esc($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function layout(string $title, string $body): string
{
    $t = esc($title);
    return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$t} | クチコミブースト</title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<header class="site-header">
  <a class="brand" href="/">★ クチコミブースト</a>
  <nav><a href="/admin">管理画面</a></nav>
</header>
<main class="container">
{$body}
</main>
<footer class="site-footer">クチコミブースト MVP — 個人店向けGoogle口コミ収集ツール</footer>
</body>
</html>
HTML;
}

function stars(int $n, int $filled): string
{
    $out = '';
    for ($i = 1; $i <= $n; $i++) $out .= $i <= $filled ? '★' : '☆';
    return $out;
}

function view_home(): string
{
    $body = <<<HTML
  <section class="hero">
    <h1>来店客の「満足」を、<br>Googleの星に変える。</h1>
    <p class="lead">個人店向けの口コミ自動収集ツール。QR/リンクを配るだけで、満足したお客様をGoogleクチコミ投稿へスムーズに誘導。不満は店内フィードバックとして非公開で受け取り、改善に活かせます。</p>
    <div class="cta-row">
      <a class="btn primary" href="/admin">管理画面をひらく</a>
    </div>
    <p class="note">※ Googleのポリシー（レビューゲーティング禁止）に配慮し、店舗ごとに運用モードを選べます。</p>
  </section>

  <section class="features">
    <div class="card"><h3>1. リンク発行</h3><p>店舗を登録すると共有リンクが発行されます。レジ横・名刺・LINEで配布。</p></div>
    <div class="card"><h3>2. かんたん評価</h3><p>お客様は★を選ぶだけ。高評価はGoogle投稿へ、低評価は非公開フィードバックへ。</p></div>
    <div class="card"><h3>3. 見える化</h3><p>件数・平均★・Google誘導数・改善フィードバックをダッシュボードで確認。</p></div>
  </section>
HTML;
    return layout('トップ', $body);
}

function view_admin(array $businesses, string $baseUrl): string
{
    if ($businesses) {
        $rows = '';
        foreach ($businesses as $b) {
            $link = $baseUrl . '/r/' . $b['slug'];
            $modeLabel = $b['mode'] === 'compliant' ? 'コンプラ重視' : '改善フォーカス';
            $rows .= '<tr>'
                . '<td><strong>' . esc($b['name']) . '</strong><br><span class="muted">★' . (int)$b['threshold'] . '以上でGoogle誘導 / ' . $modeLabel . '</span></td>'
                . '<td><a href="/r/' . esc($b['slug']) . '" target="_blank">' . esc($link) . '</a></td>'
                . '<td><a class="btn small" href="/admin/business/' . (int)$b['id'] . '">ダッシュボード</a></td>'
                . '</tr>';
        }
    } else {
        $rows = '<tr><td colspan="3" class="muted">まだ店舗がありません。下のフォームから登録してください。</td></tr>';
    }

    $body = <<<HTML
  <h1>管理画面</h1>
  <h2>登録店舗</h2>
  <table class="table">
    <thead><tr><th>店舗</th><th>依頼リンク</th><th></th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>

  <h2>店舗を新規登録</h2>
  <form class="form" method="POST" action="/admin/business">
    <label>店舗名
      <input name="name" required placeholder="例：○○整体院 △△店">
    </label>
    <label>Googleクチコミ投稿URL
      <input name="googleReviewUrl" placeholder="https://search.google.com/local/writereview?placeid=...">
      <small class="muted">GoogleビジネスプロフィールのクチコミリンクをそのままでOK。後から変更できます。</small>
    </label>
    <label>Google誘導のしきい値（この★以上で投稿画面へ）
      <select name="threshold">
        <option value="5">★5のみ</option>
        <option value="4" selected>★4以上</option>
        <option value="3">★3以上</option>
      </select>
    </label>
    <label>運用モード
      <select name="mode">
        <option value="improve" selected>改善フォーカス（低評価は非公開回収）</option>
        <option value="compliant">コンプラ重視（全員にGoogle案内＋任意で改善フィードバック）</option>
      </select>
      <small class="muted">「改善フォーカス」はレビューゲーティングに該当し得ます。利用は各店舗の責任で。</small>
    </label>
    <button class="btn primary" type="submit">登録する</button>
  </form>
HTML;
    return layout('管理画面', $body);
}

function view_dashboard(array $biz, array $stats, string $baseUrl): string
{
    $link = $baseUrl . '/r/' . $biz['slug'];

    if ($stats['feedback']) {
        $fbRows = '';
        foreach ($stats['feedback'] as $f) {
            $msg = $f['message'] !== '' && $f['message'] !== null ? esc($f['message']) : '<span class="muted">（コメントなし）</span>';
            $contact = $f['contact'] !== '' && $f['contact'] !== null ? esc($f['contact']) : '<span class="muted">-</span>';
            $when = esc(str_replace('T', ' ', substr($f['created_at'], 0, 16)));
            $fbRows .= '<tr>'
                . '<td>' . esc(stars(5, (int)$f['rating'])) . '</td>'
                . '<td>' . $msg . '</td>'
                . '<td>' . $contact . '</td>'
                . '<td class="muted">' . $when . '</td>'
                . '</tr>';
        }
    } else {
        $fbRows = '<tr><td colspan="4" class="muted">まだ非公開フィードバックはありません。</td></tr>';
    }

    $name = esc($biz['name']);
    $linkEsc = esc($link);
    $slug = esc($biz['slug']);
    $total = (int)$stats['total'];
    $avg = $stats['avg'];
    $toGoogle = (int)$stats['toGoogle'];
    $toPrivate = (int)$stats['toPrivate'];

    $body = <<<HTML
  <p><a href="/admin">← 店舗一覧へ</a></p>
  <h1>{$name}</h1>

  <div class="share">
    <strong>お客様に渡す依頼リンク：</strong>
    <code>{$linkEsc}</code>
    <a class="btn small" href="/r/{$slug}" target="_blank">開いて確認</a>
  </div>

  <section class="kpis">
    <div class="kpi"><span class="num">{$total}</span><span class="lbl">総評価数</span></div>
    <div class="kpi"><span class="num">{$avg}</span><span class="lbl">平均★</span></div>
    <div class="kpi"><span class="num">{$toGoogle}</span><span class="lbl">Google誘導</span></div>
    <div class="kpi"><span class="num">{$toPrivate}</span><span class="lbl">非公開回収</span></div>
  </section>

  <h2>非公開フィードバック（改善のヒント）</h2>
  <table class="table">
    <thead><tr><th>評価</th><th>コメント</th><th>連絡先</th><th>日時</th></tr></thead>
    <tbody>{$fbRows}</tbody>
  </table>
HTML;
    return layout($biz['name'] . ' ダッシュボード', $body);
}

function view_rate(array $biz): string
{
    $slug = esc($biz['slug']);
    $name = esc($biz['name']);
    $buttons = '';
    foreach ([1, 2, 3, 4, 5] as $n) {
        $buttons .= '<button class="star-btn" type="submit" name="rating" value="' . $n . '">'
            . stars(5, $n) . '<span class="star-num">' . $n . '</span></button>';
    }
    $body = <<<HTML
  <section class="rate">
    <h1>{$name}</h1>
    <p class="lead">本日はご来店ありがとうございました。<br>ご満足度を★で教えてください。</p>
    <form method="POST" action="/r/{$slug}" class="star-form">
      {$buttons}
    </form>
  </section>
HTML;
    return layout($biz['name'] . ' のご感想', $body);
}

function view_feedback_form(array $biz, int $rating): string
{
    $slug = esc($biz['slug']);
    $star = esc(stars(5, $rating));
    $body = <<<HTML
  <section class="rate">
    <h1>貴重なご意見をお聞かせください</h1>
    <p class="lead">{$star}<br>至らぬ点があり申し訳ございません。改善のため、よろしければ詳しくお聞かせください。<strong>この内容は公開されません。</strong></p>
    <form method="POST" action="/r/{$slug}/feedback" class="form">
      <input type="hidden" name="rating" value="{$rating}">
      <label>ご意見・改善してほしい点
        <textarea name="message" rows="5" placeholder="気になった点を教えてください"></textarea>
      </label>
      <label>ご連絡先（任意。お返事が必要な場合）
        <input name="contact" placeholder="メール または 電話番号">
      </label>
      <button class="btn primary" type="submit">送信する</button>
    </form>
  </section>
HTML;
    return layout('ご意見をお聞かせください', $body);
}

function view_thanks(string $kind): string
{
    $msg = $kind === 'private'
        ? 'ご意見ありがとうございました。今後の改善に活かしてまいります。'
        : 'ありがとうございました。';
    $msg = esc($msg);
    $body = <<<HTML
  <section class="rate center">
    <h1>ありがとうございました 🙏</h1>
    <p class="lead">{$msg}</p>
  </section>
HTML;
    return layout('ありがとうございました', $body);
}

function view_not_found(): string
{
    return layout('見つかりません', '<h1>404</h1><p>ページが見つかりません。</p><p><a href="/">トップへ</a></p>');
}
