<?php
require __DIR__ . '/lib.php';
require_login();

$me = (string)$_SESSION['user'];
$progress = progress_load($me);
$videos = array_map(fn($v) => [
    'name'  => $v['name'],
    'title' => preg_replace('/\.[^.]+$/', '', $v['name']),
    'size'  => format_size($v['size']),
    'date'  => date('Y/m/d', $v['mtime']),
    'p'     => $progress[$v['name']] ?? null,
    'src'   => signed_stream_url($v['name'], $me),
], video_list());
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h(cfg('site_title')) ?></title>
<style>
  :root{--bg:#0f1115;--panel:#181b22;--line:#2a2f3a;--text:#e8eaf0;--sub:#8b92a3;--accent:#4f8cff}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,"Hiragino Sans","Yu Gothic UI",sans-serif}
  header{position:sticky;top:0;z-index:10;display:flex;gap:12px;align-items:center;padding:12px 20px;background:rgba(15,17,21,.92);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
  header h1{font-size:18px;margin:0;white-space:nowrap}
  #q{flex:1;max-width:360px;padding:9px 12px;border-radius:8px;border:1px solid var(--line);background:var(--panel);color:var(--text);font-size:14px}
  #q:focus{outline:none;border-color:var(--accent)}
  .count{color:var(--sub);font-size:13px;white-space:nowrap}
  header form{margin-left:auto}
  header button{background:none;border:1px solid var(--line);color:var(--sub);padding:7px 12px;border-radius:8px;cursor:pointer;font-size:13px}
  header button:hover{color:var(--text);border-color:var(--sub)}
  main{max-width:1280px;margin:0 auto;padding:20px}

  /* 最初は一覧だけ。動画を開いたら一覧を隠して視聴画面に */
  #player{display:none}
  body.watching #player{display:block}
  body.watching #listView,body.watching #q,body.watching .count{display:none}
  #player video{width:100%;max-height:78vh;background:#000;border-radius:12px;display:block}
  #player .bar{display:flex;align-items:center;gap:12px;margin-top:12px}
  #player .back{background:none;border:1px solid var(--line);color:var(--text);padding:7px 12px;border-radius:8px;cursor:pointer;white-space:nowrap}
  #player h2{font-size:18px;margin:0;flex:1;word-break:break-all}
  #player .close{background:var(--panel);border:1px solid var(--line);color:var(--text);padding:7px 14px;border-radius:8px;cursor:pointer}

  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden;cursor:pointer;transition:transform .15s,border-color .15s;text-align:left;color:inherit;font:inherit;padding:0}
  .card:hover{transform:translateY(-3px);border-color:var(--accent)}
  .thumb{position:relative;aspect-ratio:16/9;background:#0b0d11 center/cover no-repeat;display:grid;place-items:center}
  .thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .3s}
  .thumb img.loaded{opacity:1}
  .thumb .ph{color:#3a4050;font-size:34px}
  .thumb .play{position:absolute;width:46px;height:46px;border-radius:50%;background:rgba(0,0,0,.55);display:grid;place-items:center;opacity:0;transition:opacity .15s}
  .card:hover .play{opacity:1}
  .play::after{content:"";border-left:14px solid #fff;border-top:9px solid transparent;border-bottom:9px solid transparent;margin-left:4px}
  .meta{padding:10px 12px 12px}
  .meta .t{font-size:14px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;word-break:break-all}
  .meta .s{font-size:12px;color:var(--sub);margin-top:6px}
  .empty{color:var(--sub);text-align:center;padding:60px 0;line-height:1.8}
  .pbar{position:absolute;left:0;right:0;bottom:0;height:4px;background:rgba(255,255,255,.18);display:none}
  .pbar i{display:block;height:100%;background:#ff4d4f}
  .badge{position:absolute;top:8px;left:8px;font-size:11px;padding:3px 7px;border-radius:6px;background:rgba(0,0,0,.7);color:#fff;display:none}
  .badge.done{background:rgba(46,160,90,.9)}
  .thumb .dur{position:absolute;right:8px;bottom:10px;font-size:11px;padding:2px 6px;border-radius:5px;background:rgba(0,0,0,.7);color:#fff;display:none}
  .vwrap{position:relative}
  #resume{position:absolute;left:16px;bottom:64px;display:none;align-items:center;gap:10px;background:rgba(20,22,28,.92);border:1px solid var(--line);padding:9px 12px;border-radius:10px;font-size:13px}
  #resume.show{display:flex}
  /* ---- 横画面モード ---- */
  .vwrap.land{position:fixed;top:0;left:0;z-index:1000;background:#000;width:var(--lw);height:var(--lh);transform-origin:top left}
  .vwrap.land.rot{transform:translateX(var(--vw)) rotate(90deg)}
  .vwrap.land video{width:100%;height:100%;max-height:none;border-radius:0;object-fit:contain}
  /* 左上の最大化ボタン（押すと即横画面 / 横画面中は縮小アイコンで元に戻る） */
  .landBtn{position:absolute;top:10px;left:10px;z-index:2;width:44px;height:44px;border-radius:10px;border:0;background:rgba(0,0,0,.45);color:#fff;cursor:pointer;display:grid;place-items:center;transition:opacity .3s;padding:0}
  .landBtn svg{width:26px;height:26px}
  .landBtn .shrink{display:none}
  .vwrap.land .landBtn .expand{display:none}
  .vwrap.land .landBtn .shrink{display:block}
  .vwrap.idle .landBtn{opacity:0}
  /* iPhone は Safari 自身の全画面ボタンが左上にある（横画面中も一緒に回転する）ので、重ならないよう上部中央へ */
  body.ios .landBtn{left:50%;transform:translateX(-50%);top:8px;width:40px;height:40px}
  #verr{display:none;margin-top:10px;padding:16px;background:var(--panel);border:1px solid var(--line);border-radius:12px;text-align:center;font-size:14px;line-height:1.7}
  #verr.show{display:block}
  .vwrap.land #verr.show{position:absolute;inset:0;z-index:3;margin:0;border-radius:0;overflow:auto;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.88)}
  #verr b{display:block;font-size:16px;margin-bottom:6px}
  #verr code{display:block;margin-top:10px;font-size:11px;background:#000;padding:8px;border-radius:6px;word-break:break-all;user-select:all;text-align:left}
  #verr button{margin-top:12px;background:var(--accent);color:#fff;border:0;border-radius:8px;padding:8px 16px;cursor:pointer}
  body.noscroll{overflow:hidden;touch-action:none}
  #resume button{background:var(--accent);color:#fff;border:0;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:12px}
  /* ---- リモコン（十字キー）操作 ---- */
  body.kbd .card:focus,body.kbd header :focus,body.kbd #listView :focus{outline:4px solid var(--accent);outline-offset:3px}
  body.kbd .card:focus{transform:scale(1.04);border-color:var(--accent)}
  #osd{position:absolute;left:0;right:0;bottom:0;z-index:4;padding:60px 4% 28px;background:linear-gradient(transparent,rgba(0,0,0,.85));display:none;pointer-events:none;transition:opacity .3s}
  body.tv #osd{display:block}
  #osd.hide{opacity:0}
  #osd .ot{font-size:24px;font-weight:600;margin-bottom:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  #osd .orow{display:flex;align-items:center;gap:16px;font-size:18px;font-variant-numeric:tabular-nums}
  /* 再生バー: 上下に広い当たり判定でタッチしやすく */
  #osd .otrack{flex:1;position:relative;height:32px;display:flex;align-items:center;pointer-events:auto;cursor:pointer;touch-action:none}
  #osd .otrack::before{content:"";position:absolute;left:0;right:0;height:8px;background:rgba(255,255,255,.25);border-radius:4px}
  #osd .otrack i{position:relative;display:block;height:8px;width:0;background:#ff4d4f;border-radius:4px}
  #osd .otrack i::after{content:"";position:absolute;right:-9px;top:50%;width:18px;height:18px;margin-top:-9px;border-radius:50%;background:#ff4d4f}
  /* タッチ用ボタン */
  #osd .octl{display:flex;align-items:center;gap:14px;margin-top:12px}
  #osd .octl button{pointer-events:auto;height:56px;min-width:56px;padding:0 16px;border:0;border-radius:28px;background:rgba(255,255,255,.14);color:#fff;font-size:18px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px}
  #osd .octl button:active{background:rgba(255,255,255,.3)}
  #osd .octl .big{min-width:72px;height:64px;font-size:24px}
  #osd .octl .sp{flex:1}
  #osd.hide,#osd.hide *{pointer-events:none!important}
  #osd .ohint{margin-top:12px;font-size:14px;color:#9aa1ad}
  body:not(.kbd) #osd .ohint{display:none} /* タッチで操作中はリモコンの案内を隠す */
  #osd .ohint b{display:inline-block;min-width:26px;padding:1px 7px;margin:0 4px 0 14px;border:1px solid #6b7280;border-radius:6px;text-align:center;font-weight:500}
  #osd .ohint b:first-child{margin-left:0}
  #seekFx{position:absolute;top:50%;left:50%;z-index:5;transform:translate(-50%,-50%);padding:16px 28px;border-radius:16px;background:rgba(0,0,0,.65);font-size:30px;font-weight:700;opacity:0;transition:opacity .25s;pointer-events:none;white-space:nowrap}
  #seekFx.show{opacity:1}

  /* ---- テレビ（Fire TV など）: 再生中は動画を画面いっぱいに ---- */
  body.tv .grid{grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:26px}
  body.tv .card:hover{transform:none}
  body.tv.watching header{display:none}
  body.tv.watching main{padding:0;max-width:none}
  body.tv.watching #player{position:fixed;inset:0;z-index:900;background:#000}
  body.tv .vwrap{width:100%;height:100%}
  body.tv #player video{width:100%;height:100vh;max-height:none;border-radius:0;object-fit:contain}
  body.tv #player .bar,body.tv .landBtn{display:none}
  body.tv #resume{bottom:150px;left:4%;font-size:18px;padding:12px 16px}
  body.tv #verr.show{position:absolute;inset:0;z-index:6;margin:0;border-radius:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.9);font-size:18px}
  @media (max-width:600px){
    header{flex-wrap:wrap;padding:10px 16px}
    #q{order:3;max-width:none;flex-basis:100%}
    main{padding:16px}
    .grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
  }
</style>
</head>
<body>
<header>
  <h1>🎬 <?= h(cfg('site_title')) ?></h1>
  <input id="q" type="search" placeholder="タイトルで検索">
  <span class="count" id="count"></span>
  <form method="post" action="logout.php">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <button type="submit">ログアウト</button>
  </form>
</header>

<main>
  <section id="player">
    <div class="vwrap">
      <video id="video" controls playsinline preload="metadata" controlslist="nodownload" disablepictureinpicture></video>
      <div id="verr"><div><b>再生できませんでした</b><span id="verrText"></span><button id="verrRetry">もう一度試す</button></div></div>
      <div id="resume"><span id="resumeText"></span><button id="restartBtn">最初から</button></div>
      <div id="seekFx"></div>
      <div id="osd" class="hide">
        <div class="ot" id="osdTitle"></div>
        <div class="orow"><span id="osdCur">0:00</span><div class="otrack"><i id="osdBar"></i></div><span id="osdDur">0:00</span></div>
        <div class="octl">
          <button type="button" id="oBack" tabindex="-1">← 一覧</button>
          <span class="sp"></span>
          <button type="button" id="oRew" tabindex="-1">⏪ 10秒</button>
          <button type="button" id="oPlay" class="big" tabindex="-1" aria-label="再生 / 一時停止">▶</button>
          <button type="button" id="oFf" tabindex="-1">10秒 ⏩</button>
          <span class="sp"></span>
          <button type="button" id="oFs" tabindex="-1">⛶ 全画面</button>
        </div>
        <div class="ohint"><b>●</b>再生 / 一時停止<b>◀</b><b>▶</b>10秒移動（長押しで早送り）<b>▲</b>全画面<b>↩</b>一覧へ戻る</div>
      </div>
      <button class="landBtn" id="landBtn" aria-label="横画面で全画面表示">
        <svg class="expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10V4h6M4 4l16 16M20 14v6h-6"/></svg>
        <svg class="shrink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4v6H4M10 10 3 3M14 20v-6h6M14 14l7 7"/></svg>
      </button>
    </div>
    <div class="bar">
      <button class="back" id="closeBtn">← 一覧</button>
      <h2 id="nowTitle"></h2>
    </div>
  </section>

  <div id="listView">
  <div class="grid" id="grid"></div>
  <?php if (!$videos): ?>
    <div class="empty">動画がありません。<br>movie フォルダに MP4 を入れるとここに表示されます。</div>
  <?php endif; ?>
  </div>
</main>

<script>
const VIDEOS = <?= json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const CSRF = <?= json_encode(csrf_token()) ?>;

const grid = document.getElementById('grid');
const player = document.getElementById('player');
const video = document.getElementById('video');
const nowTitle = document.getElementById('nowTitle');
const q = document.getElementById('q');
const countEl = document.getElementById('count');

const thumbUrl  = n => 'thumb.php?f=' + encodeURIComponent(n);

// テレビ（Fire TV / Echo Show / Android TV など）判定。URL に ?tv=1 / ?tv=0 で手動切替もできる（端末ごとに記憶）
// ブラウザが端末名を隠している場合も、リモコンのボタンを押した時点でテレビ表示に切り替わる
let tvSaved = null;
let IS_TV = (() => {
  const m = location.search.match(/[?&]tv=([01])/);
  try { if (m) localStorage.setItem('tvMode', m[1]); } catch {}
  try { tvSaved = localStorage.getItem('tvMode'); } catch {}
  if (tvSaved !== null) return tvSaved === '1';
  return /AFT[A-Z]|AEO[A-Z]|Echo Show|Fire TV|FireTV|Android TV|GoogleTV|SMART-TV|SmartTV|Tizen|Web0S|BRAVIA|AppleTV|CrKey/i.test(navigator.userAgent);
})();
// マウスが無い端末（テレビ・Echo Show 等）だけ、リモコン操作で自動切替（PCのキーボード操作では切り替えない）
const NO_MOUSE = !matchMedia('(pointer: fine)').matches;
let lastCard = null;

video.addEventListener('contextmenu', e => e.preventDefault());

// ---------- 一覧 ----------
const cards = VIDEOS.map(v => {
  const el = document.createElement('button');
  el.className = 'card';
  el.dataset.name = v.name;
  el.innerHTML = `
    <div class="thumb"><span class="ph">🎞</span><img alt=""><span class="play"></span><span class="badge"></span><span class="dur"></span><div class="pbar"><i></i></div></div>
    <div class="meta"><div class="t"></div><div class="s"></div></div>`;
  el.querySelector('.t').textContent = v.title;
  el.querySelector('.s').textContent = `${v.date}・${v.size}`;
  const img = el.querySelector('img');
  img.loading = 'lazy';
  img.onload = () => img.classList.add('loaded');
  img.onerror = () => { if (!img.dataset.tried) { img.dataset.tried = 1; enqueueThumb(v, img); } };
  img.src = thumbUrl(v.name);
  el.addEventListener('click', () => play(v));
  grid.appendChild(el);
  renderProgress(el, v.p);
  return el;
});

function fmt(sec) {
  sec = Math.max(0, Math.floor(sec || 0));
  const h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
  return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
}

// カードに視聴状況（進捗バー・続きから/視聴済み）を表示
function renderProgress(el, p) {
  const bar = el.querySelector('.pbar'), badge = el.querySelector('.badge'), dur = el.querySelector('.dur');
  bar.style.display = badge.style.display = dur.style.display = 'none';
  badge.classList.remove('done');
  if (!p) return;
  if (p.d) { dur.textContent = fmt(p.d); dur.style.display = ''; }
  if (p.done) {
    badge.textContent = '✓ 視聴済み';
    badge.classList.add('done');
    badge.style.display = '';
  } else if (p.t > 5 && p.d) {
    bar.querySelector('i').style.width = Math.min(100, p.t / p.d * 100) + '%';
    bar.style.display = '';
    badge.textContent = '続きから ' + fmt(p.t);
    badge.style.display = '';
  }
}

function updateCount(n) { countEl.textContent = `${n} 本`; }
updateCount(VIDEOS.length);

q.addEventListener('input', () => {
  const kw = q.value.trim().toLowerCase();
  let n = 0;
  cards.forEach((c, i) => {
    const hit = !kw || VIDEOS[i].title.toLowerCase().includes(kw);
    c.style.display = hit ? '' : 'none';
    if (hit) n++;
  });
  updateCount(n);
});

// ---------- 再生 ----------
let current = null;     // 再生中の動画
let ready = false;      // 再開位置の反映が済むまで保存しない（0秒で上書きしないため）
let lastSavedT = -1;
const resumeBox = document.getElementById('resume');
const resumeText = document.getElementById('resumeText');
let resumeTimer = null;

let listScrollY = 0;

async function play(v) {
  saveProgress(true);   // 前の動画の位置を保存
  current = v;
  ready = false;
  lastSavedT = -1;
  hideResume();
  hideError();
  retries = 0;
  lastTime = 0;

  nowTitle.textContent = v.title;
  osdTitle.textContent = v.title;
  document.title = v.title;
  lastCard = cards[VIDEOS.indexOf(v)] || lastCard;
  if (IS_TV) {
    showOsd();
    // テレビでは画面いっぱいに（ブラウザのアドレスバー等も隠す）
    if (document.documentElement.requestFullscreen && !document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
    }
  }
  if (!document.body.classList.contains('watching')) {
    listScrollY = window.scrollY;               // 戻った時に一覧の位置を復元
    document.body.classList.add('watching');
    history.pushState({ watch: true }, '');      // スマホの「戻る」で一覧に戻れるように
  }
  window.scrollTo(0, 0);
  bumpIdle();

  // 他の端末で見た分も反映するため、再生のたびにサーバーから最新位置を取得
  let p = v.p;
  try {
    const r = await fetch('progress.php?f=' + encodeURIComponent(v.name), { cache: 'no-store' });
    if (r.ok) p = await r.json();
  } catch {}
  if (current !== v) return; // 取得中に別の動画が選ばれた

  const resumeAt = (p && !p.done && p.t > 5) ? p.t : 0;
  video.src = v.src;
  video.addEventListener('loadedmetadata', () => {
    if (current !== v) return;
    if (resumeAt && resumeAt < video.duration - 10) {
      video.currentTime = resumeAt;
      showResume(resumeAt);
    }
    ready = true;
  }, { once: true });
  video.play().catch(() => {});
}

function showResume(t) {
  resumeText.textContent = fmt(t) + ' から再開しました';
  resumeBox.classList.add('show');
  clearTimeout(resumeTimer);
  resumeTimer = setTimeout(hideResume, 8000);
}
function hideResume() { resumeBox.classList.remove('show'); }

document.getElementById('restartBtn').addEventListener('click', () => {
  video.currentTime = 0;
  video.play().catch(() => {});
  hideResume();
});

// 再生位置をサーバーへ保存（ユーザー単位・端末共通）
function saveProgress(force = false, done = false) {
  if (!current || !ready) return;
  const d = video.duration;
  if (!isFinite(d) || !d) return;
  const t = video.currentTime;
  if (!done && !force && Math.abs(t - lastSavedT) < 10) return;
  if (!done && t >= d - 15) done = true; // ほぼ最後まで見たら視聴済み
  lastSavedT = t;

  const p = { t: done ? 0 : t, d, done, at: Date.now() / 1000 };
  current.p = p;
  const card = cards[VIDEOS.indexOf(current)];
  if (card) renderProgress(card, p);

  const body = JSON.stringify({ csrf: CSRF, f: current.name, t, d, done });
  if (force && navigator.sendBeacon) {
    navigator.sendBeacon('progress.php', new Blob([body], { type: 'application/json' }));
  } else {
    fetch('progress.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true }).catch(() => {});
  }
}

video.addEventListener('timeupdate', () => saveProgress());          // 10秒ごと
video.addEventListener('pause', () => { if (!video.ended) saveProgress(true); });
video.addEventListener('seeked', () => saveProgress(true));
video.addEventListener('ended', () => saveProgress(true, true));
// タブを閉じる・アプリを切り替える・スマホでホームに戻る時も保存
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') saveProgress(true); });
window.addEventListener('pagehide', () => saveProgress(true));

// ---------- 途中で止まった時の自動再接続・再生できない時の原因表示 ----------
let retries = 0;
let lastTime = 0;
const verr = document.getElementById('verr');
const verrText = document.getElementById('verrText');
function hideError() { verr.classList.remove('show'); }

video.addEventListener('timeupdate', () => { if (ready) lastTime = video.currentTime; });
video.addEventListener('playing', () => { retries = 0; hideError(); });

// URLを発行し直して、止まった位置から再開
async function reconnect(v, at) {
  try {
    const r = await fetch('sign.php?f=' + encodeURIComponent(v.name), { cache: 'no-store' });
    if (r.status === 403) { location.href = 'login.php'; return; }
    if (r.ok) v.src = (await r.json()).src;
  } catch {}
  if (current !== v) return;
  ready = false;
  video.src = v.src;
  video.addEventListener('loadedmetadata', () => {
    if (current !== v) return;
    if (at) video.currentTime = at;
    ready = true;
  }, { once: true });
  video.play().catch(() => {});
}

video.addEventListener('error', async () => {
  const v = current;
  if (!v || !video.getAttribute('src')) return;
  if (retries < 3) {
    retries++;
    await new Promise(r => setTimeout(r, 800 * retries));
    if (current === v) reconnect(v, lastTime);
    return;
  }
  // 3回試してもダメ → 形式の問題の可能性が高いので原因を表示
  verrText.textContent = 'この端末では再生できない形式の可能性があります。';
  verr.classList.add('show');
  try {
    const info = await (await fetch('info.php?f=' + encodeURIComponent(v.name))).json();
    if (info && current === v) verrText.innerHTML = describeFormat(info);
  } catch {}
});

document.getElementById('verrRetry').addEventListener('click', () => {
  if (!current) return;
  hideError();
  retries = 0;
  reconnect(current, lastTime);
});

function describeFormat(i) {
  const esc = t => String(t ?? '不明').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const issues = [];
  if (i.video && i.video !== 'h264') issues.push(`映像が <b style="display:inline">${esc(i.video)}</b> 形式です（Fire TV やPCのブラウザは H.264 以外が苦手）`);
  if (i.pix_fmt && /10|12/.test(i.pix_fmt)) issues.push(`10bit映像です（${esc(i.pix_fmt)}）。対応していない端末があります`);
  if (i.audio && !['aac', 'mp3', 'opus'].includes(i.audio)) issues.push(`音声が <b style="display:inline">${esc(i.audio)}</b> 形式です（AAC 以外は再生できない端末があります）`);
  if (!issues.length) return '形式は一般的な H.264 / AAC でした。通信が不安定だった可能性があります。';
  return issues.join('<br>') + '<br>H.264 + AAC に変換すると、どの端末でも再生できます：'
    + '<code>ffmpeg -i 元.mp4 -c:v libx264 -crf 20 -pix_fmt yuv420p -c:a aac -b:a 192k -movflags +faststart 新.mp4</code>';
}

// iPhone/iPad判定（要素の全画面APIが無く、動画専用の全画面だけある＝iOS Safari）
if (!document.documentElement.requestFullscreen && video.webkitEnterFullscreen) document.body.classList.add('ios');

// ---------- 横画面モード（端末の回転ロック中でも横向きで全画面表示） ----------
const vwrap = document.querySelector('.vwrap');
let landFs = false;      // Fullscreen API を使ったか
let landNative = false;  // 端末の向きを実際に横に固定できたか（Android）
let idleTimer = null;

function layoutLand() {
  const w = window.innerWidth, h = window.innerHeight;
  // 画面が実際に縦長のままなら CSS で90度回転（向き固定が効いて横になったら回転しない）
  const rot = h > w;
  vwrap.classList.toggle('rot', rot);
  vwrap.style.setProperty('--lw', (rot ? h : w) + 'px');
  vwrap.style.setProperty('--lh', (rot ? w : h) + 'px');
  vwrap.style.setProperty('--vw', w + 'px');
}

async function enterLand() {
  vwrap.classList.add('land');
  document.body.classList.add('noscroll');
  landFs = landNative = false;
  layoutLand();
  // Android など: 全画面 + 横向き固定（回転ロック中でも有効）
  try {
    if (document.documentElement.requestFullscreen) {
      await document.documentElement.requestFullscreen({ navigationUI: 'hide' });
      landFs = true;
      try { await screen.orientation.lock('landscape'); landNative = true; } catch {}
    }
  } catch {}
  // iPhone など全画面APIが無い端末は CSS 回転だけで横向き表示
  layoutLand();
  bumpIdle();
}

async function exitLand() {
  if (!vwrap.classList.contains('land')) return;
  vwrap.classList.remove('land', 'rot', 'idle');
  document.body.classList.remove('noscroll');
  try { screen.orientation.unlock(); } catch {}
  if (document.fullscreenElement) await document.exitFullscreen().catch(() => {});
  landFs = landNative = false;
}

// 最大化ボタンは再生中に3秒操作が無いと消える（YouTube風）。タップで再表示
function bumpIdle() {
  vwrap.classList.remove('idle');
  clearTimeout(idleTimer);
  idleTimer = setTimeout(() => { if (!video.paused) vwrap.classList.add('idle'); }, 3000);
}

document.getElementById('landBtn').addEventListener('click', e => {
  e.stopPropagation();
  if (vwrap.classList.contains('land')) exitLand(); else enterLand();
});
vwrap.addEventListener('pointerdown', bumpIdle);
video.addEventListener('pause', () => { clearTimeout(idleTimer); vwrap.classList.remove('idle'); });
video.addEventListener('play', bumpIdle);
window.addEventListener('resize', () => { if (vwrap.classList.contains('land')) layoutLand(); });
document.addEventListener('fullscreenchange', () => {
  if (!document.fullscreenElement && landFs) exitLand(); // 戻るボタン等で全画面が解除された
});

// 視聴画面を閉じて一覧に戻る
function closeWatch() {
  if (!document.body.classList.contains('watching')) return;
  exitLand();
  saveProgress(true);
  current = null;
  ready = false;
  hideResume();
  video.pause();
  video.removeAttribute('src');
  video.load();
  document.body.classList.remove('watching');
  document.title = <?= json_encode(cfg('site_title')) ?>;
  if (IS_TV && document.fullscreenElement) document.exitFullscreen().catch(() => {});
  window.scrollTo(0, listScrollY);
  // リモコン操作中なら、見ていた動画のカードにフォーカスを戻す
  if (lastCard && (IS_TV || document.body.classList.contains('kbd'))) lastCard.focus({ preventScroll: true });
}

document.getElementById('closeBtn').addEventListener('click', () => {
  if (history.state && history.state.watch) history.back(); // → popstate で closeWatch
  else closeWatch();
});
window.addEventListener('popstate', closeWatch);

// ---------- リモコン操作（Fire TV など） ----------
// Fire TV のリモコン: 十字キー=矢印キー / 決定=Enter / 再生一時停止・早戻し・早送り=メディアキー / 戻る=ブラウザの戻る
const osd = document.getElementById('osd');
const osdTitle = document.getElementById('osdTitle');
const osdCur = document.getElementById('osdCur');
const osdDur = document.getElementById('osdDur');
const osdBar = document.getElementById('osdBar');
const seekFx = document.getElementById('seekFx');
let osdTimer = null, seekFxTimer = null, lastSeekAt = 0, seekStreak = 0;

function updateOsd() {
  const d = video.duration;
  osdCur.textContent = fmt(video.currentTime);
  osdDur.textContent = isFinite(d) ? fmt(d) : '0:00';
  osdBar.style.width = (isFinite(d) && d ? video.currentTime / d * 100 : 0) + '%';
}
function showOsd() {
  updateOsd();
  osd.classList.remove('hide');
  clearTimeout(osdTimer);
  osdTimer = setTimeout(() => { if (!video.paused) osd.classList.add('hide'); }, 4000);
}
video.addEventListener('timeupdate', () => { if (!osd.classList.contains('hide')) updateOsd(); });

// ---- タッチ・マウス操作（テレビ表示でもリモコンと両方使える） ----
const oPlay = document.getElementById('oPlay');
const syncPlayBtn = () => { oPlay.textContent = video.paused ? '▶' : '❚❚'; };
video.addEventListener('play', syncPlayBtn);
video.addEventListener('pause', syncPlayBtn);
const tap = (id, fn) => document.getElementById(id).addEventListener('click', e => { e.stopPropagation(); fn(); showOsd(); });
tap('oPlay', () => { if (video.paused) video.play().catch(() => {}); else video.pause(); });
tap('oRew', () => { seekStreak = -1; lastSeekAt = 0; seekBy(-1); });
tap('oFf',  () => { seekStreak = -1; lastSeekAt = 0; seekBy(1); });
tap('oFs',  () => toggleFullscreen());
tap('oBack', () => goBackToList());
document.addEventListener('fullscreenchange', () => {
  document.getElementById('oFs').textContent = document.fullscreenElement ? '⛶ 全画面を終了' : '⛶ 全画面';
});

// 再生バーをタッチ / ドラッグして好きな位置へ
const otrack = osd.querySelector('.otrack');
function seekToPointer(e) {
  const d = video.duration;
  if (!ready || !isFinite(d)) return;
  const r = otrack.getBoundingClientRect();
  // 横画面（90度回転）中も正しく計算できるよう、バーの向きに合わせる
  const rot = vwrap.classList.contains('rot');
  const ratio = rot ? (e.clientY - r.top) / r.height : (e.clientX - r.left) / r.width;
  video.currentTime = Math.min(Math.max(0, ratio), 1) * (d - 0.5);
  hideResume();
  updateOsd();
  showOsd();
}
otrack.addEventListener('pointerdown', e => {
  e.stopPropagation();
  otrack.setPointerCapture(e.pointerId);
  seekToPointer(e);
  const move = ev => seekToPointer(ev);
  const up = () => { otrack.removeEventListener('pointermove', move); otrack.removeEventListener('pointerup', up); otrack.removeEventListener('pointercancel', up); };
  otrack.addEventListener('pointermove', move);
  otrack.addEventListener('pointerup', up);
  otrack.addEventListener('pointercancel', up);
});
otrack.addEventListener('click', e => e.stopPropagation());

// 画面をタップ: 操作パネルの表示 / 非表示
vwrap.addEventListener('click', e => {
  if (!IS_TV) return;
  if (e.target.closest('#resume, #verr, .octl, .otrack')) return;
  if (osd.classList.contains('hide')) showOsd();
  else if (!video.paused) { clearTimeout(osdTimer); osd.classList.add('hide'); }
});
video.addEventListener('pause', () => { if (IS_TV) { clearTimeout(osdTimer); showOsd(); clearTimeout(osdTimer); } });
video.addEventListener('play', () => { if (IS_TV) showOsd(); });

function togglePlay() {
  if (video.paused) video.play().catch(() => {}); else video.pause();
  flash(video.paused ? '❚❚ 一時停止' : '▶ 再生');
}

// 押し続ける・連打すると移動量が増える（10秒 → 30秒 → 60秒）
function seekBy(dir) {
  if (!ready || !isFinite(video.duration)) return;
  const now = Date.now();
  seekStreak = now - lastSeekAt < 700 ? seekStreak + 1 : 0;
  lastSeekAt = now;
  const step = seekStreak >= 12 ? 60 : seekStreak >= 4 ? 30 : 10;
  video.currentTime = Math.min(Math.max(0, video.currentTime + dir * step), video.duration - 1);
  hideResume();
  flash(dir < 0 ? `⏪ ${step}秒` : `${step}秒 ⏩`);
  showOsd();
}

function flash(text) {
  seekFx.textContent = text;
  seekFx.classList.add('show');
  clearTimeout(seekFxTimer);
  seekFxTimer = setTimeout(() => seekFx.classList.remove('show'), 700);
}

// 一覧で十字キーを押した方向にある一番近い項目へフォーカスを移す
function focusables() {
  const list = [...document.querySelectorAll('header input, header button, .card')];
  return list.filter(el => el.offsetParent !== null);
}
function moveFocus(dir) {
  const items = focusables();
  const cur = document.activeElement;
  if (!items.includes(cur)) {
    (lastCard && items.includes(lastCard) ? lastCard : items.find(el => el.classList.contains('card')) || items[0])?.focus();
    return;
  }
  const a = cur.getBoundingClientRect();
  const ax = a.left + a.width / 2, ay = a.top + a.height / 2;
  let best = null, bestScore = Infinity;
  for (const el of items) {
    if (el === cur) continue;
    const b = el.getBoundingClientRect();
    const bx = b.left + b.width / 2, by = b.top + b.height / 2;
    const dx = bx - ax, dy = by - ay;
    let main, cross;
    if (dir === 'left')  { if (dx > -5) continue; main = -dx; cross = Math.abs(dy); }
    if (dir === 'right') { if (dx < 5)  continue; main = dx;  cross = Math.abs(dy); }
    if (dir === 'up')    { if (dy > -5) continue; main = -dy; cross = Math.abs(dx); }
    if (dir === 'down')  { if (dy < 5)  continue; main = dy;  cross = Math.abs(dx); }
    const score = main + cross * 3; // まっすぐ先にあるものを優先
    if (score < bestScore) { bestScore = score; best = el; }
  }
  if (best) {
    best.focus({ preventScroll: true });
    best.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
}

const KEY = {
  ArrowLeft: 'left', ArrowRight: 'right', ArrowUp: 'up', ArrowDown: 'down',
  Left: 'left', Right: 'right', Up: 'up', Down: 'down',
};
function mediaKey(e) {
  if (e.key === 'MediaPlayPause' || e.keyCode === 179) return 'playpause';
  if (e.key === 'MediaPlay' || e.keyCode === 415) return 'play';
  if (e.key === 'MediaPause' || e.keyCode === 19) return 'pause';
  if (e.key === 'MediaRewind' || e.keyCode === 227 || e.keyCode === 412) return 'rew';
  if (e.key === 'MediaFastForward' || e.keyCode === 228 || e.keyCode === 417) return 'ff';
  if (e.key === 'Escape' || e.key === 'GoBack' || e.key === 'BrowserBack' || e.keyCode === 4) return 'back';
  return null;
}

document.addEventListener('keydown', e => {
  const dir = KEY[e.key];
  const m = mediaKey(e);
  if (dir || m) document.body.classList.add('kbd');
  // リモコンのボタンが押された → テレビ表示に自動切替（?tv=0 で解除した端末は除く）
  if ((dir || m || e.key === 'Enter') && !IS_TV && NO_MOUSE && tvSaved !== '0' && !(e.target === q)) enableTv(true);
  const watching = document.body.classList.contains('watching');

  if (watching) {
    // 横画面モード中の Esc は横画面を終了
    if (m === 'back' && vwrap.classList.contains('land')) { e.preventDefault(); exitLand(); return; }
    // エラー表示中は 決定 で再試行
    if (verr.classList.contains('show')) {
      if (e.key === 'Enter') { e.preventDefault(); document.getElementById('verrRetry').click(); }
      else if (m === 'back') { e.preventDefault(); goBackToList(); }
      return;
    }
    if (e.target.closest && e.target.closest('header')) return;
    const handled = IS_TV || e.target === video || e.target === document.body;
    if (!handled && !m) return;

    if (m === 'playpause' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); togglePlay(); return; }
    if (m === 'play')  { e.preventDefault(); video.play().catch(() => {}); return; }
    if (m === 'pause') { e.preventDefault(); video.pause(); return; }
    if (m === 'rew' || dir === 'left')  { e.preventDefault(); seekBy(-1); return; }
    if (m === 'ff'  || dir === 'right') { e.preventDefault(); seekBy(1); return; }
    if (dir === 'down' && resumeBox.classList.contains('show')) {   // 「最初から」
      e.preventDefault(); document.getElementById('restartBtn').click(); flash('最初から'); return;
    }
    if (dir === 'up') { e.preventDefault(); if (IS_TV) { toggleFullscreen(); showOsd(); } return; }
    if (dir === 'down') { e.preventDefault(); if (IS_TV) showOsd(); return; }
    if (m === 'back') { e.preventDefault(); goBackToList(); return; }
    return;
  }

  // ---- 一覧 ----
  if (!dir) return;
  if (e.target === q && (dir === 'left' || dir === 'right')) return; // 検索欄の文字カーソル移動はそのまま
  e.preventDefault();
  moveFocus(dir);
});

// 戻るボタン: ブラウザ自身が「戻る」を処理した場合と二重にならないよう、少し待ってから
function goBackToList() {
  setTimeout(() => {
    if (!document.body.classList.contains('watching')) return;
    if (history.state && history.state.watch) history.back(); else closeWatch();
  }, 250);
}

// マウスやタッチを使ったらフォーカス枠は消す
document.addEventListener('pointerdown', () => document.body.classList.remove('kbd'));

function enableTv(remember) {
  IS_TV = true;
  if (remember) { try { localStorage.setItem('tvMode', '1'); } catch {} }
  document.body.classList.add('tv', 'kbd');
  video.removeAttribute('controls'); // テレビではブラウザ標準のバーではなく自前の表示で操作
  if (document.body.classList.contains('watching')) showOsd();
}

// 全画面（ブラウザのアドレスバー等も隠す）の切替
function toggleFullscreen() {
  if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
  else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(() => {});
}

if (IS_TV) {
  enableTv(false);
  window.addEventListener('load', () => { const c = cards[0]; if (c && !document.body.classList.contains('watching')) c.focus({ preventScroll: true }); });
  if (cards[0]) cards[0].focus({ preventScroll: true });
}

// 開いた時は常に一覧から（再読み込みした時に残った状態や古い # を消す）
if (location.hash || (history.state && history.state.watch)) history.replaceState(null, '', location.pathname + location.search);

// ---------- サムネイル自動生成（サーバーに ffmpeg が無い場合） ----------
const thumbQueue = [];
let thumbBusy = false;

function enqueueThumb(v, img) {
  thumbQueue.push({ v, img });
  runThumbQueue();
}

async function runThumbQueue() {
  if (thumbBusy) return;
  thumbBusy = true;
  while (thumbQueue.length) {
    const { v, img } = thumbQueue.shift();
    const ok = await makeThumb(v.name, v.src);
    if (ok) img.src = thumbUrl(v.name) + '&t=' + Date.now();
  }
  thumbBusy = false;
}

function makeThumb(name, src) {
  return new Promise(resolve => {
    const v = document.createElement('video');
    let done = false;
    const finish = ok => {
      if (done) return;
      done = true;
      v.removeAttribute('src'); v.load();
      resolve(ok);
    };
    v.muted = true;
    v.preload = 'metadata';
    v.playsInline = true;
    v.addEventListener('loadedmetadata', () => {
      const d = isFinite(v.duration) ? v.duration : 0;
      v.currentTime = Math.min(<?= (int)cfg('thumb_at') ?>, d * 0.1 || 0);
    }, { once: true });
    v.addEventListener('seeked', () => {
      const w = 480, hgt = Math.round(w * (v.videoHeight / v.videoWidth || 9 / 16));
      const c = document.createElement('canvas');
      c.width = w; c.height = hgt;
      c.getContext('2d').drawImage(v, 0, 0, w, hgt);
      c.toBlob(async blob => {
        if (!blob) return finish(false);
        try {
          const r = await fetch(thumbUrl(name), {
            method: 'POST',
            headers: { 'Content-Type': 'image/jpeg', 'X-CSRF-Token': CSRF },
            body: blob,
          });
          finish(r.ok);
        } catch { finish(false); }
      }, 'image/jpeg', 0.8);
    }, { once: true });
    v.addEventListener('error', () => finish(false), { once: true });
    setTimeout(() => finish(false), 30000);
    v.src = src;
  });
}
</script>
</body>
</html>
