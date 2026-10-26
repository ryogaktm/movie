<?php
// 再生位置の取得・保存（ログインユーザーごと）
//   GET  progress.php?f=名前  → {"t":秒,"d":長さ,"done":bool,"at":時刻} または null
//   POST JSON {csrf, f, t, d, done}
require __DIR__ . '/lib.php';
require_login(true);

$user = (string)$_SESSION['user'];
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 8192), true);
    if (!is_array($in)) { http_response_code(400); exit('{"ok":false}'); }
    check_csrf($in['csrf'] ?? null);
    session_write_close();

    $name = (string)($in['f'] ?? '');
    if (!video_path($name)) { http_response_code(404); exit('{"ok":false}'); }

    $t = max(0, (float)($in['t'] ?? 0));
    $d = max(0, (float)($in['d'] ?? 0));
    $done = !empty($in['done']);
    progress_save($user, $name, [
        't'    => $done ? 0 : round($t, 1),
        'd'    => round($d, 1),
        'done' => $done,
        'at'   => time(),
    ]);
    echo '{"ok":true}';
    exit;
}

session_write_close();
$all = progress_load($user);
if (isset($_GET['f'])) {
    echo json_encode($all[(string)$_GET['f']] ?? null);
} else {
    echo json_encode((object)$all, JSON_UNESCAPED_UNICODE);
}
