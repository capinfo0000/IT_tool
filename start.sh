#!/usr/bin/env bash
# クチコミブースト ローカル起動スクリプト
# 使い方: ./start.sh   → ブラウザで http://localhost:8080/ を開く
set -e
cd "$(dirname "$0")"

PORT="${PORT:-8080}"

# 設定が無ければサンプル(SQLite)で動くので準備不要。
if ! command -v php >/dev/null 2>&1; then
  echo "PHP が見つかりません。先に PHP 8 をインストールしてください（Mac: brew install php / Windows: 公式zip）。"
  exit 1
fi

echo "------------------------------------------------------------"
echo " クチコミブースト を起動します"
echo "   トップ        : http://localhost:${PORT}/"
echo "   管理画面      : http://localhost:${PORT}/admin"
echo "   ログインPW    : src/config.php の admin_password"
echo "                   (未作成なら src/config.sample.php の 'change-me-please')"
echo "   停止          : Ctrl + C"
echo "------------------------------------------------------------"

exec php -S 127.0.0.1:"${PORT}" -t public public/index.php
