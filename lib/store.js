'use strict';

/**
 * 超軽量JSONファイルストア。
 * 依存ゼロでMVPを動かすための最小実装。
 * 将来 SQLite / PostgreSQL に差し替えやすいよう、アクセスは本モジュール経由に集約する。
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const DATA_DIR = path.join(__dirname, '..', 'data');
const DB_FILE = path.join(DATA_DIR, 'db.json');

const DEFAULT_DB = {
  businesses: [], // { id, slug, name, googleReviewUrl, threshold, mode, createdAt }
  ratings: [],    // { id, businessId, rating, routedTo, createdAt }
  feedback: [],   // { id, businessId, rating, message, contact, createdAt }
};

function ensureFile() {
  if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });
  if (!fs.existsSync(DB_FILE)) {
    fs.writeFileSync(DB_FILE, JSON.stringify(DEFAULT_DB, null, 2));
  }
}

function read() {
  ensureFile();
  try {
    const raw = fs.readFileSync(DB_FILE, 'utf8');
    const db = JSON.parse(raw);
    // 後方互換：欠けているコレクションを補完
    return { ...DEFAULT_DB, ...db };
  } catch (e) {
    return { ...DEFAULT_DB };
  }
}

function write(db) {
  ensureFile();
  fs.writeFileSync(DB_FILE, JSON.stringify(db, null, 2));
}

function id() {
  return crypto.randomBytes(8).toString('hex');
}

function slugify(name) {
  const base = String(name)
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9぀-ヿ一-龯]+/g, '-')
    .replace(/^-+|-+$/g, '');
  const suffix = crypto.randomBytes(2).toString('hex');
  return `${base || 'shop'}-${suffix}`;
}

// ---- businesses ----

function listBusinesses() {
  return read().businesses;
}

function getBusinessBySlug(slug) {
  return read().businesses.find((b) => b.slug === slug) || null;
}

function getBusinessById(bid) {
  return read().businesses.find((b) => b.id === bid) || null;
}

function createBusiness({ name, googleReviewUrl, threshold, mode }) {
  const db = read();
  const biz = {
    id: id(),
    slug: slugify(name),
    name: String(name).trim(),
    googleReviewUrl: String(googleReviewUrl || '').trim(),
    threshold: Number(threshold) || 4,
    mode: mode === 'compliant' ? 'compliant' : 'improve', // 'compliant' | 'improve'
    createdAt: new Date().toISOString(),
  };
  db.businesses.push(biz);
  write(db);
  return biz;
}

function updateBusiness(bid, patch) {
  const db = read();
  const idx = db.businesses.findIndex((b) => b.id === bid);
  if (idx === -1) return null;
  db.businesses[idx] = { ...db.businesses[idx], ...patch };
  write(db);
  return db.businesses[idx];
}

// ---- ratings ----

function recordRating({ businessId, rating, routedTo }) {
  const db = read();
  const rec = {
    id: id(),
    businessId,
    rating: Number(rating),
    routedTo, // 'google' | 'private'
    createdAt: new Date().toISOString(),
  };
  db.ratings.push(rec);
  write(db);
  return rec;
}

// ---- feedback ----

function recordFeedback({ businessId, rating, message, contact }) {
  const db = read();
  const rec = {
    id: id(),
    businessId,
    rating: Number(rating),
    message: String(message || '').trim(),
    contact: String(contact || '').trim(),
    createdAt: new Date().toISOString(),
  };
  db.feedback.push(rec);
  write(db);
  return rec;
}

function statsFor(businessId) {
  const db = read();
  const ratings = db.ratings.filter((r) => r.businessId === businessId);
  const feedback = db.feedback.filter((f) => f.businessId === businessId);
  const total = ratings.length;
  const avg = total ? ratings.reduce((s, r) => s + r.rating, 0) / total : 0;
  const toGoogle = ratings.filter((r) => r.routedTo === 'google').length;
  const toPrivate = ratings.filter((r) => r.routedTo === 'private').length;
  return {
    total,
    avg: Math.round(avg * 100) / 100,
    toGoogle,
    toPrivate,
    feedback: feedback.sort((a, b) => b.createdAt.localeCompare(a.createdAt)),
  };
}

function seedIfEmpty() {
  const db = read();
  if (db.businesses.length === 0) {
    createBusiness({
      name: 'デモ整体院',
      googleReviewUrl: 'https://search.google.com/local/writereview?placeid=DEMO',
      threshold: 4,
      mode: 'improve',
    });
  }
}

module.exports = {
  listBusinesses,
  getBusinessBySlug,
  getBusinessById,
  createBusiness,
  updateBusiness,
  recordRating,
  recordFeedback,
  statsFor,
  seedIfEmpty,
};
