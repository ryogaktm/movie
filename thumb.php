<?php
// サムネイル
//   GET  : あれば返す。無ければ ffmpeg で生成を試み、ダメなら 404（→ブラウザ側で生成）
//   POST : ブラウザが動画から切り出した JPEG を保存
require __DIR__ . '/lib.php';
require_login(true);

$path = video_path((string)($_GET['f'] ?? ''));
if (!$path) { http_response_code(404); exit; }

$tf  = thumb_file($path);
$dir = cfg('thumb_dir');
if (!is_dir($dir)) @mkdir($dir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    session_write_close();
    $data = file_get_contents('php://input', false, null, 0, 3 * 1024 * 1024);
    $info = $data ? @getimagesizefromstring($data) : false;
    if (!$info || $info[2] !== IMAGETYPE_JPEG) { http_response_code(400); exit('Invalid image'); }
    file_put_contents($tf, $data, LOCK_EX);
    header('Content-Type: application/json');
    echo '{"ok":true}';
    exit;
}

session_write_close();

if (!is_file($tf)) try_ffmpeg($path, $tf);
if (!is_file($tf)) { http_response_code(404); exit; }

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($tf));
header('Cache-Control: private, max-age=604800');
readfile($tf);

function try_ffmpeg(string $src, string $dst): void
{
    $ff = cfg('ffmpeg');
    if (!$ff || !function_exists('exec')) return;
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (in_array('exec', $disabled, true)) return;

    // 指定秒で切り出し → 短い動画などで失敗したら先頭フレーム
    foreach ([(int)cfg('thumb_at'), 0] as $sec) {
        $cmd = escapeshellarg($ff) . ' -y -loglevel error -ss ' . $sec
             . ' -i ' . escapeshellarg($src)
             . ' -frames:v 1 -vf scale=480:-2 -q:v 4 '
             . escapeshellarg($dst) . ' 2>&1';
        @exec($cmd, $o, $code);
        if ($code === 0 && is_file($dst) && filesize($dst) > 0) return;
        @unlink($dst);
    }
}
