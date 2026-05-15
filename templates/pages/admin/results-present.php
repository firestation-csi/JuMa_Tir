<?php
$ranking       = $ranking       ?? [];
$competition   = $competition   ?? [];
$totalStations = $totalStations ?? 0;

$completed = array_values(array_filter($ranking, fn($r) => $r['is_complete']));
$pending   = array_values(array_filter($ranking, fn($r) => !$r['is_complete']));

// Statistiken vorab berechnen
$totalGroups   = count($ranking);
$doneGroups    = count($completed);
$totalScores   = array_sum(array_column($completed, 'stations_completed'));
$avgFp         = $doneGroups > 0
    ? round(array_sum(array_column($completed, 'total_fp')) / $doneGroups, 1)
    : 0;
$impCounts     = array_count_values(array_column(
    array_filter($ranking, fn($r) => $r['avg_impression'] !== null),
    'avg_impression'  // not ideal, just for demo — use impressions from scores
));

$rankingJson = json_encode(array_map(fn($r) => [
    'rank'           => (int)$r['rank'],
    'group_num'      => $r['group_num'],
    'group_name'     => $r['group_name'],
    'feuerwehr_name' => $r['feuerwehr_name'] ?? '',
    'total_fp'       => (int)$r['total_fp'],
    'combined_score' => (float)$r['combined_score'],
    'is_complete'    => (bool)$r['is_complete'],
    'stations_completed' => (int)$r['stations_completed'],
], $ranking), JSON_UNESCAPED_UNICODE);

$statsJson = json_encode([
    'totalGroups'   => $totalGroups,
    'doneGroups'    => $doneGroups,
    'pendingGroups' => count($pending),
    'totalStations' => $totalStations,
    'avgFp'         => $avgFp,
    'pct'           => $totalGroups > 0 ? round($doneGroups / $totalGroups * 100) : 0,
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="de" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Präsentation · <?= htmlspecialchars($competition['name'] ?? '') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        /* Erzwinge Dark-Mode für Beamer-Ansicht */
        :root {
            --wt-bg:            #0B0A09;
            --wt-surface:       #161513;
            --wt-surface-alt:   #1E1C1A;
            --wt-border:        #2C2A27;
            --wt-border-strong: #3D3A36;
            --wt-text:          #F4F2EE;
            --wt-text-muted:    #A8A49E;
            --wt-text-subtle:   #6B6760;
            --wt-red:           #E84656;
            --wt-ok:            #58C18C;
            --wt-ok-soft:       #11261C;
            --wt-warn:          #F59E0B;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; background: var(--wt-bg);
                     color: var(--wt-text); font-family: system-ui, sans-serif; }

        /* ── Header ── */
        #ph {
            position: fixed; top: 0; left: 0; right: 0; height: 48px; z-index: 100;
            display: flex; align-items: center; gap: 14px; padding: 0 24px;
            background: var(--wt-surface); border-bottom: 1px solid var(--wt-border);
        }
        #ph-logo  { font-size: 13px; font-weight: 800; color: var(--wt-red); flex-shrink: 0; }
        #ph-title { font-size: 14px; font-weight: 700; flex: 1; white-space: nowrap;
                    overflow: hidden; text-overflow: ellipsis; }
        #ph-live  { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 6px;
                    background: var(--wt-ok-soft); color: var(--wt-ok); flex-shrink: 0; }
        #ph-ts    { font-size: 11px; color: var(--wt-text-subtle); font-family: monospace; flex-shrink: 0; }

        /* ── Views ── */
        .pv { position: fixed; inset: 0; top: 48px; bottom: 52px;
              opacity: 0; pointer-events: none; transition: opacity .5s ease;
              overflow: hidden; padding: 24px 32px; }
        .pv.active { opacity: 1; pointer-events: auto; }

        /* ── Podium ── */
        #pv-podium { display: flex; flex-direction: column; align-items: center;
                     justify-content: flex-end; gap: 0; }
        .p-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .1em;
                     text-transform: uppercase; color: var(--wt-text-subtle);
                     margin-bottom: 6px; align-self: flex-start; }
        .p-comp    { font-size: clamp(22px, 3.5vw, 42px); font-weight: 900;
                     letter-spacing: -.03em; margin-bottom: 20px; align-self: flex-start; }

        .podium-stage { display: flex; align-items: flex-end; justify-content: center;
                        gap: 12px; width: 100%; max-width: 900px; }
        .ps           { display: flex; flex-direction: column; align-items: center; flex: 1; max-width: 280px; }
        .ps-medal     { font-size: clamp(28px, 4.5vw, 52px); margin-bottom: 6px;
                        animation: pmBounce .9s ease-in-out infinite alternate; }
        .ps:nth-child(1) .ps-medal { animation-delay: .15s; }
        .ps:nth-child(3) .ps-medal { animation-delay: .30s; }
        @keyframes pmBounce { from { transform: translateY(0); } to { transform: translateY(-7px); } }
        .ps-name  { font-size: clamp(12px, 1.6vw, 19px); font-weight: 800;
                    text-align: center; line-height: 1.2; margin-bottom: 3px; }
        .ps-fw    { font-size: clamp(10px, 1.1vw, 14px); color: var(--wt-text-muted);
                    text-align: center; margin-bottom: 6px; }
        .ps-score { font-size: clamp(20px, 3vw, 40px); font-weight: 900;
                    font-family: 'JetBrains Mono', monospace; margin-bottom: 8px; }
        .ps-bar   { width: 100%; border-radius: 8px 8px 0 0; }
        .ps--1 .ps-score { color: #F59E0B; }
        .ps--2 .ps-score { color: #94A3B8; }
        .ps--3 .ps-score { color: #B45309; }
        .ps--1 .ps-bar { height: clamp(90px,16vh,170px); background: linear-gradient(180deg,#F59E0B,#D97706); }
        .ps--2 .ps-bar { height: clamp(65px,12vh,130px); background: linear-gradient(180deg,#94A3B8,#64748B); }
        .ps--3 .ps-bar { height: clamp(45px, 8vh, 100px); background: linear-gradient(180deg,#D97706,#92400E); }
        .ps--empty .ps-bar { opacity: .15; }

        /* ── Rangliste ── */
        #pv-ranking { display: flex; flex-direction: column; }
        .rl-head { font-size: clamp(16px, 2.2vw, 28px); font-weight: 900;
                   margin-bottom: 14px; }
        #rl-wrap { flex: 1; overflow-y: auto; border-radius: 10px;
                   border: 1px solid var(--wt-border); background: var(--wt-surface); }
        .rl-row  { display: grid; grid-template-columns: 3rem 1fr 5rem 7rem;
                   align-items: center; gap: 10px; padding: 11px 18px;
                   border-bottom: 1px solid var(--wt-border); }
        .rl-row:last-child { border-bottom: 0; }
        .rl-row--1 { background: rgba(245,158,11,.1); }
        .rl-row--2 { background: rgba(148,163,184,.07); }
        .rl-row--3 { background: rgba(180,83,9,.09); }
        .rl-rank  { font-size: 17px; font-weight: 900; font-family: monospace;
                    text-align: center; color: var(--wt-text-subtle); }
        .rl-rank--1 { color: #F59E0B; }
        .rl-rank--2 { color: #94A3B8; }
        .rl-rank--3 { color: #B45309; }
        .rl-name  { font-size: clamp(13px, 1.4vw, 17px); font-weight: 700; }
        .rl-fw    { font-size: clamp(10px, 1vw, 13px); color: var(--wt-text-muted); }
        .rl-fp    { font-size: 12px; color: var(--wt-text-subtle); font-family: monospace; text-align: right; }
        .rl-score { font-size: clamp(15px, 1.8vw, 22px); font-weight: 900;
                    font-family: 'JetBrains Mono', monospace; text-align: right; }

        /* ── Statistik ── */
        #pv-stats { display: flex; flex-direction: column; gap: 16px; }
        .st-head  { font-size: clamp(16px, 2.2vw, 28px); font-weight: 900; margin-bottom: 2px; }
        .st-grid  { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .st-card  { background: var(--wt-surface); border: 1px solid var(--wt-border);
                    border-radius: 12px; padding: 18px 20px; }
        .st-val   { font-size: clamp(28px, 4vw, 48px); font-weight: 900;
                    font-family: 'JetBrains Mono', monospace; line-height: 1; margin-bottom: 4px; }
        .st-lbl   { font-size: 11.5px; font-weight: 700; letter-spacing: .07em;
                    text-transform: uppercase; color: var(--wt-text-subtle); }
        .st-bar-wrap { margin-top: 4px; height: 6px; background: var(--wt-border);
                       border-radius: 3px; overflow: hidden; }
        .st-bar   { height: 100%; border-radius: 3px; background: var(--wt-ok);
                    transition: width .8s ease; }
        .st-table-wrap { flex: 1; overflow: hidden; border-radius: 10px;
                         border: 1px solid var(--wt-border); background: var(--wt-surface); }
        .st-rank-row { display: grid; grid-template-columns: 2.5rem 1fr 4rem;
                       gap: 8px; padding: 9px 16px; border-bottom: 1px solid var(--wt-border);
                       font-size: clamp(12px, 1.3vw, 15px); }
        .st-rank-row:last-child { border-bottom: 0; }
        .st-rank-num { font-weight: 900; font-family: monospace;
                       color: var(--wt-text-muted); text-align: center; }
        .st-rank-score { font-family: monospace; font-weight: 700; text-align: right; }

        /* ── Nav-Footer ── */
        #pf { position: fixed; bottom: 0; left: 0; right: 0; height: 52px;
              display: flex; align-items: center; justify-content: center; gap: 12px;
              background: var(--wt-surface); border-top: 1px solid var(--wt-border);
              padding: 0 24px; }
        .pf-dot { width: 9px; height: 9px; border-radius: 50%;
                  background: var(--wt-border-strong); cursor: pointer;
                  transition: background .2s, transform .2s; }
        .pf-dot.active { background: var(--wt-red); transform: scale(1.3); }
        #pf-hint { position: absolute; right: 20px; font-size: 11px;
                   color: var(--wt-text-subtle); font-family: monospace; }
    </style>
</head>
<body>

<div id="ph">
    <span id="ph-logo">KFV TIR</span>
    <span id="ph-title"><?= htmlspecialchars($competition['name'] ?? '') ?></span>
    <span id="ph-live">● LIVE</span>
    <span id="ph-ts">–</span>
</div>

<!-- View 0: Podium -->
<div class="pv active" id="pv-podium">
    <div class="p-eyebrow">Ergebnisse</div>
    <div class="p-comp"><?= htmlspecialchars($competition['name'] ?? '') ?>
        <?= ($competition['date'] ?? null) ? ' · ' . date('d.m.Y', strtotime($competition['date'])) : '' ?>
    </div>
    <div class="podium-stage" id="podiumStage"></div>
</div>

<!-- View 1: Rangliste -->
<div class="pv" id="pv-ranking">
    <div class="rl-head">Gesamtrangliste</div>
    <div id="rl-wrap"><div id="rl-rows"></div></div>
</div>

<!-- View 2: Statistik -->
<div class="pv" id="pv-stats">
    <div class="st-head">Wettbewerbs-Statistik</div>
    <div class="st-grid" id="stGrid"></div>
    <div class="st-table-wrap" id="stTopWrap">
        <div id="stTop"></div>
    </div>
</div>

<!-- Footer-Nav -->
<div id="pf">
    <div class="pf-dot active" data-v="0"></div>
    <div class="pf-dot"       data-v="1"></div>
    <div class="pf-dot"       data-v="2"></div>
    <span id="pf-hint">← → · F Vollbild</span>
</div>

<script>
(function () {
    let ranking = <?= $rankingJson ?>;
    let stats   = <?= $statsJson  ?>;
    let view    = 0;
    const VIEWS = ['pv-podium','pv-ranking','pv-stats'];

    /* ── Podium ──────────────────────────────────── */
    function renderPodium(data) {
        const done   = data.filter(r => r.is_complete);
        const top3   = done.slice(0, 3);
        const medals = ['🥇','🥈','🥉'];
        const order  = [1, 0, 2]; // Platz 2-1-3 für Podium-Optik

        document.getElementById('podiumStage').innerHTML = order.map(i => {
            const r = top3[i];
            if (!r) return `<div class="ps ps--${i+1} ps--empty"><div class="ps-bar"></div></div>`;
            const sc = Number(r.combined_score).toFixed(1).replace('.', ',');
            return `<div class="ps ps--${i+1}">
                <div class="ps-medal">${medals[i]}</div>
                <div class="ps-name">#${esc(r.group_num)} ${esc(r.group_name)}</div>
                ${r.feuerwehr_name ? `<div class="ps-fw">${esc(r.feuerwehr_name)}</div>` : ''}
                <div class="ps-score">${sc}</div>
                <div class="ps-bar"></div>
            </div>`;
        }).join('');
    }

    /* ── Rangliste ───────────────────────────────── */
    function renderRanking(data) {
        const done = data.filter(r => r.is_complete);
        const nc   = ['','rl-rank--1','rl-rank--2','rl-rank--3'];
        const rc   = ['','rl-row--1','rl-row--2','rl-row--3'];
        document.getElementById('rl-rows').innerHTML = done.map(r => `
            <div class="rl-row ${rc[r.rank] ?? ''}">
                <div class="rl-rank ${nc[r.rank] ?? ''}">${r.rank}</div>
                <div>
                    <div class="rl-name">#${esc(r.group_num)} ${esc(r.group_name)}</div>
                    ${r.feuerwehr_name ? `<div class="rl-fw">${esc(r.feuerwehr_name)}</div>` : ''}
                </div>
                <div class="rl-fp">${r.total_fp} FP</div>
                <div class="rl-score">${Number(r.combined_score).toFixed(1).replace('.', ',')}</div>
            </div>`).join('');
    }

    /* ── Statistik ───────────────────────────────── */
    function renderStats(data, s) {
        const done = data.filter(r => r.is_complete);
        const pct  = s.pct;
        document.getElementById('stGrid').innerHTML = `
            <div class="st-card">
                <div class="st-val" style="color:var(--wt-ok);">${s.doneGroups}</div>
                <div class="st-lbl">Abgeschlossen</div>
                <div class="st-bar-wrap"><div class="st-bar" style="width:${pct}%;"></div></div>
            </div>
            <div class="st-card">
                <div class="st-val" style="color:var(--wt-warn);">${s.pendingGroups}</div>
                <div class="st-lbl">Ausstehend</div>
            </div>
            <div class="st-card">
                <div class="st-val">${s.totalGroups}</div>
                <div class="st-lbl">Gruppen gesamt</div>
            </div>
            <div class="st-card">
                <div class="st-val" style="color:var(--wt-text-muted);">${s.avgFp}</div>
                <div class="st-lbl">Ø Fehlerpunkte</div>
            </div>`;

        // Top 5 + Schlusslicht
        const top5 = done.slice(0, 5);
        const last  = done.length > 5 ? done.slice(-1) : [];
        const rows  = [...top5, ...(last.length ? ['---'] : []), ...last];
        document.getElementById('stTop').innerHTML = rows.map(r => {
            if (r === '---') return `<div style="padding:4px 16px;font-size:11px;color:var(--wt-text-subtle);font-family:monospace;">⋯</div>`;
            const sc = Number(r.combined_score).toFixed(1).replace('.', ',');
            return `<div class="st-rank-row">
                <div class="st-rank-num">${r.rank}</div>
                <div>#${esc(r.group_num)} ${esc(r.group_name)}</div>
                <div class="st-rank-score">${sc}</div>
            </div>`;
        }).join('');
    }

    /* ── Views ───────────────────────────────────── */
    function showView(idx) {
        view = ((idx % VIEWS.length) + VIEWS.length) % VIEWS.length;
        document.querySelectorAll('.pv').forEach((v, i) =>
            v.classList.toggle('active', i === view));
        document.querySelectorAll('.pf-dot').forEach((d, i) =>
            d.classList.toggle('active', i === view));
    }

    let slideTimer = setInterval(() => showView(view + 1), 18_000);
    const resetSlide = () => { clearInterval(slideTimer); slideTimer = setInterval(() => showView(view + 1), 18_000); };

    document.querySelectorAll('.pf-dot').forEach(d =>
        d.addEventListener('click', () => { showView(parseInt(d.dataset.v)); resetSlide(); }));

    document.addEventListener('keydown', e => {
        if (['ArrowRight',' '].includes(e.key)) { e.preventDefault(); showView(view + 1); resetSlide(); }
        if (e.key === 'ArrowLeft')               { showView(view - 1); resetSlide(); }
        if (e.key.toLowerCase() === 'f')
            document.fullscreenElement ? document.exitFullscreen()
                                       : document.documentElement.requestFullscreen();
    });

    /* ── Live-Polling ────────────────────────────── */
    async function poll() {
        try {
            const res  = await fetch('/admin/results', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error();
            const json = await res.json();
            const d    = json.data ?? json;
            if (d.ranking) {
                ranking = d.ranking;
                const done = ranking.filter(r => r.is_complete);
                const pend = ranking.filter(r => !r.is_complete);
                stats.doneGroups    = done.length;
                stats.pendingGroups = pend.length;
                stats.totalGroups   = ranking.length;
                stats.avgFp         = done.length
                    ? Math.round(done.reduce((s, r) => s + r.total_fp, 0) / done.length * 10) / 10
                    : 0;
                stats.pct = stats.totalGroups ? Math.round(done.length / stats.totalGroups * 100) : 0;
                renderPodium(ranking);
                renderRanking(ranking);
                renderStats(ranking, stats);
            }
            document.getElementById('ph-live').textContent = '● LIVE';
            document.getElementById('ph-live').style.background = 'var(--wt-ok-soft)';
            document.getElementById('ph-ts').textContent = d.ts ?? '';
        } catch {
            document.getElementById('ph-live').textContent = '○ OFFLINE';
            document.getElementById('ph-live').style.background = 'rgba(232,70,86,.15)';
        }
    }

    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    /* ── Init ────────────────────────────────────── */
    renderPodium(ranking);
    renderRanking(ranking);
    renderStats(ranking, stats);
    poll();
    setInterval(poll, 30_000);
})();
</script>
</body>
</html>
