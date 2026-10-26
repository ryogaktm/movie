<?php
// 動画の形式を調べる（再生できない時の原因表示用。サーバーに ffprobe が無ければ null）
require __DIR__ . '/lib.php';
require_login(true);
session_write_close();
header('Content-Type: application/json; charset=utf-8');

$path = video_path((string)($_GET['f'] ?? ''));
$fp = cfg('ffprobe');
if (!$path || !$fp || !function_exists('exec')
    || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
    exit('null');
}

$cmd = escapeshellarg($fp) . ' -v error -show_entries stream=codec_type,codec_name,profile,pix_fmt -of json '
     . escapeshellarg($path) . ' 2>/dev/null';
@exec($cmd, $out, $code);
$j = json_decode(implode("\n", $out), true);
if ($code !== 0 || !is_array($j)) exit('null');

$info = ['video' => null, 'profile' => null, 'pix_fmt' => null, 'audio' => null];
foreach ($j['streams'] ?? [] as $s) {
    if (($s['codec_type'] ?? '') === 'video' && !$info['video']) {
        $info['video'] = $s['codec_name'] ?? null;
        $info['profile'] = $s['profile'] ?? null;
        $info['pix_fmt'] = $s['pix_fmt'] ?? null;
    }
    if (($s['codec_type'] ?? '') === 'audio' && !$info['audio']) $info['audio'] = $s['codec_name'] ?? null;
}
echo json_encode($info);
