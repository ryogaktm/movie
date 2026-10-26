<?php
declare(strict_types=1);

// 初期値（config.php に書いた項目だけが上書きされる。新しい設定項目が増えても config.php の書き換えは不要）
$GLOBALS['CONFIG'] = [
    'users'      => [],
    'movie_dir'  => __DIR__ . '/movie',
    'thumb_dir'  => __DIR__ . '/thumbs',
    'data_dir'   => __DIR__ . '/data',
    'ffmpeg'     => 'ffmpeg',
    'ffprobe'    => 'ffprobe',
    'login_days' => 60,
    'thumb_at'   => 5,
    'site_title' => 'Movie Library',
];
if (is_file(__DIR__ . '/config.php')) {
    $GLOBALS['CONFIG'] = array_merge($GLOBALS['CONFIG'], (array)require __DIR__ . '/config.php');
}

const VIDEO_EXT = ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];

function cfg(string $k)
{
    return $GLOBALS['CONFIG'][$k] ?? null;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * セッション開始。ログイン状態を login_days 日間保持し、アクセスするたびに期限を延長する。
 * （PHP標準だとセッションは約24分で消え、Cookieもブラウザを閉じると消えるため）
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $lifetime = max(1, (int)cfg('login_days')) * 86400;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    // 共用サーバーの /tmp だと他のサイトの設定で掃除されるので、専用フォルダに保存
    $dir = cfg('data_dir') . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', (string)$lifetime);

    $cookie = ['lifetime' => $lifetime, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'];
    session_name('MOVIESESSID');
    session_set_cookie_params($cookie);
    session_start();

    // ログイン中ならCookieの期限を延長（使っている限りログアウトされない）
    if (!empty($_SESSION['user']) && !headers_sent()) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + $lifetime, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}

// ---------- 署名付き動画URL ----------
// テレビのブラウザなど、動画の取得時にCookieを送らない端末でも再生できるように、
// URL自体にログイン済みの証明（有効期限付き）を付ける。
function app_secret(): string
{
    $f = cfg('data_dir') . DIRECTORY_SEPARATOR . 'secret.key';
    if (!is_file($f)) {
        @mkdir(dirname($f), 0755, true);
        file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
    }
    return trim((string)file_get_contents($f));
}

function stream_sig(string $name, string $user, int $exp): string
{
    return hash_hmac('sha256', $name . "\n" . $user . "\n" . $exp, app_secret());
}

function signed_stream_url(string $name, string $user): string
{
    $exp = time() + 86400; // 24時間有効
    return 'stream.php?' . http_build_query(
        ['f' => $name, 'u' => $user, 'e' => $exp, 's' => stream_sig($name, $user, $exp)],
        '', '&', PHP_QUERY_RFC3986
    );
}

function check_stream_token(string $name): bool
{
    $u = (string)($_GET['u'] ?? '');
    $e = (int)($_GET['e'] ?? 0);
    $s = (string)($_GET['s'] ?? '');
    $users = cfg('users');
    if ($u === '' || $e < time() || !isset($users[$u])) return false;
    return hash_equals(stream_sig($name, $u, $e), $s);
}

function is_logged_in(): bool
{
    start_session();
    return !empty($_SESSION['user']);
}

/** 未ログインなら、ページはログイン画面へ、API（動画・画像）は 403 */
function require_login(bool $api = false): void
{
    if (is_logged_in()) return;
    if ($api) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Location: login.php');
    exit;
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function check_csrf(?string $t): void
{
    start_session();
    if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(400);
        exit('Bad token');
    }
}

function verify_password(string $input, string $stored): bool
{
    if (preg_match('/^\$(2y|argon2)/', $stored)) return password_verify($input, $stored);
    return hash_equals($stored, $input);
}

function video_ext(string $name): string
{
    return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

/** movie フォルダ内の動画一覧（新しい順） */
function video_list(): array
{
    $dir = cfg('movie_dir');
    $out = [];
    foreach (@scandir($dir) ?: [] as $f) {
        if ($f === '' || $f[0] === '.') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        if (!is_file($p) || !isset(VIDEO_EXT[video_ext($f)])) continue;
        $out[] = ['name' => $f, 'size' => filesize($p), 'mtime' => filemtime($p)];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

/** ファイル名から安全に実パスを得る（フォルダ外へのアクセスを防止） */
function video_path(string $name): ?string
{
    if ($name === '' || $name[0] === '.' || preg_match('#[/\\\\\x00]#', $name)) return null;
    if (!isset(VIDEO_EXT[video_ext($name)])) return null;
    $dir = realpath(cfg('movie_dir'));
    if (!$dir) return null;
    $p = realpath($dir . DIRECTORY_SEPARATOR . $name);
    if (!$p || strpos($p, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($p)) return null;
    return $p;
}

/** 動画ごとのサムネイルファイル名（動画が差し替わったら自動で作り直し） */
function thumb_file(string $path): string
{
    $key = sha1($path . '|' . filemtime($path) . '|' . filesize($path));
    return cfg('thumb_dir') . DIRECTORY_SEPARATOR . $key . '.jpg';
}

// ---------- 視聴位置（ユーザーごと・端末共通） ----------
function progress_file(string $user): string
{
    $dir = cfg('data_dir');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . DIRECTORY_SEPARATOR . 'progress_' . sha1($user) . '.json';
}

function progress_load(string $user): array
{
    $f = progress_file($user);
    if (!is_file($f)) return [];
    $fp = fopen($f, 'rb');
    flock($fp, LOCK_SH);
    $json = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}

function progress_save(string $user, string $name, array $entry): void
{
    $fp = fopen(progress_file($user), 'c+');
    flock($fp, LOCK_EX);
    $data = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($data)) $data = [];
    $data[$name] = $entry;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function format_size(int $b): string
{
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $v = (float)$b;
    while ($v >= 1024 && $i < count($u) - 1) { $v /= 1024; $i++; }
    return ($i ? number_format($v, 1) : (string)$b) . ' ' . $u[$i];
}
