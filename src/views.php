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
  <p style="text-align:right"><a href="/logout">ログアウト</a></p>
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
    <label>スラッグ（URL用・任意）
      <input name="slug" placeholder="例：seitai-shibuya">
      <small class="muted">依頼リンクは /r/（入力値）になります。空欄なら自動採番。半角英数とハイフンのみ。</small>
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

function view_login(string $error = ''): string
{
    $err = $error !== '' ? '<p class="note" style="color:#b91c1c">' . esc($error) . '</p>' : '';
    $body = <<<HTML
  <section class="rate" style="max-width:420px">
    <h1>管理画面ログイン</h1>
    {$err}
    <form class="form" method="POST" action="/login">
      <label>パスワード
        <input type="password" name="password" required autofocus>
      </label>
      <button class="btn primary" type="submit">ログイン</button>
    </form>
  </section>
HTML;
    return layout('ログイン', $body);
}

function view_dashboard(array $biz, array $stats, string $baseUrl): string
{
    $link = $baseUrl . '/r/' . $biz['slug'];
    $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' . rawurlencode($link);

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
    $qrEsc = esc($qr);
    $slug = esc($biz['slug']);
    $total = (int)$stats['total'];
    $avg = $stats['avg'];
    $toGoogle = (int)$stats['toGoogle'];
    $toPrivate = (int)$stats['toPrivate'];
    $url = esc($biz['google_review_url']);
    $bid = (int)$biz['id'];
    $thr = (int)$biz['threshold'];
    $selImprove = $biz['mode'] === 'improve' ? 'selected' : '';
    $selCompliant = $biz['mode'] === 'compliant' ? 'selected' : '';
    $sel3 = $thr === 3 ? 'selected' : '';
    $sel4 = $thr === 4 ? 'selected' : '';
    $sel5 = $thr === 5 ? 'selected' : '';

    $body = <<<HTML
  <p><a href="/admin">← 店舗一覧へ</a> ・ <a href="/logout">ログアウト</a></p>
  <h1>{$name}</h1>

  <div class="share">
    <div class="qr">
      <img src="{$qrEsc}" alt="依頼リンクのQRコード" width="160" height="160">
      <a class="btn small" href="/admin/business/{$bid}/pop" target="_blank">POPを印刷</a>
    </div>
    <div class="share-link">
      <strong>お客様に渡す依頼リンク：</strong>
      <code id="reqlink">{$linkEsc}</code>
      <button class="btn small" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reqlink').innerText);this.innerText='コピーしました ✓'">リンクをコピー</button>
      <p><a class="btn small" href="/r/{$slug}" target="_blank">開いて確認</a></p>
      <p class="muted">レジ横にPOPを印刷して設置、または名刺・LINEでリンクを配布してください。</p>
    </div>
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

  <h2 class="no-print">店舗設定の変更</h2>
  <form class="form no-print" method="POST" action="/admin/business/{$bid}">
    <label>スラッグ（URL用）
      <input name="slug" value="{$slug}" placeholder="例：seitai-shibuya">
      <small class="muted">依頼リンクは /r/（入力値）。変更すると既存のQR・リンクは使えなくなります。半角英数とハイフンのみ。</small>
    </label>
    <label>Googleクチコミ投稿URL
      <input name="googleReviewUrl" value="{$url}" placeholder="https://search.google.com/local/writereview?placeid=...">
    </label>
    <label>Google誘導のしきい値
      <select name="threshold">
        <option value="5" {$sel5}>★5のみ</option>
        <option value="4" {$sel4}>★4以上</option>
        <option value="3" {$sel3}>★3以上</option>
      </select>
    </label>
    <label>運用モード
      <select name="mode">
        <option value="improve" {$selImprove}>改善フォーカス（低評価は非公開回収）</option>
        <option value="compliant" {$selCompliant}>コンプラ重視（全員にGoogle案内）</option>
      </select>
    </label>
    <button class="btn primary" type="submit">設定を保存</button>
  </form>
HTML;
    return layout($biz['name'] . ' ダッシュボード', $body);
}

function view_pop(array $biz, string $baseUrl): string
{
    $link = $baseUrl . '/r/' . $biz['slug'];
    $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&margin=12&data=' . rawurlencode($link);
    $name = esc($biz['name']);
    $qrEsc = esc($qr);
    return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$name} 口コミPOP</title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="pop">
  <p class="pop-store">{$name}</p>
  <h1 class="pop-head">クチコミに<br>ご協力ください 🙏</h1>
  <img class="pop-qr" src="{$qrEsc}" alt="口コミQRコード">
  <ol class="pop-steps">
    <li><span class="num">1</span>スマホのカメラでQRを読み取る</li>
    <li><span class="num">2</span>★で評価する</li>
    <li><span class="num">3</span>そのままGoogleに投稿</li>
  </ol>
  <p class="pop-foot">所要 約30秒 ／ アプリ不要</p>
</div>

<div class="staff-card">
  <p class="staff-title">【スタッフ用】会計時の声かけ例</p>
  <p class="staff-quote">「当院いまクチコミを集めていまして、もしよろしければ、こちらのQRから30秒ほどでご感想をいただけませんか？ 今後の励みになります🙏」</p>
  <ul class="staff-tips">
    <li>施術後〜会計の<strong>満足度が高い瞬間</strong>にひと声。</li>
    <li>お客様<strong>ご自身のスマホ</strong>で読み取ってもらう（Googleにそのまま投稿できます）。</li>
    <li>無理強いはしない。<strong>割引等と引き換えの依頼はNG</strong>（景品表示法）。</li>
  </ul>
</div>

<div class="pop-actions no-print">
  <button class="btn primary" type="button" onclick="window.print()">印刷する</button>
</div>
</body>
</html>
HTML;
}

/** お客様向けの軽量レイアウト（管理ヘッダ無し・クリーンなカード） */
function customer_layout(string $title, string $brand, string $body): string
{
    $t = esc($title);
    $b = esc($brand);
    return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$t}</title>
<link rel="stylesheet" href="/style.css">
</head>
<body class="cust">
<div class="cust-wrap">
  <div class="cust-card">
    <p class="cust-brand">★ {$b}</p>
    {$body}
  </div>
</div>
</body>
</html>
HTML;
}

/** Google公式風のGロゴ（インラインSVG） */
function google_g_svg(): string
{
    return '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
        . '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>'
        . '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
        . '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
        . '</svg>';
}

function view_rate(array $biz): string
{
    $slug = esc($biz['slug']);
    $buttons = '';
    foreach ([1, 2, 3, 4, 5] as $n) {
        $buttons .= '<button type="submit" name="rating" value="' . $n . '">'
            . '<span class="st">★</span><span class="stn">' . $n . '</span></button>';
    }
    $body = <<<HTML
  <p class="cust-hello">ご来店ありがとうございました！</p>
  <h1 class="cust-h">サービスはいかがでしたか？</h1>
  <p class="cust-sub">星をタップして評価してください</p>
  <form method="POST" action="/r/{$slug}" class="stars-pick">
    {$buttons}
  </form>
  <p class="cust-note">いただいたご意見は、サービス向上のために活用します。</p>
HTML;
    return customer_layout($biz['name'] . ' のご感想', $biz['name'], $body);
}

/** 評価後の結果画面（高評価→Google誘導 / 低評価→フィードバックへ） */
function view_rate_result(array $biz, int $rating, string $dest): string
{
    $slug = esc($biz['slug']);
    $url = esc($biz['google_review_url']);
    $show = '<div class="stars-show">' . esc(stars(5, $rating)) . '</div>';

    if ($dest === 'google') {
        $g = google_g_svg();
        $body = <<<HTML
  <h1 class="cust-h">ご評価ありがとうございました！</h1>
  {$show}
  <p class="cust-sub">素晴らしいご評価をありがとうございます！<br>Googleのクチコミ投稿にご協力ください。</p>
  <a class="google-btn" href="{$url}">{$g}<span>Googleクチコミを投稿する</span></a>
  <p style="margin-top:14px"><a class="cust-link" href="/r/{$slug}/done">後で投稿する</a></p>
HTML;
    } else {
        $body = <<<HTML
  <h1 class="cust-h">ご意見をお聞かせください</h1>
  {$show}
  <p class="cust-sub">より良いお店にするために、<br>率直なご意見をお聞かせください。</p>
  <a class="btn primary block" href="/r/{$slug}/feedback?rating={$rating}">フィードバックを送信する</a>
  <p style="margin-top:14px"><a class="cust-link" href="/r/{$slug}/done">後で送る</a></p>
HTML;
    }
    return customer_layout($biz['name'], $biz['name'], $body);
}

function view_feedback_form(array $biz, int $rating): string
{
    $slug = esc($biz['slug']);
    $body = <<<HTML
  <h1 class="cust-h">ご意見をお聞かせください</h1>
  <p class="cust-sub">ご記入いただいた内容は、店舗の改善のみに利用し、公開されることはありません。</p>
  <form method="POST" action="/r/{$slug}/feedback" class="form left">
    <input type="hidden" name="rating" value="{$rating}">
    <label>ご意見・ご感想（任意）
      <textarea name="message" rows="4" placeholder="ご自由にご記入ください"></textarea>
    </label>
    <label>ご連絡先（任意）
      <input name="contact" placeholder="メールアドレスなど">
    </label>
    <button class="btn primary block" type="submit">送信する</button>
  </form>
  <p style="margin-top:14px"><a class="cust-link" href="/r/{$slug}/done">スキップする</a></p>
HTML;
    return customer_layout('ご意見をお聞かせください', $biz['name'], $body);
}

function view_thanks(array $biz, string $kind): string
{
    $msg = $kind === 'private'
        ? 'ご意見は店舗の改善に役立たせていただきます。今後ともよろしくお願いいたします。'
        : 'ありがとうございました。今後ともよろしくお願いいたします。';
    $msg = esc($msg);
    $body = <<<HTML
  <div class="check-circle">✓</div>
  <h1 class="cust-h">ありがとうございました</h1>
  <p class="cust-sub">{$msg}</p>
  <p><a class="btn ghost" href="#" onclick="window.close();return false;">閉じる</a></p>
HTML;
    return customer_layout('ありがとうございました', $biz['name'], $body);
}

function view_not_found(): string
{
    return layout('見つかりません', '<h1>404</h1><p>ページが見つかりません。</p><p><a href="/">トップへ</a></p>');
}
