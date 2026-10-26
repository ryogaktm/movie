<?php
// ログイン済みユーザーにだけ動画を配信（シーク用の Range リクエスト対応）
require __DIR__ . '/lib.php';

$name = (string)($_GET['f'] ?? '');
// ログイン中（Cookie）か、署名付きURLが有効なら再生OK
if (!check_stream_token($name) && !is_logged_in()) {
    http_response_code(403);
    exit('Forbidden');
}

$path = video_path($name);
if (!$path) { http_response_code(404); exit('Not found'); }

session_write_close(); // 再生中も他のページを操作できるようにセッションロックを解放

$size  = filesize($path);
$start = 0;
$end   = $size - 1;

header('Content-Type: ' . VIDEO_EXT[video_ext($path)]);
header('Accept-Ranges: bytes');
header('Content-Disposition: inline');
header('Cache-Control: private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');

if (isset($_SERVER['HTTP_RANGE'])) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m) || ($m[1] === '' && $m[2] === '')) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    if ($m[1] === '') {                 // bytes=-500（末尾から）
        $start = max(0, $size - (int)$m[2]);
    } else {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

header('Content-Length: ' . ($end - $start + 1));
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;

while (ob_get_level()) ob_end_clean();
@set_time_limit(0);

$fp = fopen($path, 'rb');
fseek($fp, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fp) && !connection_aborted()) {
    $chunk = fread($fp, (int)min(262144, $left));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    flush();
    $left -= strlen($chunk);
}
fclose($fp);
