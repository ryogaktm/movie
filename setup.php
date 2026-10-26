<?php
// 初期設定（config.php が無い / ユーザー未設定のときだけ使える）
require __DIR__ . '/lib.php';
start_session();

$configFile = __DIR__ . '/config.php';

// 設定済みなら使えない（他人に上書きされないように）
if (cfg('users')) {
    header('Location: login.php');
    exit;
}

$err = '';
$manual = '';   // 書き込めなかった時に手動で貼り付けてもらう内容
$form = ['user' => '', 'title' => cfg('site_title')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($_POST['csrf'] ?? null);
    $form['user']  = trim((string)($_POST['user'] ?? ''));
    $form['title'] = trim((string)($_POST['title'] ?? '')) ?: 'Movie Library';
    $pass  = (string)($_POST['pass'] ?? '');
    $pass2 = (string)($_POST['pass2'] ?? '');

    if ($form['user'] === '' || mb_strlen($form['user']) > 50) {
        $err = 'ユーザー名を入力してください（50文字以内）';
    } elseif (strlen($pass) < 6) {
        $err = 'パスワードは6文字以上にしてください';
    } elseif ($pass !== $pass2) {
        $err = 'パスワード（確認）が一致しません';
    } else {
        $config = [
            'users'      => [$form['user'] => password_hash($pass, PASSWORD_DEFAULT)],
            'site_title' => $form['title'],
        ];
        $php = "<?php\n"
             . "// 設定ファイル（setup.php で作成 " . date('Y-m-d H:i') . "）\n"
             . "// ユーザーを追加する場合: 'ユーザー名' => 'パスワード'（平文可）または password_hash() のハッシュ\n"
             . "// その他の設定項目は config.sample.php を参照\n"
             . "return " . var_export($config, true) . ";\n";

        // 'x' = 既にファイルがあれば失敗（同時アクセスでの上書き防止）
        $fp = is_file($configFile) ? false : @fopen($configFile, 'x');
        if ($fp) {
            fwrite($fp, $php);
            fclose($fp);
            @chmod($configFile, 0644); // FTPでも編集できるように（ブラウザからは .htaccess で遮断）
            session_regenerate_id(true);
            $_SESSION['user'] = $form['user'];
            header('Location: index.php');
            exit;
        }
        if (is_file($configFile)) {
            $err = 'config.php は既にありますが、ユーザーが設定されていません。config.php を削除してからやり直すか、下の内容で書き換えてください。';
        } else {
            $err = 'config.php を書き込めませんでした（フォルダの書き込み権限を確認してください）。下の内容で config.php を作成しても設定できます。';
        }
        $manual = $php;
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>初期設定 - Movie Library</title>
<style>
  :root{--bg:#0f1115;--panel:#181b22;--line:#2a2f3a;--text:#e8eaf0;--sub:#8b92a3;--accent:#4f8cff;--err:#ff6b6b}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,"Hiragino Sans","Yu Gothic UI",sans-serif;padding:16px}
  form{width:100%;max-width:400px;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:28px}
  h1{font-size:20px;margin:0 0 6px;text-align:center}
  .lead{font-size:13px;color:var(--sub);text-align:center;margin:0 0 10px;line-height:1.6}
  label{display:block;font-size:13px;color:var(--sub);margin:14px 0 6px}
  input{width:100%;padding:11px 12px;border-radius:8px;border:1px solid var(--line);background:#0f1115;color:var(--text);font-size:15px}
  input:focus{outline:none;border-color:var(--accent)}
  button{width:100%;margin-top:22px;padding:12px;border:0;border-radius:8px;background:var(--accent);color:#fff;font-size:15px;font-weight:600;cursor:pointer}
  .err{color:var(--err);font-size:13px;margin-top:14px;line-height:1.6}
  textarea{width:100%;height:170px;margin-top:10px;background:#0f1115;color:var(--text);border:1px solid var(--line);border-radius:8px;padding:10px;font:12px/1.5 ui-monospace,Menlo,Consolas,monospace}
</style>
</head>
<body>
<form method="post" autocomplete="off">
  <h1>🎬 初期設定</h1>
  <p class="lead">ログインに使うユーザーを作成します。<br>作成後はこの画面は使えなくなります。</p>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label for="title">サイト名</label>
  <input id="title" name="title" value="<?= h($form['title']) ?>">
  <label for="user">ユーザー名</label>
  <input id="user" name="user" required autofocus value="<?= h($form['user']) ?>" autocomplete="username">
  <label for="pass">パスワード（6文字以上）</label>
  <input id="pass" name="pass" type="password" required minlength="6" autocomplete="new-password">
  <label for="pass2">パスワード（確認）</label>
  <input id="pass2" name="pass2" type="password" required minlength="6" autocomplete="new-password">
  <button type="submit">設定して始める</button>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <?php if ($manual): ?><textarea readonly onclick="this.select()"><?= h($manual) ?></textarea><?php endif; ?>
</form>
</body>
</html>
