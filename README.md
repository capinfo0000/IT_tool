# クチコミブースト（Kuchikomi Boost）

個人店（整体・美容・士業・教室など）向けの **Google口コミ自動収集ツール** のMVPです。

QR/リンクを配るだけで、満足したお客様を **Googleクチコミ投稿へスムーズに誘導**。
不満は **店内フィードバックとして非公開で受け取り**、改善に活かせます。

📄 企画・市場分析・競合優位性・収益試算は [`docs/企画書.md`](docs/企画書.md) を参照してください。
📋 機能の一覧は [`docs/機能要件.md`](docs/機能要件.md) を参照してください。

---

## 技術スタック

**PHP + MySQL**（レンタルサーバ「コアサーバー V2」での運用を想定）。
データアクセスはPDOで抽象化しており、**本番=MySQL / ローカル=SQLite** を同一コードで動かせます。

```
public/
  index.php      フロントコントローラ（ルーティング）
  .htaccess      実在ファイル以外を index.php へ（LiteSpeed/Apache）
  style.css      スタイル
src/
  db.php         データアクセス層（PDO、MySQL/SQLite両対応）
  views.php      画面（HTML生成）
  config.sample.php  設定サンプル（コピーして src/config.php を作成）
data/            SQLite等のローカルデータ（gitignore）
docs/企画書.md    市場分析・競合優位性・収益試算・ロードマップ
```

## ローカルでの動かし方

PHP 8 系（`pdo_sqlite` 有効）があれば、設定なしでそのまま起動できます（SQLite）。

**かんたん起動（推奨）**
```bash
./start.sh        # Mac/Linux（ダブルクリックでも可）
start.bat         # Windows（ダブルクリック）
```
→ ブラウザで **http://localhost:8080/** を開く（管理画面は `/admin`）。停止は Ctrl+C。

**手動で起動する場合**
```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

1. `http://localhost:8080/` を開く
2. 「店舗を新規登録」から店名・Googleクチコミ投稿URLを登録
3. 発行された依頼リンク `/r/{slug}` をお客様へ
4. ダッシュボードで結果を確認（ログインPWは `src/config.php` の `admin_password`、未作成時はサンプルの `change-me-please`）

> ⚠️ `http://localhost:8080/` は **このスクリプトを実行したPC上**で開けます（クラウド側で動かしても手元のブラウザからは見えません）。

## コアサーバー V2 への配置（本番）

1. `src/config.sample.php` を `src/config.php` にコピーし、`driver` を `mysql` にしてMySQL接続情報を記入。
2. サーバ上に **`src/` と公開ディレクトリ（public_html）を同じ階層**に置き、`public/` の中身を `public_html/` に配置（`index.php` は `../src/` を参照）。
3. MySQL（コントロールパネルで作成）を用意。テーブルは初回アクセス時に自動作成されます。
4. ブラウザでアクセスして動作確認。

> 補足：コアサーバーV2はPHP+MySQLの共有レンタルサーバ（LiteSpeed）。本ツールはこの構成に最適化しています。

## 注意（コンプライアンス）

Googleは「レビューゲーティング」を禁止しています。低評価を非公開へ振り分ける「改善フォーカスモード」は運用次第でポリシー違反になり得ます。既定の **コンプライアンス重視モード** を推奨します。詳細は企画書のコンプライアンス節を参照。

## 今後の拡張（ロードマップ）

- ログイン認証、Stripeによるサブスク課金
- 予約/POS連携での自動依頼送信、AI返信文生成、業種ベンチマーク
- QR画像生成、メール/LINE送信
