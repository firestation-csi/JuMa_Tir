<?php
$ranking       = $ranking       ?? [];
$competition   = $competition   ?? [];
$totalStations = $totalStations ?? 0;

$top3      = array_values(array_filter($ranking, fn($r) => $r['is_complete']));
$top3      = array_slice($top3, 0, 3);
$rest      = array_values(array_filter($ranking, fn($r) => $r['is_complete']));
$rest      = array_slice($rest, 3);
$pending   = array_values(array_filter($ranking, fn($r) => !$r['is_complete']));

$rankingJson = json_encode(array_map(fn($r) => [
    'rank'          => (int)$r['rank'],
    'group_num'     => $r['group_num'],
    'group_name'    => $r['group_name'],
    'feuerwehr_name'=> $r['feuerwehr_name'] ?? '',
    'total_fp'      => (int)$r['total_fp'],
    'combined_score'=> (float)$r['combined_score'],
    'is_complete'   => (bool)$r['is_complete'],
], $ranking), JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Präsentation · <?= htmlspecialchars($competition['name'] ?? '') ?></title>
    <style>
        :root {
            --gold:   #f59e0b;
            --silver: #94a3b8;
            --bronze: #b45309;
            --bg:     #0f172a;
            --surface:#1e293b;
            --border: #334155;
            --text:   #f1f5f9;
            --muted:  #94a3b8;
            --red:    #D4263A;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; background: var(--bg); color: var(--text);
                     font-family: system-ui, sans-serif; overflow: hidden; }

        /* ── Header ── */
        #pres-header {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 24px;
            background: rgba(15,23,42,.92); border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
        }
        #pres-header h1 { font-size: 15px; font-weight: 800; letter-spacing: -.02em; }
        #pres-header h1 span { color: var(--red); }
        #pres-meta { font-size: 12px; color: var(--muted); font-family: monospace; }
        #pres-live  { font-size: 11px; font-weight: 700; padding: 3px 10px;
                      border-radius: 6px; background: #064e3b; color: #34d399; }

        /* ── Views ── */
        .pres-view { position: fixed; inset: 0; top: 46px;
                     display: flex; flex-direction: column; align-items: center;
                     overflow: hidden; opacity: 0; pointer-events: none;
                     transition: opacity .6s ease; padding: 24px 32px 16px; }
        .pres-view.active { opacity: 1; pointer-events: auto; }

        /* ── Podium ── */
        #view-podium { justify-content: flex-end; gap: 0; }
        #view-podium .comp-name {
            position: absolute; top: 16px; left: 0; right: 0; text-align: center;
            font-size: clamp(20px, 3vw, 36px); font-weight: 900;
            letter-spacing: -.03em; color: var(--text); opacity: .9;
        }

        .podium-stage { display: flex; align-items: flex-end; justify-content: center;
                        gap: 16px; width: 100%; max-width: 860px; }
        .podium-slot  { display: flex; flex-direction: column; align-items: center;
                        flex: 1; max-width: 260px; }
        .podium-slot--empty .podium-bar { opacity: .15; }

        .podium-medal { font-size: clamp(32px, 5vw, 56px); margin-bottom: 8px;
                        animation: bounce .8s ease infinite alternate; }
        .podium-slot:nth-child(2) .podium-medal { animation-delay: .2s; }
        .podium-slot:nth-child(3) .podium-medal { animation-delay: .4s; }
        @keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-8px); } }

        .podium-name  { font-size: clamp(13px, 1.8vw, 20px); font-weight: 800;
                        text-align: center; line-height: 1.2; margin-bottom: 4px; }
        .podium-fw    { font-size: clamp(11px, 1.2vw, 15px); color: var(--muted);
                        text-align: center; margin-bottom: 8px; }
        .podium-score { font-size: clamp(22px, 3.5vw, 44px); font-weight: 900;
                        font-family: monospace; margin-bottom: 10px; }
        .podium-slot--1 .podium-score { color: var(--gold); }
        .podium-slot--2 .podium-score { color: var(--silver); }
        .podium-slot--3 .podium-score { color: var(--bronze); }
        .podium-bar   { width: 100%; border-radius: 8px 8px 0 0; }
        .podium-slot--1 .podium-bar { height: clamp(100px, 18vh, 180px); background: linear-gradient(180deg,#fbbf24,#d97706); }
        .podium-slot--2 .podium-bar { height: clamp(70px,  13vh, 130px); background: linear-gradient(180deg,#cbd5e1,#64748b); }
        .podium-slot--3 .podium-bar { height: clamp(50px,  9vh,  100px); background: linear-gradient(180deg,#d97706,#92400e); }

        /* ── Rangliste ── */
        #view-ranking { justify-content: flex-start; }
        #view-ranking h2 { font-size: clamp(18px, 2.5vw, 30px); font-weight: 900;
                           margin-bottom: 16px; align-self: flex-start; }

        #ranking-table-wrap { width: 100%; max-width: 900px; overflow-y: auto;
                              flex: 1; border-radius: 12px; }
        .rank-row { display: grid; grid-template-columns: 3rem 1fr auto auto;
                    align-items: center; gap: 12px; padding: 12px 20px;
                    border-bottom: 1px solid var(--border); }
        .rank-row:last-child { border-bottom: 0; }
        .rank-row--gold   { background: rgba(245,158,11,.12); }
        .rank-row--silver { background: rgba(148,163,184,.08); }
        .rank-row--bronze { background: rgba(180,83,9,.1); }
        .rank-num  { font-size: 18px; font-weight: 900; font-family: monospace;
                     text-align: center; }
        .rank-num--1 { color: var(--gold); }
        .rank-num--2 { color: var(--silver); }
        .rank-num--3 { color: var(--bronze); }
        .rank-name { font-size: clamp(13px, 1.5vw, 18px); font-weight: 700; }
        .rank-fw   { font-size: clamp(11px, 1.1vw, 14px); color: var(--muted); margin-top: 2px; }
        .rank-fp   { font-size: clamp(11px, 1.1vw, 13px); color: var(--muted);
                     font-family: monospace; text-align: right; }
        .rank-score{ font-size: clamp(16px, 2vw, 24px); font-weight: 900;
                     font-family: monospace; text-align: right; }

        /* ── Nav-Dots ── */
        #pres-nav { position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%);
                    display: flex; gap: 8px; z-index: 200; }
        .nav-dot { width: 10px; height: 10px; border-radius: 50%;
                   background: var(--border); cursor: pointer; transition: background .2s; }
        .nav-dot.active { background: var(--red); }

        /* ── Controls hint ── */
        #pres-hint { position: fixed; bottom: 40px; right: 16px; font-size: 11px;
                     color: var(--border); font-family: monospace; }
    </style>
</head>
<body>

<!-- Header -->
<div id="pres-header">
    <h1><span>KFV</span> Tirschenreuth · <?= htmlspecialchars($competition['name'] ?? '') ?></h1>
    <span id="pres-live">● LIVE</span>
    <div id="pres-meta">–</div>
</div>

<!-- View 1: Podium -->
<div class="pres-view active" id="view-podium">
    <div class="comp-name"><?= htmlspecialchars($competition['name'] ?? '') ?></div>
    <div class="podium-stage" id="podiumStage"></div>
</div>

<!-- View 2: Rangliste -->
<div class="pres-view" id="view-ranking">
    <h2>Gesamtrangliste</h2>
    <div id="ranking-table-wrap">
        <div id="rankingRows" style="background:var(--surface);border-radius:12px;overflow:hidden;"></div>
    </div>
</div>

<!-- Nav-Dots -->
<div id="pres-nav">
    <div class="nav-dot active" data-view="0"></div>
    <div class="nav-dot"       data-view="1"></div>
</div>

<div id="pres-hint">← → wechseln · F Vollbild</div>

<script>
(function () {
    const RANKING_JSON = <?= $rankingJson ?>;
    let ranking = RANKING_JSON;
    let view    = 0;
    const views = ['view-podium', 'view-ranking'];

    // ── Podium rendern ──────────────────────────────
    function renderPodium(data) {
        const completed = data.filter(r => r.is_complete);
        const top3      = completed.slice(0, 3);
        const order     = [1, 0, 2]; // Platz 2, 1, 3 für Podiums-Optik

        const stage = document.getElementById('podiumStage');
        const medals = ['🥇','🥈','🥉'];
        const slots  = ['1','2','3'];

        stage.innerHTML = order.map(i => {
            const r = top3[i];
            if (!r) return `<div class="podium-slot podium-slot--${slots[i]} podium-slot--empty">
                <div class="podium-bar"></div></div>`;
            const score = Number(r.combined_score).toFixed(1).replace('.', ',');
            return `<div class="podium-slot podium-slot--${slots[i]}">
                <div class="podium-medal">${medals[i]}</div>
                <div class="podium-name">#${r.group_num} ${r.group_name}</div>
                ${r.feuerwehr_name ? `<div class="podium-fw">${r.feuerwehr_name}</div>` : ''}
                <div class="podium-score">${score}</div>
                <div class="podium-bar"></div>
            </div>`;
        }).join('');
    }

    // ── Rangliste rendern ───────────────────────────
    function renderRanking(data) {
        const completed = data.filter(r => r.is_complete);
        const cls = ['','rank-row--gold','rank-row--silver','rank-row--bronze'];
        const numCls = ['','rank-num--1','rank-num--2','rank-num--3'];
        document.getElementById('rankingRows').innerHTML = completed.map(r => `
            <div class="rank-row ${cls[r.rank] ?? ''}">
                <div class="rank-num ${numCls[r.rank] ?? ''}">${r.rank}</div>
                <div>
                    <div class="rank-name">#${r.group_num} ${r.group_name}</div>
                    ${r.feuerwehr_name ? `<div class="rank-fw">${r.feuerwehr_name}</div>` : ''}
                </div>
                <div class="rank-fp">${r.total_fp} FP</div>
                <div class="rank-score">${Number(r.combined_score).toFixed(1).replace('.', ',')}</div>
            </div>`).join('');
    }

    // ── View wechseln ───────────────────────────────
    function showView(idx) {
        view = (idx + views.length) % views.length;
        document.querySelectorAll('.pres-view').forEach((v, i) =>
            v.classList.toggle('active', i === view));
        document.querySelectorAll('.nav-dot').forEach((d, i) =>
            d.classList.toggle('active', i === view));
    }

    // ── Auto-Slide alle 20s ─────────────────────────
    let slideTimer = setInterval(() => showView(view + 1), 20_000);

    function resetSlide() {
        clearInterval(slideTimer);
        slideTimer = setInterval(() => showView(view + 1), 20_000);
    }

    document.querySelectorAll('.nav-dot').forEach(d =>
        d.addEventListener('click', () => { showView(parseInt(d.dataset.view)); resetSlide(); }));

    // ── Tastatur ────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'ArrowRight' || e.key === ' ') { showView(view + 1); resetSlide(); }
        if (e.key === 'ArrowLeft')                   { showView(view - 1); resetSlide(); }
        if (e.key === 'f' || e.key === 'F') {
            document.fullscreenElement
                ? document.exitFullscreen()
                : document.documentElement.requestFullscreen();
        }
    });

    // ── Live-Polling alle 30s ───────────────────────
    async function poll() {
        try {
            const res  = await fetch('/admin/results', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error();
            const json = await res.json();
            const data = json.data ?? json;
            if (data.ranking) {
                ranking = data.ranking;
                renderPodium(ranking);
                renderRanking(ranking);
            }
            document.getElementById('pres-live').textContent = '● LIVE';
            document.getElementById('pres-live').style.background = '#064e3b';
            document.getElementById('pres-meta').textContent = 'aktualisiert ' + (data.ts || '');
        } catch {
            document.getElementById('pres-live').textContent = '○ OFFLINE';
            document.getElementById('pres-live').style.background = '#450a0a';
        }
    }

    // ── Init ────────────────────────────────────────
    renderPodium(ranking);
    renderRanking(ranking);
    poll();
    setInterval(poll, 30_000);
})();
</script>
</body>
</html>
