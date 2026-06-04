<?php
/**
 * 設定ファイルのサンプル。
 * 本番（コアサーバーV2）では src/config.php としてコピーし、driver を 'mysql' にして
 * MySQL 接続情報を記入する。config.php が無ければ本サンプル（SQLite）が使われ、
 * ローカルでもそのまま動く。
 */
return [
    // 'sqlite'（ローカル開発） または 'mysql'（コアサーバーV2 本番）
    'driver' => 'sqlite',

    // SQLite を使う場合の保存先
    'sqlite_path' => __DIR__ . '/../data/db.sqlite',

    // MySQL（コアサーバーV2）を使う場合の接続情報
    'mysql' => [
        'host'    => 'localhost',
        'dbname'  => 'your_db_name',
        'user'    => 'your_db_user',
        'pass'    => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
];
