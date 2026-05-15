// station.js — JuMA Bewertungsfluss
// State-Machine: dashboard → checkin → scoring → confirm
// Tabs: aktiv | verlauf | profil

import { apiFetch, showMessage } from './app.js';
import { startScanner, stopScanner } from './qr.js';

// ── Daten aus PHP ─────────────────────────────
const D       = JSON.parse(document.getElementById('__JUMA__').textContent);
const station = D.station;
const tasks   = D.tasks;
const judge   = D.judge;
const csrf    = D.csrf;
const hasTime = parseInt(station.has_time) === 1;
const taskMap = Object.fromEntries(tasks.map(t => [t.id, t]));

// Debug-Ausgabe: zeigt welche Station wirklich geladen wurde
console.log('[JuMA] Page loaded — Station:', {
    dbStationId:   D.stationId,
    stationObject: station.id + ' · ' + station.code + ' · ' + station.name,
    dbJudgeId:     D.judgeId,
    judgeObject:   judge.id + ' · ' + judge.name,
    historyCount:  (D.history || []).length,
});

// ── Stopwatch (module-level, überlebt Re-Renders) ─
let swRunning = false, swBase = 0, swStart = 0, swRaf = null;
let swMs = 0;

function swTick() {
    swMs = swBase + (performance.now() - swStart);
    updateSwFace();
    swRaf = requestAnimationFrame(swTick);
}
function swStartTimer() {
    if (swRunning) return;
    swRunning = true;
    swStart   = performance.now();
    swRaf     = requestAnimationFrame(swTick);
}
function swStop() {
    if (!swRunning) return;
    swBase   += performance.now() - swStart;
    swMs      = swBase;
    swRunning = false;
    if (sw.raf) {
    cancelAnimationFrame(sw.raf);
    sw.raf = null;
}
    cancelAnimationFrame(swRaf);
}
function swReset() {
    swStop();
    swBase = swMs = 0;
    updateSwFace();
}
function swFmt(ms) {
    const t  = Math.max(0, ms);
    const m  = Math.floor(t / 60000);
    const s  = Math.floor((t % 60000) / 1000);
    const cs = Math.floor((t % 1000) / 10);
    return { main: `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`, frac: String(cs).padStart(2,'0') };
}
function updateSwFace() {
    const el = document.getElementById('sw-face');
    if (!el) return;
    const { main, frac } = swFmt(swMs);
    el.innerHTML = `${main}<span class="wt_stopwatch__ms">.${frac}</span>`;
}

// ── Multi-Stopwatch (für zeit_felder > 1) ─────
// { taskId: [{ms, running, base, start, raf}, ...] }
const multiSw = {};

function mswGet(taskId, n) {
    if (!multiSw[taskId]) {
        multiSw[taskId] = Array.from({ length: n }, () =>
            ({ ms: 0, running: false, base: 0, start: 0, raf: null }));
    }
    return multiSw[taskId];
}

function mswToggle(taskId, idx) {
    const task = taskMap[taskId];
    if (!task?.time) return;

    const arr = mswGet(taskId, task.time.zeit_felder);
    const sw  = arr[idx];

    // alten RAF sicher stoppen
    if (sw.raf) {
        cancelAnimationFrame(sw.raf);
        sw.raf = null;
    }

    if (sw.running) {

        sw.base += performance.now() - sw.start;
        sw.ms = sw.base;
        sw.running = false;

        state.scoring.taskValues = {
            ...state.scoring.taskValues,
            [taskId]: arr.map(s => Math.round(s.ms)),
        };

        updateScoringLive();

    } else {

    // zuerst ALLE anderen Timer stoppen
    arr.forEach((other, otherIdx) => {

        if (otherIdx === idx) return;

        if (other.running) {

            other.base += performance.now() - other.start;
            other.ms = other.base;
            other.running = false;

            if (other.raf) {
                cancelAnimationFrame(other.raf);
                other.raf = null;
            }
        }
    });

    // aktuellen Timer starten
    sw.start = performance.now();
    sw.running = true;

    const tick = () => {

        if (!sw.running) return;

        sw.ms = sw.base + (performance.now() - sw.start);

        const el = document.getElementById(`msw-${taskId}-${idx}`);

        if (el) {
            el.textContent = swFmt(sw.ms).main;
        }

        sw.raf = requestAnimationFrame(tick);
    };

    sw.raf = requestAnimationFrame(tick);
}

    render();
}

function mswReset(taskId, idx) {
    const task = taskMap[taskId];
    if (!task?.time) return;
    const arr = mswGet(taskId, task.time.zeit_felder);
    const sw  = arr[idx];
    cancelAnimationFrame(sw.raf);
    sw.ms = sw.base = 0;
    sw.running = false;
    state.scoring.taskValues = {
        ...state.scoring.taskValues,
        [taskId]: arr.map(s => Math.round(s.ms)),
    };
    render();
}

// ── State ─────────────────────────────────────
const state = {
    route:           'dashboard',
    tab:             'aktiv',
    group:           null,
    history:         D.history || [],
    scoring:         emptyScoring(),
    submitting:      false,
    submitted:       false,
    online:          navigator.onLine,
    queueCount:      0,
    messages:        [],
    unreadCount:     D.unreadCount || 0,
    chatInput:       '',
    allGroups:              null,
    allGroupsLoading:       false,
    stationsOverview:       null,
    stationsOverviewLoading: false,
};

function emptyScoring() {
    const taskValues = {};
    tasks.filter(t => t.type === 'boolean').forEach(t => { taskValues[t.id] = 'ok'; });
    // Multi-Timer-Arrays vorbelegen und Stopwatch-State zurücksetzen
    tasks.filter(t => t.type === 'time' && (t.time?.zeit_felder ?? 1) > 1).forEach(t => {
        taskValues[t.id] = new Array(t.time.zeit_felder).fill(0);
        delete multiSw[t.id]; // Stopwatch-State zurücksetzen
    });
    return { taskValues, impression: null };
}

// ── Sync-Status ────────────────────────────────
window.addEventListener('online',  () => { state.online = true;  rerenderSyncPill(); });
window.addEventListener('offline', () => { state.online = false; rerenderSyncPill(); });
window.addEventListener('wt:queue-count', (e) => {
    state.queueCount = e.detail ?? 0;
    rerenderSyncPill();
});

window.addEventListener('wt:synced', (e) => {
    const syncedGroupIds = new Set(e.detail);
    let changed = false;
    state.history = state.history.map(h => {
        if (!h.synced && syncedGroupIds.has(h.group_id)) {
            changed = true;
            return { ...h, synced: true };
        }
        return h;
    });
    if (changed) render();
});

function rerenderSyncPill() {
    const pills = document.querySelectorAll('.wt_sync-pill');
    pills.forEach(p => { p.outerHTML = syncPillHtml(); });
}

// ── FP-Berechnung ──────────────────────────────
function calcFp(taskValues) {
    let fp = 0;
    for (const t of tasks) {
        const v = taskValues[t.id];
        if (t.type === 'boolean' && v === 'fail') {
            fp += t.points;
        } else if (t.type === 'count' && v > 0) {
            const clamped = t.max_count !== null ? Math.min(v, t.max_count) : v;
            fp += clamped * t.points;
        }
        // Zeitstrafe
        if (t.time) {
            const felder = t.time.zeit_felder ?? 1;
            const { sollzeit_sek: soll, hoechstzeit_sek: max, zeitstrafe_fp: fpj, zeiteinheit_sek: einh } = t.time;
            const calcTimeFp = (ms) => {
                const sek  = Math.floor(ms / 1000);
                if (sek <= soll) return 0;
                const over = (max ? Math.min(sek, max) : sek) - soll;
                return Math.floor(over / einh) * fpj;
            };
            if (felder > 1) {
                const times = Array.isArray(v) ? v : (Array.isArray(taskValues[t.id]) ? taskValues[t.id] : []);
                times.forEach(ms => { if (ms > 0) fp += calcTimeFp(ms); });
            } else {
                fp += calcTimeFp(swMs);
            }
        }
    }
    return fp;
}

function allScored(taskValues) {
    return tasks.filter(t => t.type === 'boolean').every(t => taskValues[t.id]);
}

const hasTimeComponent  = hasTime || tasks.some(t => t.time);
const hasSingleTimeTask = hasTime || tasks.some(t => t.type === 'time' && (t.time?.zeit_felder ?? 1) === 1);

/** Gibt {ok, hints} zurück — hints ist ein Array mit Beschreibungen was noch fehlt */
function readyCheck(taskValues, impression) {
    const hints = [];
    if (!impression) hints.push('Eindruck wählen');

    for (const t of tasks) {
        if (t.type !== 'time' || !t.time) continue;
        const felder = t.time.zeit_felder ?? 1;
        if (felder > 1) {
            const arr = mswGet(t.id, felder);
            if (arr.some(sw => sw.running))       hints.push('Alle Zeiten anhalten');
            else if (arr.every(sw => sw.ms === 0)) hints.push('Zeiten erfassen (T1–T' + felder + ')');
        } else {
            if (swMs === 0)     hints.push('Zeit stoppen');
            else if (swRunning) hints.push('Stoppuhr anhalten');
        }
    }
    // Fallback: station has_time ohne dedizierten time-Task
    if (hasTime && !tasks.some(t => t.type === 'time')) {
        if (swMs === 0)     hints.push('Zeit stoppen');
        else if (swRunning) hints.push('Stoppuhr anhalten');
    }
    return { ok: hints.length === 0, hints };
}

// ── HTML-Helfer ────────────────────────────────
// esc: für Attributwerte (inkl. Anführungszeichen)
const esc = (v) => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
// escT: für Text-Inhalte (Anführungszeichen müssen NICHT escaped werden)
const escT = (v) => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

function syncPillHtml() {
    const offline = !state.online;
    const dot = offline ? 'wt_pulse--offline' : 'wt_pulse--online';
    const label = offline
        ? `OFFLINE${state.queueCount > 0 ? ` · ${state.queueCount} ausstehend` : ''}`
        : 'WERTUNGSZENTRALE · LIVE';
    return `<span class="wt_sync-pill"><span class="wt_pulse ${dot}"></span>${label}</span>`;
}

function topHeaderHtml(showBack = false) {
    const left = showBack
        ? `<button class="wt_btn wt_btn--ghost" id="btnBack" style="height:32px;padding:0 10px 0 6px;font-size:12px;gap:4px;">
               <svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M12 4l-6 6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
               Zurück</button>`
        : `<span class="wt_station-chip"><span class="wt_dot"></span>${esc(station.code)} · ${esc(station.name)}</span>`;
    return `<div class="wt_top-header">${left}${syncPillHtml()}</div>`;
}

function tabBarHtml() {
    const t = (id, icon, label, badge = 0) => {
        const badgeHtml = badge > 0
            ? `<span class="wt_tab-badge">${badge > 9 ? '9+' : badge}</span>`
            : '';
        return `<button class="wt_tabbar__btn${state.tab===id?' wt_tabbar__btn--active':''}" data-tab="${id}">
            <span style="position:relative;display:inline-block;">${icon}${badgeHtml}</span>
            <span>${label}</span></button>`;
    };
    return `<div class="wt_tabbar">
        ${t('aktiv',    svgFlame(),   'Aktiv')}
        ${t('verlauf',  svgList(),    'Verlauf')}
        ${t('live',     svgGrid(),    'Live')}
        ${t('zentrale', svgChat(),    'Zentrale', state.unreadCount)}
        ${t('profil',   svgUser(),    'Profil')}
    </div>`;
}

function progressHtml(step) {
    const steps = [1,2,3].map(i =>
        `<div class="wt_progress-step${i<step?' wt_progress-step--done':i===step?' wt_progress-step--active':''}"></div>`
    ).join('');
    const labels = ['','Anmeldung','Bewertung','Bestätigung'];
    return `<div class="wt_progress-track">${steps}</div>
            <div class="wt_caption" style="display:flex;justify-content:space-between;margin-top:2px;">
              <span>Schritt ${step}/3 · ${labels[step]}</span>
              <span class="wt_mono">${esc(station.code)}</span>
            </div>`;
}

// ── Screens ────────────────────────────────────
function scoreCardColor(fp) {
    return fp > 10 ? { c: 'var(--wt-red)', bg: 'var(--wt-red-tint)' }
                   : { c: 'var(--wt-ok)',  bg: 'var(--wt-ok-soft)'  };
}

function scoreCardHtml(h, idx) {
    const { c, bg } = scoreCardColor(h.total_fp);
    return `
    <button class="wt_score-card" data-score-idx="${idx}">
        <div class="wt_group-num" style="background:${bg};color:${c};">#${esc(h.group_num)}</div>
        <div style="flex:1;min-width:0;">
            <div style="font-size:14.5px;font-weight:600;">${esc(h.group_name)}</div>
            <div class="wt_group-meta">
                <span>${esc(h.timestamp)}</span><span class="wt_sep"></span>
                <span style="color:${h.synced?'var(--wt-ok)':'var(--wt-warn)'};">${h.synced?'übermittelt':'ausstehend'}</span>
            </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
            <div class="wt_mono" style="font-size:18px;font-weight:700;color:${c};">${h.total_fp}</div>
            <div class="wt_caption" style="font-size:10px;">FP</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="color:var(--wt-text-subtle);flex-shrink:0;"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>`;
}

function renderDashboard() {
    const last3 = state.history.slice(0, 3);
    return `
        ${topHeaderHtml()}
        <div class="wt_scroll wt_fade-in">
            <div class="wt_section" style="padding-top:16px;">
                <div class="wt_eyebrow">An deiner Station</div>
                <h2 class="wt_h1" style="margin-top:4px;">Bereit für<br>nächste Gruppe.</h2>
            </div>
            <div class="wt_section">
                <button class="wt_btn wt_btn--primary wt_btn--block wt_cta-btn" id="btnCheckin">
                    ${svgQr()} Gruppen-QR scannen
                </button>
                <div class="wt_caption" style="text-align:center;margin-top:8px;">
                    Gruppe meldet sich mit Karte an deiner Station an
                </div>
            </div>
            ${last3.length > 0 ? `
            <div class="wt_section">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0 4px 10px;">
                    <span class="wt_eyebrow">Letzte Bewertungen</span>
                    <span class="wt_caption wt_mono">${state.history.length} gesamt</span>
                </div>
                ${last3.map((h, i) => scoreCardHtml(h, i)).join('')}
            </div>` : ''}
        </div>
        ${tabBarHtml()}`;
}

function renderCheckin() {
    return `
        ${topHeaderHtml(true)}
        <div class="wt_scroll wt_fade-in" style="padding-bottom:32px;">
            <div class="wt_section" style="padding-top:16px;">
                ${progressHtml(1)}
                <h2 class="wt_h1" style="margin-top:14px;">Karte der Gruppe<br>scannen.</h2>
            </div>
            <div class="wt_section">
                <div class="wt_viewfinder" id="qrContainer">
                    <span class="wt_viewfinder__corner wt_viewfinder__corner--tl"></span>
                    <span class="wt_viewfinder__corner wt_viewfinder__corner--tr"></span>
                    <span class="wt_viewfinder__corner wt_viewfinder__corner--bl"></span>
                    <span class="wt_viewfinder__corner wt_viewfinder__corner--br"></span>
                    <span class="wt_viewfinder__scanline" id="scanline"></span>
                </div>
            </div>
            <div class="wt_section">
                <div class="wt_caption" style="text-align:center;">
                    Halte die Kamera ruhig auf die Gruppen-Karte
                </div>
            </div>
        </div>`;
}

function renderGroupConfirm(group) {
    return `
        ${topHeaderHtml(true)}
        <div class="wt_scroll wt_fade-in" style="padding-bottom:32px;">
            <div class="wt_section" style="padding-top:16px;">
                ${progressHtml(1)}
                <h2 class="wt_h1" style="margin-top:14px;">Gruppe #${esc(group.group_num)}.</h2>
            </div>
            <div class="wt_section">
                <div class="wt_card" style="padding:16px;display:flex;align-items:center;gap:12px;">
                    <div class="wt_group-num" style="background:var(--wt-ok-soft);color:var(--wt-ok);">#${esc(group.group_num)}</div>
                    <div style="flex:1;">
                        <div style="font-size:15px;font-weight:700;">${esc(group.group_name)}</div>
                        <div class="wt_group-meta">
                            <span>${esc(group.kreis||'')}</span>
                            ${group.altersgruppe?`<span class="wt_sep"></span><span>${esc(group.altersgruppe)}</span>`:''}
                            ${group.startnr?`<span class="wt_sep"></span><span>${esc(group.startnr)}</span>`:''}
                        </div>
                    </div>
                    <span class="wt_total-pill" style="background:var(--wt-ok-soft);color:var(--wt-ok);">OK</span>
                </div>
            </div>
            ${group.members && group.members.length > 0 ? `
            <div class="wt_section">
                <button class="wt_btn wt_btn--ghost wt_btn--block" id="btnGroupInfo">
                    ${svgInfo()} Gruppen-Info (${group.members.length} Teilnehmer)
                </button>
            </div>` : ''}
            ${group.already_scored ? `
            <div class="wt_section">
                <div class="wt_card" style="padding:16px;border:2px solid var(--wt-warn);background:rgba(245,158,11,.07);">
                    <div style="font-weight:700;font-size:14px;color:var(--wt-warn);margin-bottom:6px;">⚠ Bereits bewertet</div>
                    <div style="font-size:13px;color:var(--wt-text-muted);">
                        Diese Gruppe wurde an dieser Station bereits bewertet
                        ${group.existing_judge ? ` von <strong>${esc(group.existing_judge)}</strong>` : ''}.
                        Ergebnis: <strong class="wt_mono">${group.existing_fp} FP</strong>
                    </div>
                </div>
            </div>
            <div class="wt_section">
                <button class="wt_btn wt_btn--ghost wt_btn--block" id="btnBack" style="height:48px;">
                    Zurück zum Dashboard
                </button>
            </div>` : `
            <div class="wt_section">
                <button class="wt_btn wt_btn--primary wt_btn--block" id="btnStartScoring" style="height:56px;font-size:16px;">
                    Bewertung starten ${svgArrow()}
                </button>
            </div>`}
        </div>`;
}

function renderScoring() {
    const g   = state.group;
    const tv  = state.scoring.taskValues;
    const fp  = calcFp(tv);
    const imp = state.scoring.impression;
    const { ok: ready, hints } = readyCheck(tv, imp);

    const boolTasks       = tasks.filter(t => t.type === 'boolean');
    const countTasks      = tasks.filter(t => t.type === 'count');
    const singleTimeTasks = tasks.filter(t => t.type === 'time' && (t.time?.zeit_felder ?? 1) === 1);
    const multiTimeTasks  = tasks.filter(t => t.type === 'time' && (t.time?.zeit_felder ?? 1) > 1);
    const hasTimeTasks    = tasks.some(t => t.time);
    const showSingleSw    = hasTime || singleTimeTasks.length > 0;

    return `
        ${topHeaderHtml(true)}
        <div class="wt_scroll wt_fade-in">
            <div class="wt_section" style="padding-top:12px;">
                <div class="wt_group-banner">
                    <div class="wt_group-num">#${esc(g.group_num)}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:15px;font-weight:700;">${esc(g.group_name)}</div>
                        <div class="wt_mono" style="font-size:11.5px;color:rgba(255,255,255,0.6);font-weight:500;margin-top:2px;">
                            ${esc(g.kreis||'')}${g.altersgruppe?` · ${esc(g.altersgruppe)}`:''}
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="wt_mono" id="fp-live" style="font-size:24px;font-weight:600;">${fp}</div>
                        <div class="wt_caption" style="color:rgba(255,255,255,0.55);">FP</div>
                    </div>
                </div>
                <div style="margin-top:14px;">${progressHtml(2)}</div>
            </div>

            ${showSingleSw ? `
            <div class="wt_section">
                <div class="wt_eyebrow" style="padding:0 4px 8px;">Zeit</div>
                <div class="wt_card">
                    <div class="wt_stopwatch">
                        <div class="wt_stopwatch__face" id="sw-face">
                            ${(() => { const {main,frac} = swFmt(swMs); return `${main}<span class="wt_stopwatch__ms">.${frac}</span>`; })()}
                        </div>
                        <div class="wt_stopwatch__controls">
                            <button class="wt_btn${swRunning?' wt_btn--dark':' wt_btn--primary'}" id="btnSwToggle">
                                ${swRunning ? 'Stopp' : swMs > 0 ? 'Weiter' : 'Start'}
                            </button>
                            <button class="wt_btn wt_btn--ghost" id="btnSwReset" style="flex:0;padding:0 18px;">↺</button>
                        </div>
                    </div>
                    ${singleTimeTasks.map(t => {
                        const fmt = s => `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;
                        return `<div style="padding:10px 16px 0;border-top:1px solid var(--wt-border);display:flex;gap:16px;font-size:12px;color:var(--wt-text-subtle);">
                            <span>Soll: <strong>${fmt(t.time.sollzeit_sek)}</strong></span>
                            ${t.time.hoechstzeit_sek ? `<span>Max: <strong>${fmt(t.time.hoechstzeit_sek)}</strong></span>` : ''}
                            <span>${t.time.zeitstrafe_fp} FP / ${t.time.zeiteinheit_sek}s</span>
                        </div>`;
                    }).join('')}
                </div>
            </div>` : ''}

            ${multiTimeTasks.map(t => {
                const felder = t.time.zeit_felder;
                const arr    = mswGet(t.id, felder);
                const fmt    = s => `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;
                const rows   = arr.map((sw, i) => `
                    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--wt-border);">
                        <div style="width:28px;height:28px;border-radius:8px;background:var(--wt-surface-alt);display:flex;align-items:center;justify-content:center;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;color:var(--wt-text-muted);flex-shrink:0;">T${i+1}</div>
                        <div id="msw-${t.id}-${i}" style="flex:1;font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:600;letter-spacing:-0.02em;">
                            ${swFmt(sw.ms).main}
                        </div>
                        <button class="wt_btn ${sw.running ? 'wt_btn--dark' : sw.ms > 0 ? 'wt_btn--ghost' : 'wt_btn--primary'}" style="min-width:74px;height:36px;font-size:13px;"
                                data-msw="${t.id}" data-msw-idx="${i}">
                            ${sw.running ? 'Stopp' : sw.ms > 0 ? 'Weiter' : 'Start'}
                        </button>
                        <button class="wt_btn wt_btn--ghost" style="flex:0;height:36px;padding:0 12px;"
                                data-msw-reset="${t.id}" data-msw-idx="${i}">↺</button>
                    </div>`).join('');
                return `
                <div class="wt_section">
                    <div class="wt_eyebrow" style="padding:0 4px 8px;">${esc(t.label)}</div>
                    <div class="wt_card">
                        ${rows}
                        <div style="padding:10px 16px;font-size:12px;color:var(--wt-text-subtle);border-top:1px solid var(--wt-border);">
                            Soll: <strong>${fmt(t.time.sollzeit_sek)}</strong>
                            ${t.time.hoechstzeit_sek ? ` / Max: <strong>${fmt(t.time.hoechstzeit_sek)}</strong>` : ''}
                            &nbsp;·&nbsp; ${t.time.zeitstrafe_fp} FP / ${t.time.zeiteinheit_sek}s je TN
                        </div>
                    </div>
                </div>`;
            }).join('')}

            ${boolTasks.length > 0 ? `
            <div class="wt_section">
                <div class="wt_eyebrow" style="padding:0 4px 8px;">Kriterien</div>
                <div class="wt_card">
                    ${boolTasks.map(t => `
                    <div class="wt_row-line">
                        <div style="min-width:0;flex:1;">
                            <div class="wt_row-label">${esc(t.label)}</div>
                            <div class="wt_row-sub">${t.points} FP bei Fehler</div>
                        </div>
                        <div class="wt_check-toggle">
                            <button class="wt_check-toggle__ok${tv[t.id]==='ok'?' wt_check-toggle__ok--active':''}" data-task="${t.id}" data-val="ok">Korrekt</button>
                            <button class="wt_check-toggle__fail${tv[t.id]==='fail'?' wt_check-toggle__fail--active':''}" data-task="${t.id}" data-val="fail">Fehler</button>
                        </div>
                    </div>`).join('')}
                </div>
            </div>` : ''}

            ${countTasks.length > 0 ? `
            <div class="wt_section">
                <div class="wt_eyebrow" style="padding:0 4px 8px;">Fehlerpunkte je Teilnehmer</div>
                <div class="wt_card">
                    ${countTasks.map(t => {
                        const val    = tv[t.id] ?? 0;
                        const maxVal = t.max_count !== null ? t.max_count : 999;
                        const sub    = t.max_count !== null
                            ? `${t.points} FP je Verstoß · max ${t.max_count}`
                            : `${t.points} FP je Verstoß`;
                        return `
                    <div class="wt_row-line">
                        <div style="min-width:0;flex:1;">
                            <div class="wt_row-label">${esc(t.label)}</div>
                            <div class="wt_row-sub">${sub}</div>
                        </div>
                        <div class="wt_stepper">
                            <button class="wt_stepper__btn" data-stepper="${t.id}" data-dir="-1" ${val===0?'disabled':''}>−</button>
                            <span class="wt_stepper__val" data-stepper-val="${t.id}">${val}</span>
                            <button class="wt_stepper__btn" data-stepper="${t.id}" data-dir="1" data-max="${maxVal}" ${val>=maxVal?'disabled':''}>+</button>
                        </div>
                    </div>`; }).join('')}
                </div>
            </div>` : ''}

            <div class="wt_section">
                <div class="wt_eyebrow" style="padding:0 4px 8px;">Eindruck der Gruppe</div>
                <div class="wt_card">
                    <div class="wt_impression-grid">
                        ${[['sehr_gut','01','Sehr gut'],['gut','02','Gut'],['befriedigend','03','Befriedigend']].map(([id,num,label]) => `
                        <button class="wt_impression-btn${imp===id?' wt_impression-btn--active':''}" data-impression="${id}">
                            <span class="wt_impression-btn__num">${num}</span>
                            <span class="wt_impression-btn__label">${label}</span>
                        </button>`).join('')}
                    </div>
                </div>
            </div>

            <div class="wt_section">
                <button class="wt_btn wt_btn--primary wt_btn--block" id="btnToConfirm"
                    style="height:56px;font-size:16px;" ${ready?'':'disabled'}>
                    Zur Bestätigung ${svgArrow()}
                </button>
                <div id="scoringHint" class="wt_caption" style="text-align:center;margin-top:10px;color:var(--wt-warn);font-weight:600;">
                    ${!ready ? hints.map(h => '· ' + h).join('  ') : ''}
                </div>
            </div>
        </div>`;
}

function renderConfirm() {
    const g  = state.group;
    const tv = state.scoring.taskValues;
    const fp = calcFp(tv);

    const fmtMs  = ms => { const m = Math.floor(ms/60000); const s = Math.floor((ms%60000)/1000); return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`; };
    const fmtSek = s  => `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;

    const failItems = tasks.flatMap(t => {
        if (t.type === 'boolean' && tv[t.id] === 'fail') {
            return [{ label: t.label, fp: t.points, sub: null }];
        }
        if (t.type === 'count' && (tv[t.id] ?? 0) > 0) {
            const count = t.max_count !== null ? Math.min(tv[t.id], t.max_count) : tv[t.id];
            return [{ label: t.label, fp: count * t.points, sub: `${count} × ${t.points} FP` }];
        }
        if (t.type === 'time' && t.time) {
            const { sollzeit_sek: soll, hoechstzeit_sek: max, zeitstrafe_fp: fpj, zeiteinheit_sek: einh, zeit_felder: felder = 1 } = t.time;
            const calcTimeFp = ms => {
                const sek  = Math.floor(ms / 1000);
                if (sek <= soll) return 0;
                const over = (max ? Math.min(sek, max) : sek) - soll;
                return Math.floor(over / einh) * fpj;
            };
            if (felder > 1) {
                const times  = Array.isArray(tv[t.id]) ? tv[t.id] : [];
                const totalFp = times.reduce((sum, ms) => sum + calcTimeFp(ms), 0);
                if (totalFp > 0) {
                    const sub = times.map((ms, i) => { const p = calcTimeFp(ms); return p > 0 ? `T${i+1}: ${fmtMs(ms)} (+${p})` : null; }).filter(Boolean).join(' · ');
                    return [{ label: t.label, fp: totalFp, sub }];
                }
            } else {
                const fp = calcTimeFp(swMs);
                if (fp > 0) {
                    return [{ label: t.label, fp, sub: `${fmtMs(swMs)} · Soll ${fmtSek(soll)}` }];
                }
            }
        }
        return [];
    });
    const imp = { sehr_gut:'Sehr gut', gut:'Gut', befriedigend:'Befriedigend' }[state.scoring.impression] || '–';

    const submitting = state.submitting;
    const done       = state.submitted;

    return `
        ${topHeaderHtml(!submitting && !done)}
        <div class="wt_scroll wt_fade-in">
            <div class="wt_section" style="padding-top:12px;">
                ${progressHtml(3)}
                <h2 class="wt_h1" style="margin-top:14px;">Übermitteln<br>an Wertung.</h2>
            </div>
            <div class="wt_section">
                <div class="wt_card" style="padding:20px;">
                    <div class="wt_eyebrow" style="margin-bottom:10px;">Zusammenfassung</div>
                    <div style="display:flex;align-items:flex-end;gap:12px;">
                        <div class="wt_summary-total">${fp}</div>
                        <div style="padding-bottom:6px;">
                            <div class="wt_caption">Fehlerpunkte gesamt</div>
                        </div>
                    </div>
                    <div style="height:1px;background:var(--wt-border);margin:16px 0;"></div>
                    <div class="wt_summary-row"><span class="wt_summary-row__label">Gruppe</span><span class="wt_summary-row__value">#${esc(g.group_num)} · ${esc(g.group_name)}</span></div>
                    <div class="wt_summary-row"><span class="wt_summary-row__label">Station</span><span class="wt_summary-row__value">${esc(station.code)} · ${esc(station.name)}</span></div>
                    <div class="wt_summary-row"><span class="wt_summary-row__label">Schiedsrichter</span><span class="wt_summary-row__value">${esc(judge.name)}</span></div>
                    ${hasTime && swMs > 0 ? `
                    <div class="wt_summary-row">
                        <span class="wt_summary-row__label">Zeit</span>
                        <span class="wt_summary-row__value wt_mono">${swFmt(swMs).main}</span>
                    </div>` : ''}
                    ${failItems.length > 0 ? `
                    <div style="height:1px;background:var(--wt-border);margin:16px 0;"></div>
                    <div class="wt_eyebrow" style="margin-bottom:10px;color:var(--wt-red);">Fehler (${failItems.length})</div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        ${failItems.map(f => `
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                            <span style="font-size:13px;color:var(--wt-text);">${esc(f.label)}</span>
                            <span class="wt_total-pill" style="white-space:nowrap;flex-shrink:0;">${f.sub ? esc(f.sub) + ' = ' : ''}+${f.fp} FP</span>
                        </div>`).join('')}
                    </div>` : ''}
                    <div style="height:1px;background:var(--wt-border);margin:16px 0;"></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="wt_eyebrow">Eindruck</div>
                        <span class="wt_total-pill" style="background:var(--wt-ok-soft);color:var(--wt-ok);">${imp}</span>
                    </div>
                </div>
            </div>
            <div class="wt_section" style="display:flex;flex-direction:column;gap:10px;padding-bottom:32px;">
                <button class="wt_btn wt_btn--primary wt_btn--block" id="btnSend"
                    style="height:56px;font-size:16px;" ${submitting||done?'disabled':''}>
                    ${submitting ? 'Übertrage…' : done ? 'Übermittelt ✓' : `Senden an Wertungszentrale ${svgArrow()}`}
                </button>
                ${!submitting && !done ? `
                <button class="wt_btn wt_btn--ghost wt_btn--block" id="btnBackToScoring">
                    Korrigieren
                </button>` : ''}
            </div>
        </div>
        ${(submitting || done) ? `
        <div class="wt_sync-overlay">
            ${submitting ? `
            <div class="wt_sync-spinner"></div>
            <div style="text-align:center;">
                <div style="font-size:16px;font-weight:700;margin-bottom:4px;">Wird übertragen…</div>
                <div class="wt_caption wt_mono">→ Wertungszentrale · ${esc(station.code)}</div>
            </div>` : `
            <div class="wt_sync-check">
                <svg width="32" height="32" viewBox="0 0 18 18" fill="none">
                    <path d="M3.5 9.5l4 4 7-9" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div style="text-align:center;">
                <div style="font-size:18px;font-weight:700;margin-bottom:4px;">Übermittelt!</div>
                <div class="wt_caption wt_mono">${fp} Fp · Gruppe #${esc(g.group_num)}</div>
            </div>`}
        </div>` : ''}`;
}

function renderHistory() {
    // Daten laden wenn noch nicht vorhanden — deferred, damit kein Re-Render mitten im Render
    if (!state.allGroups && !state.allGroupsLoading) {
        setTimeout(loadAllGroups, 0);
    }

    const groups  = state.allGroups || [];
    const scored  = groups.filter(g => g.score_id);
    const pending = groups.filter(g => !g.score_id);

    const groupRowHtml = (g) => {
        const hasFp  = g.score_id !== null;
        const { c, bg } = scoreCardColor(hasFp ? g.total_fp : 0);
        return `
        <div class="wt_group-row${hasFp ? '' : ' wt_group-row--pending'}">
            <div class="wt_group-num" style="${hasFp ? `background:${bg};color:${c};` : ''}">#${esc(g.group_num)}</div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:14px;font-weight:600;">${esc(g.group_name)}</div>
                <div class="wt_group-meta">
                    ${g.kreis ? `<span>${esc(g.kreis)}</span>` : ''}
                    ${g.altersgruppe ? `<span class="wt_sep"></span><span>${esc(g.altersgruppe)}</span>` : ''}
                    ${hasFp ? `<span class="wt_sep"></span><span style="color:var(--wt-text-subtle);">${esc(g.scored_at ? g.scored_at.substring(11,16) : '')}</span>` : ''}
                </div>
            </div>
            ${hasFp
                ? `<div style="text-align:right;flex-shrink:0;">
                       <div class="wt_mono" style="font-size:16px;font-weight:700;color:${c};">${g.total_fp}</div>
                       <div class="wt_caption" style="font-size:10px;">FP</div>
                   </div>`
                : `<span class="wt_caption" style="flex-shrink:0;color:var(--wt-text-subtle);">ausstehend</span>`}
        </div>`;
    };

    return `
        ${topHeaderHtml()}
        <div class="wt_scroll wt_fade-in">
            <div class="wt_section" style="padding-top:16px;">
                <div class="wt_eyebrow">Verlauf · ${esc(station.code)}</div>
                <h2 class="wt_h1" style="margin-top:4px;">${scored.length} von ${groups.length}<br>bewertet.</h2>
            </div>

            ${state.allGroupsLoading ? `
            <div class="wt_section">
                <div class="wt_caption" style="text-align:center;padding:32px 0;">Lade Gruppen…</div>
            </div>` : ''}

            ${scored.length > 0 ? `
            <div class="wt_section">
                <div class="wt_eyebrow" style="padding:0 4px 10px;color:var(--wt-ok);">Bewertet (${scored.length})</div>
                <div class="wt_card" style="overflow:hidden;">
                    ${scored.map(groupRowHtml).join('')}
                </div>
            </div>` : ''}

            ${pending.length > 0 ? `
            <div class="wt_section">
                <div class="wt_eyebrow" style="padding:0 4px 10px;">Ausstehend (${pending.length})</div>
                <div class="wt_card" style="overflow:hidden;">
                    ${pending.map(groupRowHtml).join('')}
                </div>
            </div>` : ''}

            ${!state.allGroupsLoading && groups.length === 0 ? `
            <div class="wt_section">
                <div class="wt_caption" style="text-align:center;padding:32px 0;">Keine Gruppen gefunden.</div>
            </div>` : ''}
        </div>
        ${tabBarHtml()}`;
}

// ── Live-Karte (Leaflet) ──────────────────────
let lMap = null;

async function loadStationsOverview() {
    if (state.stationsOverviewLoading) return;
    state.stationsOverviewLoading = true; // direkte Mutation — kein Re-Render, kein Spinner-Flash
    try {
        const data = await apiFetch('/api/stations/overview');
        state.stationsOverview = data.stations || [];
    } catch {
        state.stationsOverview = state.stationsOverview ?? [];
    }
    state.stationsOverviewLoading = false;
    if (state.tab !== 'live') return;

    // Map-Container im DOM? → nur Marker aktualisieren; sonst neu rendern (zeigt Map-div)
    if (lMap && document.getElementById('wt-live-map')) {
        updateLiveMarkers();
    } else {
        if (lMap) { try { lMap.remove(); } catch { /**/ } lMap = null; }
        render();
    }
}

function renderStationsOverview() {
    // Noch keine Daten → Laden anstoßen
    if (state.stationsOverview === null && !state.stationsOverviewLoading) {
        setTimeout(loadStationsOverview, 0);
    }

    let inner;
    if (state.stationsOverview === null) {
        // Erster Ladevorgang — Spinner zeigen
        inner = `<div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;color:var(--wt-text-subtle);">
            <div class="wt_sync-spinner"></div>
            <span class="wt_caption">Lade Stationen…</span>
        </div>`;
    } else {
        const withCoords = state.stationsOverview.filter(s => s.lat !== null && s.lng !== null);
        if (withCoords.length === 0) {
            inner = `<div style="flex:1;display:flex;align-items:center;justify-content:center;">
                <span class="wt_caption">Für keine Station sind Koordinaten hinterlegt.</span>
            </div>`;
        } else {
            inner = `<div id="wt-live-map" style="flex:1;position:relative;z-index:0;"></div>`;
        }
    }

    const refreshBtn = state.stationsOverview !== null ? `
        <div style="position:absolute;bottom:calc(56px + env(safe-area-inset-bottom));right:12px;z-index:500;">
            <button id="btnRefreshOverview" style="
                height:34px;padding:0 12px;border-radius:10px;border:1px solid var(--wt-border);
                background:var(--wt-surface);color:var(--wt-text);font-family:inherit;
                font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;
                box-shadow:0 2px 8px rgba(0,0,0,.12);">
                ${svgRefresh()} Aktualisieren
            </button>
        </div>` : '';

    return `
        ${topHeaderHtml()}
        ${inner}
        ${refreshBtn}
        ${tabBarHtml()}`;
}

function initLiveMap() {
    const container = document.getElementById('wt-live-map');
    if (!container || typeof L === 'undefined') return;

    // Alte Instanz sauber entfernen
    if (lMap) {
        try { lMap.remove(); } catch { /* ignorieren */ }
        lMap = null;
    }

    lMap = L.map(container, { zoomControl: true, attributionControl: true });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(lMap);

    updateLiveMarkers();
}

function updateLiveMarkers() {
    if (!lMap) return;

    // Alle vorhandenen Marker entfernen
    lMap.eachLayer(layer => {
        if (layer instanceof L.Marker) lMap.removeLayer(layer);
    });

    const stations = state.stationsOverview || [];
    const withCoords = stations.filter(s => s.lat !== null && s.lng !== null);

    if (withCoords.length === 0) return;

    const bounds = [];

    withCoords.forEach(s => {
        const isOwn     = s.id === D.stationId;
        const groups    = s.groups || [];
        const hasGroups = groups.length > 0;

        // Farben: eigene Station blau, mit Gruppe grün, leer grau
        const bg = isOwn ? '#3b82f6' : hasGroups ? '#22c55e' : '#94a3b8';
        const border = isOwn ? '#1d4ed8' : hasGroups ? '#15803d' : '#64748b';

        const badgeHtml = hasGroups
            ? `<div style="
                position:absolute;top:-5px;right:-5px;
                background:#ef4444;color:#fff;border-radius:50%;
                width:16px;height:16px;font-size:9px;font-weight:800;
                display:flex;align-items:center;justify-content:center;
                border:1.5px solid #fff;line-height:1;">${groups.length}</div>`
            : '';

        const icon = L.divIcon({
            className: '',
            html: `<div style="position:relative;">
                <div style="
                    background:${bg};color:#fff;border-radius:50%;
                    width:38px;height:38px;display:flex;align-items:center;justify-content:center;
                    font-family:monospace;font-size:11px;font-weight:800;letter-spacing:-0.02em;
                    box-shadow:0 2px 10px rgba(0,0,0,.28);border:2.5px solid ${border};
                    white-space:nowrap;">${esc(s.code)}</div>
                ${badgeHtml}
            </div>`,
            iconSize: [38, 38],
            iconAnchor: [19, 19],
            popupAnchor: [0, -22],
        });

        const groupRows = groups.length === 0
            ? `<div style="padding:6px 0;color:#888;font-size:12px;font-style:italic;">Keine Gruppe anwesend</div>`
            : groups.map(g => `
                <div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid #eee;">
                    <span style="font-size:11px;font-weight:700;color:#666;">#${esc(g.group_num)}</span>
                    <span style="font-size:12.5px;font-weight:600;">${esc(g.group_name)}</span>
                    ${g.kreis ? `<span style="font-size:11px;color:#999;">${esc(g.kreis)}</span>` : ''}
                </div>`).join('');

        const popupContent = `
            <div style="min-width:170px;max-width:220px;font-family:system-ui,sans-serif;">
                <div style="font-weight:800;font-size:13px;margin-bottom:2px;color:#111;">
                    ${esc(s.code)} · ${esc(s.name)}
                </div>
                ${isOwn ? `<div style="font-size:10.5px;font-weight:600;color:#3b82f6;margin-bottom:6px;">Deine Station</div>` : '<div style="margin-bottom:6px;"></div>'}
                ${groupRows}
            </div>`;

        L.marker([s.lat, s.lng], { icon })
         .bindPopup(popupContent, { maxWidth: 240 })
         .addTo(lMap);

        bounds.push([s.lat, s.lng]);
    });

    if (bounds.length > 0) {
        lMap.fitBounds(bounds, { padding: [48, 48], maxZoom: 16 });
    }
}

async function loadAllGroups() {
    if (state.allGroupsLoading) return;
    setState({ allGroupsLoading: true });
    try {
        const data = await apiFetch('/api/station/groups');
        console.log('[JuMA] /api/station/groups — API station_id:', data.debug_station_id,
                    '| Page station_id:', D.stationId,
                    '| Match:', data.debug_station_id === D.stationId ? '✓' : '✗ MISMATCH!');
        setState({ allGroups: data.groups || [], allGroupsLoading: false });
    } catch {
        setState({ allGroups: [], allGroupsLoading: false });
    }
}

function renderChat() {
    const msgs = state.messages;
    const fmtTime = (iso) => {
        if (!iso) return '';
        const d = new Date(iso.replace(' ', 'T'));
        return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    };

    const bubblesHtml = msgs.length === 0
        ? `<div class="wt_caption" style="text-align:center;padding:40px 0;">Noch keine Nachrichten von der Wertungszentrale.</div>`
        : msgs.map(m => {
            const isJudge = m.sender === 'judge';
            return `
            <div style="display:flex;flex-direction:column;align-items:${isJudge?'flex-end':'flex-start'};">
                <div class="wt_chat-bubble wt_chat-bubble--${isJudge?'judge':'zentrale'}">${escT(m.body)}</div>
                <div class="wt_chat-meta${isJudge?' wt_chat-meta--right':''}">${isJudge ? 'Du' : 'Zentrale'} · ${fmtTime(m.created_at)}</div>
            </div>`;
        }).join('');

    return `
        ${topHeaderHtml()}
        <div class="wt_scroll wt_fade-in wt_scroll--chat" id="chatScroll">
            <div class="wt_section" style="padding-top:16px;">
                <div class="wt_eyebrow">Wertungszentrale</div>
                <h2 class="wt_h1" style="margin-top:4px;">Nachrichten.</h2>
            </div>
            <div class="wt_chat-messages" id="chatMessages">
                ${bubblesHtml}
            </div>
        </div>
        <div class="wt_chat-input-bar">
            <textarea id="chatInput" rows="1" placeholder="Nachricht an Zentrale…">${esc(state.chatInput)}</textarea>
            <button class="wt_chat-send-btn" id="btnChatSend" ${state.chatInput.trim() ? '' : 'disabled'}>
                ${svgSend()}
            </button>
        </div>
        ${tabBarHtml()}`;
}

async function loadMessages() {
    try {
        const data      = await apiFetch('/api/messages');
        const newMsgs   = data.messages || [];
        const unread = state.tab === 'zentrale' ? 0 : (parseInt(data.unread) || 0);

        state.messages = newMsgs;

        if (state.tab === 'zentrale') {
            // Chat-Tab offen: als gelesen markieren, Chat neu rendern
            if (state.unreadCount > 0) {
                apiFetch('/api/messages/read', { method: 'POST' }).catch(() => {});
            }
            state.unreadCount = 0;
            render();
            setTimeout(() => {
                const el = document.getElementById('chatScroll');
                if (el) el.scrollTop = el.scrollHeight;
            }, 50);
        } else {
            // Nicht im Chat: nur Badge aktualisieren, KEIN Re-Render
            const changed = unread !== state.unreadCount;
            state.unreadCount = unread;
            if (changed) updateTabBadge();
        }
    } catch { /* Offline – ignorieren */ }
}

/** Aktualisiert nur den Zentrale-Badge in der Tab-Bar ohne Re-Render */
function updateTabBadge() {
    const btn = root.querySelector('[data-tab="zentrale"]');
    if (!btn) return;
    let badge = btn.querySelector('.wt_tab-badge');
    if (state.unreadCount > 0) {
        if (!badge) {
            const iconWrap = btn.querySelector('span');
            if (iconWrap) {
                badge = document.createElement('span');
                badge.className = 'wt_tab-badge';
                iconWrap.appendChild(badge);
            }
        }
        if (badge) badge.textContent = state.unreadCount > 9 ? '9+' : state.unreadCount;
    } else if (badge) {
        badge.remove();
    }
}

async function sendChatMessage() {
    const body = state.chatInput.trim();
    if (!body) return;
    setState({ chatInput: '' });
    try {
        await apiFetch('/api/messages', { method: 'POST', body: JSON.stringify({ body }) });
        loadMessages();
    } catch (err) {
        showMessage('Senden fehlgeschlagen', 'error');
    }
}

function renderProfile() {
    return `
        ${topHeaderHtml()}
        <div class="wt_scroll wt_fade-in">
            <div class="wt_section" style="padding-top:16px;">
                <div class="wt_eyebrow">Profil</div>
                <div style="display:flex;gap:14px;align-items:center;margin-top:14px;">
                    <div class="wt_profile-avatar">${esc(judge.initials)}</div>
                    <div>
                        <div style="font-size:20px;font-weight:700;">${esc(judge.name)}</div>
                        <div class="wt_caption wt_mono">SR-${esc(judge.id)}</div>
                    </div>
                </div>
            </div>
            <div class="wt_section">
                <div class="wt_card" style="overflow:hidden;">
                    <div class="wt_row-line">
                        <div>
                            <div class="wt_row-label">Aktive Station</div>
                            <div class="wt_row-sub">DB-ID: ${D.stationId} · Judge-ID: ${D.judgeId}</div>
                        </div>
                        <span class="wt_station-chip"><span class="wt_dot"></span>${esc(station.code)} · ${esc(station.name)}</span>
                    </div>
                    <div class="wt_row-line">
                        <div><div class="wt_row-label">Heute bewertet</div></div>
                        <div class="wt_mono" style="font-size:18px;font-weight:600;">${state.history.length}</div>
                    </div>
                    <div class="wt_row-line">
                        <div><div class="wt_row-label">Sync-Warteschlange</div></div>
                        <div class="wt_mono" style="font-size:18px;font-weight:600;color:${state.queueCount>0?'var(--wt-warn)':'var(--wt-text-subtle)'};">${state.queueCount}</div>
                    </div>
                </div>
                <button class="wt_btn wt_btn--ghost wt_btn--block" id="btnLogout" style="margin-top:16px;">
                    Abmelden
                </button>
            </div>
        </div>
        ${tabBarHtml()}`;
}

// ── Render & Attach ────────────────────────────
const root = document.getElementById('juma-app');

function setState(updates) {
    Object.assign(state, updates);
    render();
}

function render() {
    // Lade-Indikator beim ersten Render entfernen
    document.getElementById('juma-loading')?.remove();

    let html;
    if (state.tab === 'verlauf')     html = renderHistory();
    else if (state.tab === 'live')    html = renderStationsOverview();
    else if (state.tab === 'zentrale') html = renderChat();
    else if (state.tab === 'profil') html = renderProfile();
    else switch (state.route) {
        case 'checkin':       html = renderCheckin();              break;
        case 'group-confirm': html = renderGroupConfirm(state.group); break;
        case 'scoring':       html = renderScoring();              break;
        case 'confirm':       html = renderConfirm();              break;
        default:              html = renderDashboard();
    }
    root.innerHTML = html;
    attachHandlers();

    // Stopwatch-Timer live weiterführen nach Re-Render
    if (swRunning) updateSwFace();
}

function attachHandlers() {
    // Tab-Bar
    root.querySelectorAll('[data-tab]').forEach(btn =>
        btn.addEventListener('click', () => {
            const newTab = btn.dataset.tab;
            if (newTab === 'zentrale') {
                if (state.unreadCount > 0) {
                    apiFetch('/api/messages/read', { method: 'POST' }).catch(() => {});
                }
                loadMessages();
                setState({ tab: newTab, route: 'dashboard', unreadCount: 0 });
            } else if (newTab === 'live') {
                // Immer frisch laden beim Tab-Wechsel
                state.stationsOverview = null;
                setState({ tab: newTab, route: 'dashboard' });
            } else {
                setState({ tab: newTab, route: 'dashboard' });
            }
        }));

    // Live-Tab: Karte initialisieren + Aktualisieren-Button
    if (state.tab === 'live') initLiveMap();
    const btnRefresh = document.getElementById('btnRefreshOverview');
    if (btnRefresh) btnRefresh.addEventListener('click', () => {
        state.stationsOverview = null;
        loadStationsOverview();
    });

    // Aktiv Tab: Score-Cards klickbar
    root.querySelectorAll('[data-score-idx]').forEach(btn =>
        btn.addEventListener('click', () => showScoreModal(parseInt(btn.dataset.scoreIdx))));

    // Dashboard → Checkin
    const btnCheckin = document.getElementById('btnCheckin');
    if (btnCheckin) btnCheckin.addEventListener('click', startCheckin);

    // Zurück-Button
    const btnBack = document.getElementById('btnBack');
    if (btnBack) btnBack.addEventListener('click', goBack);

    // Checkin → QR-Scanner starten automatisch
    if (state.route === 'checkin') {
        startScanner('qrContainer', async (token) => {
            await stopScanner();
            await scanGroup(token);
        }).catch(() => showMessage('Kamera nicht verfügbar', 'error'));
    }

    // Group-Confirm → Bewertung starten
    const btnStart = document.getElementById('btnStartScoring');
    if (btnStart) btnStart.addEventListener('click', () => {
        swReset();
        setState({ route: 'scoring', scoring: emptyScoring() });
    });

    // Group-Info Dialog
    const btnInfo = document.getElementById('btnGroupInfo');
    if (btnInfo) btnInfo.addEventListener('click', () => showGroupInfo(state.group));

    // Scoring: Check-Toggle
    root.querySelectorAll('[data-task][data-val]').forEach(btn =>
        btn.addEventListener('click', () => {
            const taskId = parseInt(btn.dataset.task);
            const val    = btn.dataset.val;
            state.scoring.taskValues = { ...state.scoring.taskValues, [taskId]: val };
            updateScoringLive();
        }));

    // Scoring: Stepper
    root.querySelectorAll('[data-stepper]').forEach(btn => {
        if (!btn.dataset.dir) return;
        btn.addEventListener('click', () => {
            const taskId = parseInt(btn.dataset.stepper);
            const dir    = parseInt(btn.dataset.dir);
            const max    = btn.dataset.max !== undefined ? parseInt(btn.dataset.max) : 999;
            const cur    = state.scoring.taskValues[taskId] ?? 0;
            const next   = Math.min(max, Math.max(0, cur + dir));
            state.scoring.taskValues = { ...state.scoring.taskValues, [taskId]: next };
            updateScoringLive();
        });
    });

    // Scoring: Impression
    root.querySelectorAll('[data-impression]').forEach(btn =>
        btn.addEventListener('click', () => {
            state.scoring.impression = btn.dataset.impression;
            updateScoringLive();
        }));

    // Scoring: Weiter zur Bestätigung
    const btnToConfirm = document.getElementById('btnToConfirm');
    if (btnToConfirm) btnToConfirm.addEventListener('click', () => {
        swStop();
        setState({ route: 'confirm' });
    });

    // Bestätigung: Senden
    const btnSend = document.getElementById('btnSend');
    if (btnSend) btnSend.addEventListener('click', transmit);

    // Bestätigung: Zurück zur Bewertung
    const btnBackScoring = document.getElementById('btnBackToScoring');
    if (btnBackScoring) btnBackScoring.addEventListener('click', () => setState({ route: 'scoring' }));

    // Stopwatch
    const btnSwToggle = document.getElementById('btnSwToggle');
    if (btnSwToggle) btnSwToggle.addEventListener('click', () => {
        if (swRunning) { swStop(); } else { swStartTimer(); }
        render();
        // Hint sofort aktualisieren (Zeit angehalten → evtl. ready)
        if (state.route === 'scoring') updateScoringLive();
    });
    const btnSwReset = document.getElementById('btnSwReset');
    if (btnSwReset) btnSwReset.addEventListener('click', () => { swReset(); render(); });

    // Multi-Stopwatch Toggle
    root.querySelectorAll('[data-msw][data-msw-idx]').forEach(btn =>
        btn.addEventListener('click', () =>
            mswToggle(parseInt(btn.dataset.msw), parseInt(btn.dataset.mswIdx))));

    // Multi-Stopwatch Reset
    root.querySelectorAll('[data-msw-reset][data-msw-idx]').forEach(btn =>
        btn.addEventListener('click', () =>
            mswReset(parseInt(btn.dataset.mswReset), parseInt(btn.dataset.mswIdx))));

    // Chat: Eingabe + Senden
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.value = state.chatInput;
        chatInput.addEventListener('input', (e) => {
            state.chatInput = e.target.value;
            const btn = document.getElementById('btnChatSend');
            if (btn) btn.disabled = !e.target.value.trim();
            // Auto-resize
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 80) + 'px';
        });
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
        });
    }
    const btnChatSend = document.getElementById('btnChatSend');
    if (btnChatSend) btnChatSend.addEventListener('click', sendChatMessage);

    // Profil: Abmelden
    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) btnLogout.addEventListener('click', () => {
        if (confirm('Von der Station abmelden?')) {
            fetch('/admin/logout', { method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(csrf)}`,
            }).then(() => location.href = '/judge');
        }
    });
}

// Scoring-Elemente live aktualisieren ohne Full-Re-Render
function updateScoringLive() {
    const tv = state.scoring.taskValues;
    const fp = calcFp(tv);

    // FP-Anzeige im Banner
    const fpEl = document.getElementById('fp-live');
    if (fpEl) fpEl.textContent = fp;

    // Check-Toggle aktive Klassen
    root.querySelectorAll('[data-task][data-val]').forEach(btn => {
        const val = tv[parseInt(btn.dataset.task)];
        const isOk   = btn.dataset.val === 'ok';
        const isFail = btn.dataset.val === 'fail';
        btn.classList.toggle('wt_check-toggle__ok--active',   isOk   && val === 'ok');
        btn.classList.toggle('wt_check-toggle__fail--active', isFail && val === 'fail');
    });

    // Stepper-Werte und Button-States
    root.querySelectorAll('[data-stepper-val]').forEach(el => {
        const taskId = parseInt(el.dataset.stepperVal);
        el.textContent = tv[taskId] ?? 0;
    });
    root.querySelectorAll('[data-stepper][data-dir="-1"]').forEach(btn => {
        btn.disabled = (tv[parseInt(btn.dataset.stepper)] ?? 0) === 0;
    });
    root.querySelectorAll('[data-stepper][data-dir="1"]').forEach(btn => {
        const max = btn.dataset.max !== undefined ? parseInt(btn.dataset.max) : 999;
        btn.disabled = (tv[parseInt(btn.dataset.stepper)] ?? 0) >= max;
    });

    // Impression aktive Klassen
    root.querySelectorAll('[data-impression]').forEach(btn => {
        btn.classList.toggle('wt_impression-btn--active', btn.dataset.impression === state.scoring.impression);
    });

    // Submit-Button + Hint-Text
    const btnToConfirm = document.getElementById('btnToConfirm');
    const hintEl       = document.getElementById('scoringHint');
    if (btnToConfirm) {
        const { ok: ready, hints } = readyCheck(tv, state.scoring.impression);
        btnToConfirm.disabled = !ready;
        if (hintEl) hintEl.textContent = !ready ? hints.map(h => '· ' + h).join('  ') : '';
    }
}

// ── Flow-Aktionen ─────────────────────────────
function startCheckin() {
    setState({ tab: 'aktiv', route: 'checkin', group: null });
}

async function scanGroup(token) {
    try {
        const g = await apiFetch('/api/group/verify', {
            method: 'POST',
            body: JSON.stringify({ token }),
        });
        setState({ route: 'group-confirm', group: g });
    } catch (err) {
        showMessage(err.message, 'error');
        setState({ route: 'checkin' });
    }
}

function goBack() {
    stopScanner().catch(() => {});
    switch (state.route) {
        case 'checkin':
        case 'group-confirm': setState({ route: 'dashboard' }); break;
        case 'scoring':       setState({ route: 'group-confirm' }); break;
        case 'confirm':       setState({ route: 'scoring' }); break;
        default:              setState({ route: 'dashboard' });
    }
}

async function transmit() {
    const tv = state.scoring.taskValues;
    const payload = {
        group_id:   state.group.group_id,
        station_id: parseInt(station.id),
        tasks: tasks.map(t => {
            const entry = {
                task_id: t.id,
                type:    t.type,
                value:   t.type === 'boolean' ? (tv[t.id] ?? null)
                       : t.type === 'count'   ? (tv[t.id] ?? 0)
                       : null,
            };
            // Multi-Timer: Array der Einzelzeiten mitschicken
            if (t.type === 'time' && (t.time?.zeit_felder ?? 1) > 1) {
                entry.times = Array.isArray(tv[t.id]) ? tv[t.id].map(ms => ms ?? 0) : [];
            }
            return entry;
        }),
        impression: state.scoring.impression,
        // Globale Stoppuhr nur wenn Single-Time-Tasks oder station has_time vorhanden
        time_ms:    hasSingleTimeTask ? Math.round(swMs) : null,
        notes:      null,
    };

    setState({ submitting: true });

    try {
        const result = await apiFetch('/api/score', {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        setState({ submitting: false, submitted: true });

        setTimeout(() => {
            const newEntry = {
                score_id:     result.score_id,
                group_id:     state.group.group_id,
                group_name:   state.group.group_name,
                group_num:    state.group.group_num,
                kreis:        state.group.kreis || '',
                total_fp:     result.total_fp,
                synced:       true,
                timestamp:    new Date().toLocaleTimeString('de-DE', { hour:'2-digit', minute:'2-digit' }),
                impression:   state.scoring.impression,
                time_ms:      hasSingleTimeTask ? Math.round(swMs) : null,
                notes:        null,
                task_results: payload.tasks,
            };
            // Verlauf-Cache invalidieren damit Gruppen-Status aktuell bleibt
            state.allGroups = null;
            state.history = [newEntry, ...state.history];
            state.submitted = false;
            state.route     = 'dashboard';
            state.tab       = 'aktiv';
            state.group     = null;
            state.scoring   = emptyScoring();
            swReset();
            render();
        }, 1400);

    } catch {
        // Offline: lokal speichern
        setState({ submitting: false, submitted: false });
        window.dispatchEvent(new CustomEvent('wt:save-offline', { detail: payload }));

        const newEntry = {
            group_id:   state.group.group_id,
            group_name: state.group.group_name,
            group_num:  state.group.group_num,
            kreis:      state.group.kreis || '',
            total_fp:   calcFp(tv),
            synced:     false,
            timestamp:  new Date().toLocaleTimeString('de-DE', { hour:'2-digit', minute:'2-digit' }),
        };
        state.history = [newEntry, ...state.history];
        state.route   = 'dashboard';
        state.group   = null;
        state.scoring = emptyScoring();
        swReset();
        render();
        showMessage('Offline gespeichert – wird automatisch synchronisiert.', 'info');
    }
}

function showScoreModal(historyIdx) {
    const h = state.history[historyIdx];
    if (!h) return;

    const impLabel = { sehr_gut:'Sehr gut', gut:'Gut', befriedigend:'Befriedigend' }[h.impression] || '–';
    const { c } = scoreCardColor(h.total_fp);

    // Nur Fehler / nicht-null Einträge zeigen
    const failRows = (h.task_results || []).filter(r => {
        const t = taskMap[r.task_id];
        if (!t) return false;
        return (t.type === 'boolean' && r.value === 'fail') ||
               (t.type === 'count' && (r.value ?? 0) > 0);
    }).map(r => {
        const t = taskMap[r.task_id];
        let fp = 0, valLabel = '';
        if (t.type === 'boolean') {
            fp = t.points;
            valLabel = 'Fehler';
        } else {
            fp = (r.value ?? 0) * t.points;
            valLabel = `${r.value} × ${t.points} FP`;
        }
        return `<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px solid var(--wt-border);">
            <div style="font-size:13px;color:var(--wt-text);flex:1;">${esc(t.label)}</div>
            <div style="font-size:12px;font-family:'JetBrains Mono',monospace;color:var(--wt-red);white-space:nowrap;font-weight:600;">+${fp} FP</div>
        </div>`;
    }).join('');

    const timeHtml = h.time_ms
        ? `<div class="wt_summary-row" style="padding:8px 0;">
               <span style="color:var(--wt-text-muted);font-size:13px;">Zeit</span>
               <span style="font-family:'JetBrains Mono',monospace;font-weight:600;">${swFmt(h.time_ms).main}</span>
           </div>`
        : '';

    const d = document.createElement('dialog');
    d.style.cssText = 'border:0;border-radius:20px;padding:0;background:var(--wt-surface);color:var(--wt-text);width:360px;max-width:94vw;box-shadow:0 16px 48px rgba(0,0,0,.22);';
    d.innerHTML = `
        <div style="padding:18px 18px 12px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="wt_group-num" style="background:var(--wt-surface-alt);">#${esc(h.group_num)}</div>
                <div style="flex:1;">
                    <div style="font-size:15px;font-weight:700;">${esc(h.group_name)}</div>
                    <div class="wt_caption wt_mono">${esc(h.timestamp)} · ${h.synced?'übermittelt':'ausstehend'}</div>
                </div>
                <div style="font-size:32px;font-weight:700;font-family:'JetBrains Mono',monospace;color:${c};">${h.total_fp}</div>
            </div>
        </div>
        <div style="max-height:45vh;overflow-y:auto;padding:0 18px;border-top:1px solid var(--wt-border);">
            ${failRows || `<div style="padding:16px 0;text-align:center;color:var(--wt-text-subtle);font-size:13px;">Keine Fehler — alles korrekt.</div>`}
            ${timeHtml}
            ${h.notes ? `<div style="padding:10px 0;font-size:13px;color:var(--wt-text-muted);font-style:italic;">"${esc(h.notes)}"</div>` : ''}
            <div style="display:flex;justify-content:space-between;padding:10px 0;">
                <span style="color:var(--wt-text-muted);font-size:13px;">Eindruck</span>
                <span style="font-size:13px;font-weight:600;">${impLabel}</span>
            </div>
        </div>
        <div style="padding:12px 16px;display:flex;gap:8px;border-top:1px solid var(--wt-border);">
            <button id="btnModalDelete" style="flex:1;height:44px;border:1px solid var(--wt-red);background:transparent;color:var(--wt-red);border-radius:12px;font-family:inherit;font-weight:600;font-size:14px;cursor:pointer;">
                Löschen
            </button>
            <button id="btnModalClose" style="flex:2;height:44px;background:var(--wt-surface-alt);border:1px solid var(--wt-border);border-radius:12px;font-family:inherit;font-weight:600;font-size:14px;cursor:pointer;">
                Schließen
            </button>
        </div>`;

    document.body.appendChild(d);
    d.showModal();

    d.querySelector('#btnModalClose').onclick = () => d.close();
    d.querySelector('#btnModalDelete').onclick = async () => {
        if (!confirm(`Bewertung für #${h.group_num} ${h.group_name} wirklich löschen?`)) return;
        try {
            await apiFetch(`/api/score/${h.score_id}/delete`, { method: 'POST' });
            state.history = state.history.filter((_, i) => i !== historyIdx);
            d.close();
            render();
        } catch (err) {
            showMessage('Löschen fehlgeschlagen: ' + err.message, 'error');
        }
    };
    d.addEventListener('close', () => d.remove());
}

function showGroupInfo(g) {
    const rows = (g.members || []).map((m, i) => `
        <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--wt-border);">
            <div style="width:26px;height:26px;border-radius:8px;background:var(--wt-surface-alt);display:flex;align-items:center;justify-content:center;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;color:var(--wt-text-muted);">${i+1}</div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13.5px;font-weight:600;">${esc(m.vorname)} ${esc(m.name)}</div>
                <div style="font-size:11.5px;color:var(--wt-text-subtle);">
                    ${m.funktion ? esc(m.funktion) : ''}${m.funktion && m.alter_jahre ? ' · ' : ''}${m.alter_jahre ? m.alter_jahre + ' J.' : ''}
                </div>
            </div>
        </div>`).join('');

    // einfacher nativer Dialog (kein SweetAlert benötigt)
    const d = document.createElement('dialog');
    d.style.cssText = 'border:0;border-radius:20px;padding:0;background:var(--wt-surface);color:var(--wt-text);width:340px;max-width:90vw;box-shadow:0 12px 40px rgba(0,0,0,.2);';
    d.innerHTML = `
        <div style="padding:16px 16px 8px;font-weight:700;font-size:15px;">#${esc(g.group_num)} · ${esc(g.group_name)}</div>
        <div style="max-height:320px;overflow-y:auto;border-top:1px solid var(--wt-border);border-bottom:1px solid var(--wt-border);">
            ${rows || `<div style="padding:20px;text-align:center;color:var(--wt-text-subtle);">Keine Mitglieder hinterlegt.</div>`}
        </div>
        <div style="padding:12px 16px;">
            <button style="width:100%;height:44px;border:1px solid var(--wt-border);background:var(--wt-surface-alt);border-radius:12px;font-family:inherit;font-weight:600;font-size:14px;cursor:pointer;" onclick="this.closest('dialog').close()">Schließen</button>
        </div>`;
    document.body.appendChild(d);
    d.showModal();
    d.addEventListener('close', () => d.remove());
}

// ── Inline SVG Icons ───────────────────────────
const svgQr    = () => `<svg width="20" height="20" viewBox="0 0 22 22" fill="none" style="flex-shrink:0"><rect x="2" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="13" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="2" y="13" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="4.5" y="4.5" width="2" height="2" fill="currentColor"/><rect x="15.5" y="4.5" width="2" height="2" fill="currentColor"/><rect x="4.5" y="15.5" width="2" height="2" fill="currentColor"/></svg>`;
const svgArrow = () => `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 9h10M9 4l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
const svgInfo  = () => `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7.2" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="5.6" r=".9" fill="currentColor"/><path d="M9 8.2v5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;
const svgFlame = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M11 2c0 4-5 5-5 10a5 5 0 1010 0c0-2-1.5-3.5-2.5-4.5C11 6 14 5 11 2z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`;
const svgList  = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M4 6h14M4 11h14M4 16h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>`;
const svgUser  = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="11" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M4 19c1.5-3.5 4-5 7-5s5.5 1.5 7 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;
const svgChat  = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M4 4h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H7l-4 3V5a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`;
const svgSend    = () => `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M15 9L3 3l3 6-3 6 12-6z" fill="currentColor"/></svg>`;
const svgGrid    = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="12" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="12" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="12" y="12" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/></svg>`;
const svgRefresh = () => `<svg width="15" height="15" viewBox="0 0 18 18" fill="none"><path d="M16 9A7 7 0 1 1 9 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 2l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

// ── Start ──────────────────────────────────────
render();

// Queue-Count beim Laden anfragen
window.dispatchEvent(new Event('wt:request-queue-count'));

// Nachrichten-Polling alle 20 Sekunden
loadMessages();
setInterval(loadMessages, 20_000);

// Stationsübersicht alle 30 Sekunden aktualisieren wenn Tab aktiv
setInterval(() => {
    if (state.tab === 'live') loadStationsOverview();
}, 30_000);
