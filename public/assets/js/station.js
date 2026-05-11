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
    allGroups:       null,
    allGroupsLoading: false,
};

function emptyScoring() {
    const taskValues = {};
    tasks.filter(t => t.type === 'boolean').forEach(t => { taskValues[t.id] = 'ok'; });
    return { taskValues, impression: null };
}

// ── Sync-Status ────────────────────────────────
window.addEventListener('online',  () => { state.online = true;  rerenderSyncPill(); });
window.addEventListener('offline', () => { state.online = false; rerenderSyncPill(); });
window.addEventListener('wt:queue-count', (e) => {
    state.queueCount = e.detail ?? 0;
    rerenderSyncPill();
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
        // Zeitstrafe: gilt für time-Typ (eigene Aufgabe) und optionale Zeitfelder an anderen Typen
        if (t.time) {
            const sek = Math.floor(swMs / 1000);
            const { sollzeit_sek: soll, hoechstzeit_sek: max, zeitstrafe_fp: fpj, zeiteinheit_sek: einh } = t.time;
            if (sek > soll) {
                const over = (max ? Math.min(sek, max) : sek) - soll;
                fp += Math.floor(over / einh) * fpj;
            }
        }
    }
    return fp;
}

function allScored(taskValues) {
    return tasks.filter(t => t.type === 'boolean').every(t => taskValues[t.id]);
}

const hasTimeComponent = hasTime || tasks.some(t => t.time);

/** Gibt {ok, hints} zurück — hints ist ein Array mit Beschreibungen was noch fehlt */
function readyCheck(taskValues, impression) {
    const hints = [];
    if (!impression) hints.push('Eindruck wählen');
    if (hasTimeComponent) {
        if (swMs === 0)      hints.push('Zeit stoppen');
        else if (swRunning)  hints.push('Stoppuhr anhalten');
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

    const boolTasks  = tasks.filter(t => t.type === 'boolean');
    const countTasks = tasks.filter(t => t.type === 'count');
    const hasTimeTasks = tasks.some(t => t.time);

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

            ${(hasTime || hasTimeTasks) ? `
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
                    ${tasks.filter(t => t.time).map(t => {
                        const fmt = s => `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;
                        return `<div style="padding:10px 16px 0;border-top:1px solid var(--wt-border);display:flex;gap:16px;font-size:12px;color:var(--wt-text-subtle);">
                            <span>Soll: <strong>${fmt(t.time.sollzeit_sek)}</strong></span>
                            ${t.time.hoechstzeit_sek ? `<span>Max: <strong>${fmt(t.time.hoechstzeit_sek)}</strong></span>` : ''}
                            <span>${t.time.zeitstrafe_fp} FP / ${t.time.zeiteinheit_sek}s Überschreitung</span>
                        </div>`;
                    }).join('')}
                </div>
            </div>` : ''}

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

    const fails = tasks.filter(t => t.type==='boolean' && tv[t.id]==='fail');
    const imp   = { sehr_gut:'Sehr gut', gut:'Gut', befriedigend:'Befriedigend' }[state.scoring.impression] || '–';

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
                    ${fails.length > 0 ? `
                    <div style="height:1px;background:var(--wt-border);margin:16px 0;"></div>
                    <div class="wt_eyebrow" style="margin-bottom:10px;color:var(--wt-red);">Fehler (${fails.length})</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        ${fails.map(f => `<span class="wt_total-pill">${esc(f.label)} +${f.points}</span>`).join('')}
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

async function loadAllGroups() {
    if (state.allGroupsLoading) return;
    setState({ allGroupsLoading: true });
    try {
        const data = await apiFetch('/api/station/groups');
        setState({ allGroups: data.groups || [], allGroupsLoading: false });
    } catch {
        // Bei Fehler leere Liste setzen damit kein erneuter Retry ausgelöst wird
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
                        <div><div class="wt_row-label">Aktive Station</div><div class="wt_row-sub">Wechseln durch Neu-Scan</div></div>
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
            // Beim Öffnen des Chat-Tabs: als gelesen markieren + Nachrichten laden
            if (newTab === 'zentrale') {
                if (state.unreadCount > 0) {
                    apiFetch('/api/messages/read', { method: 'POST' }).catch(() => {});
                }
                loadMessages();
                setState({ tab: newTab, route: 'dashboard', unreadCount: 0 });
            } else {
                setState({ tab: newTab, route: 'dashboard' });
            }
        }));

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
        tasks: tasks.map(t => ({
            task_id: t.id,
            type:    t.type,
            // time-Typ: Wert kommt aus Stoppuhr (time_ms), kein eigener taskValue
            value:   t.type === 'boolean' ? (tv[t.id] ?? null)
                   : t.type === 'count'   ? (tv[t.id] ?? 0)
                   : null,
        })),
        impression: state.scoring.impression,
        time_ms:    hasTimeComponent ? Math.round(swMs) : null,
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
                time_ms:      hasTimeComponent ? Math.round(swMs) : null,
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
const svgSend  = () => `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M15 9L3 3l3 6-3 6 12-6z" fill="currentColor"/></svg>`;

// ── Start ──────────────────────────────────────
render();

// Queue-Count beim Laden anfragen
window.dispatchEvent(new Event('wt:request-queue-count'));

// Nachrichten-Polling alle 20 Sekunden
loadMessages();
setInterval(loadMessages, 20_000);
