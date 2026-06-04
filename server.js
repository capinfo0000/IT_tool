'use strict';

/**
 * クチコミブースト MVP サーバ
 * 依存ライブラリゼロ（Node.js標準モジュールのみ）。
 *   起動: npm start   （既定 http://localhost:3000）
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

const store = require('./lib/store');
const views = require('./lib/views');

const PORT = process.env.PORT || 3000;

function send(res, status, html) {
  res.writeHead(status, { 'Content-Type': 'text/html; charset=utf-8' });
  res.end(html);
}

function redirect(res, location) {
  res.writeHead(302, { Location: location });
  res.end();
}

function serveStatic(res, name) {
  const file = path.join(__dirname, 'public', name);
  if (!fs.existsSync(file) || !file.startsWith(path.join(__dirname, 'public'))) {
    return send(res, 404, views.notFound());
  }
  const ext = path.extname(file);
  const types = { '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8' };
  res.writeHead(200, { 'Content-Type': types[ext] || 'application/octet-stream' });
  res.end(fs.readFileSync(file));
}

function parseBody(req) {
  return new Promise((resolve) => {
    let data = '';
    req.on('data', (c) => {
      data += c;
      if (data.length > 1e6) req.destroy(); // 過大なボディを拒否
    });
    req.on('end', () => {
      const params = new URLSearchParams(data);
      const obj = {};
      for (const [k, v] of params) obj[k] = v;
      resolve(obj);
    });
  });
}

function baseUrlOf(req) {
  const host = req.headers.host || `localhost:${PORT}`;
  const proto = req.headers['x-forwarded-proto'] || 'http';
  return `${proto}://${host}`;
}

const server = http.createServer(async (req, res) => {
  try {
    const u = new URL(req.url, baseUrlOf(req));
    const pathname = u.pathname;
    const method = req.method;

    // 静的ファイル
    if (pathname.startsWith('/static/')) {
      return serveStatic(res, pathname.replace('/static/', ''));
    }

    // トップ
    if (pathname === '/' && method === 'GET') {
      return send(res, 200, views.home());
    }

    // 管理：店舗一覧
    if (pathname === '/admin' && method === 'GET') {
      return send(res, 200, views.admin(store.listBusinesses(), baseUrlOf(req)));
    }

    // 管理：店舗作成
    if (pathname === '/admin/business' && method === 'POST') {
      const body = await parseBody(req);
      if (!body.name || !body.name.trim()) {
        return send(res, 400, views.admin(store.listBusinesses(), baseUrlOf(req)));
      }
      store.createBusiness(body);
      return redirect(res, '/admin');
    }

    // 管理：店舗ダッシュボード
    const dashMatch = pathname.match(/^\/admin\/business\/([a-z0-9]+)$/i);
    if (dashMatch && method === 'GET') {
      const biz = store.getBusinessById(dashMatch[1]);
      if (!biz) return send(res, 404, views.notFound());
      return send(res, 200, views.dashboard(biz, store.statsFor(biz.id), baseUrlOf(req)));
    }

    // 顧客：評価ページ
    const rateMatch = pathname.match(/^\/r\/([^/]+)$/);
    if (rateMatch && method === 'GET') {
      const biz = store.getBusinessBySlug(decodeURIComponent(rateMatch[1]));
      if (!biz) return send(res, 404, views.notFound());
      return send(res, 200, views.ratePage(biz));
    }

    // 顧客：評価送信 → スマート導線
    if (rateMatch && method === 'POST') {
      const biz = store.getBusinessBySlug(decodeURIComponent(rateMatch[1]));
      if (!biz) return send(res, 404, views.notFound());
      const body = await parseBody(req);
      const rating = Math.max(1, Math.min(5, Number(body.rating) || 0));

      if (biz.mode === 'compliant') {
        // コンプラ重視：全員Googleへ案内（URLがあれば）
        store.recordRating({ businessId: biz.id, rating, routedTo: 'google' });
        if (biz.googleReviewUrl) return redirect(res, biz.googleReviewUrl);
        return send(res, 200, views.thanks('public'));
      }

      // 改善フォーカス：しきい値で振り分け
      if (rating >= biz.threshold) {
        store.recordRating({ businessId: biz.id, rating, routedTo: 'google' });
        if (biz.googleReviewUrl) return redirect(res, biz.googleReviewUrl);
        return send(res, 200, views.thanks('public'));
      }
      store.recordRating({ businessId: biz.id, rating, routedTo: 'private' });
      return send(res, 200, views.feedbackForm(biz, rating));
    }

    // 顧客：非公開フィードバック送信
    const fbMatch = pathname.match(/^\/r\/([^/]+)\/feedback$/);
    if (fbMatch && method === 'POST') {
      const biz = store.getBusinessBySlug(decodeURIComponent(fbMatch[1]));
      if (!biz) return send(res, 404, views.notFound());
      const body = await parseBody(req);
      store.recordFeedback({
        businessId: biz.id,
        rating: body.rating,
        message: body.message,
        contact: body.contact,
      });
      return send(res, 200, views.thanks('private'));
    }

    return send(res, 404, views.notFound());
  } catch (err) {
    console.error(err);
    send(res, 500, views.layout('エラー', '<h1>500</h1><p>サーバエラーが発生しました。</p>'));
  }
});

store.seedIfEmpty();
server.listen(PORT, () => {
  console.log(`クチコミブースト MVP → http://localhost:${PORT}`);
});

module.exports = server;
