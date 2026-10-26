<?php
require __DIR__ . '/lib.php';
start_session();
if (!cfg('users')) { header('Location: setup.php'); exit; } // 未設定ならブラウザで初期設定
if (is_logged_in()) { header('Location: index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($_POST['csrf'] ?? null);
    $u  = (string)($_POST['user'] ?? '');
    $pw = (string)($_POST['pass'] ?? '');
    $users = cfg('users');
    if (isset($users[$u]) && verify_password($pw, (string)$users[$u])) {
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        header('Location: index.php');
        exit;
    }
    sleep(1); // 総当たり対策の簡易ウェイト
    $err = 'ユーザー名またはパスワードが違います';
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ログイン - <?= h(cfg('site_title')) ?></title>
<style>
  :root{--bg:#0f1115;--panel:#181b22;--line:#2a2f3a;--text:#e8eaf0;--sub:#8b92a3;--accent:#4f8cff;--err:#ff6b6b}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,"Hiragino Sans","Yu Gothic UI",sans-serif;padding:16px}
  form{width:100%;max-width:340px;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:28px}
  h1{font-size:20px;margin:0 0 20px;text-align:center}
  label{display:block;font-size:13px;color:var(--sub);margin:14px 0 6px}
  input{width:100%;padding:11px 12px;border-radius:8px;border:1px solid var(--line);background:#0f1115;color:var(--text);font-size:15px}
  input:focus{outline:none;border-color:var(--accent)}
  button{width:100%;margin-top:22px;padding:12px;border:0;border-radius:8px;background:var(--accent);color:#fff;font-size:15px;font-weight:600;cursor:pointer}
  .err{color:var(--err);font-size:13px;margin-top:14px;text-align:center}
</style>
</head>
<body>
<form method="post" autocomplete="on">
  <h1>🎬 <?= h(cfg('site_title')) ?></h1>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <label for="user">ユーザー名</label>
  <input id="user" name="user" required autofocus autocomplete="username">
  <label for="pass">パスワード</label>
  <input id="pass" name="pass" type="password" required autocomplete="current-password">
  <button type="submit">ログイン</button>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
</form>
</body>
</html>
