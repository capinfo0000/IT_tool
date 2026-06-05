# デプロイ手順：Vercel（PHP）

> ⚠️ Vercelは本来Node/JS向け。本アプリ(PHP)はコミュニティ製ランタイム `vercel-php` で動かす。
> **SQLiteは使えない**ため、別途**外部MySQL（無料枠）**が必要。初回はビルド調整が要ることがある。

---

## 構成（このリポジトリに同梱済み）
- `vercel.json`：`public/index.php` を `vercel-php` で実行、全リクエストをそこへ集約。`src/**` を同梱。
- 設定は **config.php ではなく環境変数**から読む（`src/db.php` の `config()` が `KB_*` を参照）。

## Step 1. 無料のMySQLを用意（必須）
サーバーレスは外部DBが必要。MySQL互換の無料枠の例：
- **TiDB Cloud Serverless**（MySQL互換・無料枠）
- **Aiven for MySQL**（無料トライアル）/ **Railway** / その他マネージドMySQL

作成後、**ホスト・DB名・ユーザー・パスワード**を控える（SSL必須の場合あり→その際は要追加対応）。

## Step 2. Vercelでリポジトリをimport
1. Vercelにログイン →「Add New… → Project」
2. GitHubの `capinfo0000/it_tool` を選択 → **Branch を `claude/loving-dijkstra-FrTGt`**（または main にマージ後 main）
3. Framework Preset：**Other**（自動でOK）。Build/Output設定は不要（vercel.jsonが制御）。

## Step 3. 環境変数を設定（Project → Settings → Environment Variables）
| 変数 | 値 |
|---|---|
| `KB_ADMIN_PASSWORD` | 管理画面の強いパスワード |
| `KB_DB_DRIVER` | `mysql` |
| `KB_DB_HOST` | （Step1のホスト） |
| `KB_DB_NAME` | （DB名） |
| `KB_DB_USER` | （ユーザー） |
| `KB_DB_PASS` | （パスワード） |

## Step 4. デプロイ＆確認
- 「Deploy」→ 数分でURL発行（`https://xxxx.vercel.app`）。
- `/` 表示、`/admin`（KB_ADMIN_PASSWORDでログイン）、店舗登録 → 本物のGoogleレビューURLを設定。

## 既知の注意点（正直に）
1. **`vercel-php` のランタイム版**：`vercel.json` の `vercel-php@0.7.4` でビルド失敗する場合、[vercel-php のReleases](https://github.com/vercel-community/php/releases) の最新版番号に書き換える。
2. **管理ログインのセッション**：サーバーレスはインスタンスをまたぐとセッションが切れることがある（再ログインが必要になる場合）。本格運用では要対策。
3. **DBのSSL**：外部MySQLがSSL必須だとPDO DSNにSSL指定が必要（その場合は `db.php` に数行追加。連絡ください）。
4. 私（開発側）はVercelへ直接デプロイ・テストできないため、**初回ビルドのログを見ながら微調整**します。エラーが出たらビルドログを共有してください。

## 代替（PHPがそのまま動く方が楽な場合）
- **コアサーバーV2**（PHP+MySQLネイティブ／手順は [デプロイ手順_コアサーバーV2.md](デプロイ手順_コアサーバーV2.md)）。Vercelより素直に動きます。
