<?php
// ===== 設定ファイルの見本 =====
// このファイルを config.php という名前でコピーして使ってください。
// 配布するアップデートには config.php は含まれないので、上書きでログイン情報が消えることはありません。
// 書いていない項目は初期値（lib.php）が使われます。
return [
    // ログインユーザー（ユーザー名 => パスワード）
    // 平文でも動きますが、password_hash() で作ったハッシュ（$2y$... で始まる文字列）を推奨
    //   例: php -r "echo password_hash('mypassword', PASSWORD_DEFAULT);"
    'users' => [
        'admin' => 'change-me',
    ],

    // 動画フォルダ（公開ディレクトリの外に置くと最も安全。例: '/home/USER/movie'）
    'movie_dir' => __DIR__ . '/movie',

    // サムネイル保存先
    'thumb_dir' => __DIR__ . '/thumbs',

    // 視聴履歴（再生位置）の保存先。ユーザーごとに保存され、どの端末からでも共通
    'data_dir' => __DIR__ . '/data',

    // ffmpeg のパス（サーバーに無い場合は null。その場合はブラウザ側で自動生成します）
    'ffmpeg' => 'ffmpeg',

    // サムネイルを切り出す位置（秒）
    'thumb_at' => 5,

    'site_title' => 'Movie Library',
];
