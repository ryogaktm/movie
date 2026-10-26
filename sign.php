<?php
// 再生用の署名付きURLを発行し直す（再生エラー時の再接続用）
require __DIR__ . '/lib.php';
require_login(true);
$user = (string)$_SESSION['user'];
session_write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$name = (string)($_GET['f'] ?? '');
if (!video_path($name)) { http_response_code(404); exit('null'); }
echo json_encode(['src' => signed_stream_url($name, $user)]);
