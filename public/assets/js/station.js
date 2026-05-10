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
const hasTime = !!station.has_time;

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
    route:      'dashboard',
    tab:        'aktiv',
    group:      null,
    history:    D.history || [],
    scoring:    emptyScoring(),
    submitting: false,
    submitted:  false,
    online:     navigator.onLine,
    queueCount: 0,
};

function emptyScoring() {
    return { taskValues: {}, impression: null, notes: '' };
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
        if (t.type === 'boolean' && v === 'fail') fp += t.points;
        else if (t.type === 'count' && v > 0)     fp += v * t.points;
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

// ── HTML-Helfer ────────────────────────────────
const esc = (v) => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

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
    const t = (id, icon, label) =>
        `<button class="wt_tabbar__btn${state.tab===id?' wt_tabbar__btn--active':''}" data-tab="${id}">
            ${icon}<span>${label}</span></button>`;
    return `<div class="wt_tabbar">
        ${t('aktiv',   svgFlame(), 'Aktiv')}
        ${t('verlauf', svgList(),  'Verlauf')}
        ${t('profil',  svgUser(),  'Profil')}
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
function renderDashboard() {
    const last = state.history[0];
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
            ${state.history.length > 0 ? `
            <div class="wt_section">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0 4px 10px;">
                    <span class="wt_eyebrow">Heute bewertet</span>
                    <span class="wt_caption wt_mono">${state.history.length}</span>
                </div>
                <div class="wt_card" style="overflow:hidden;">
                    ${state.history.slice(0,5).map(h => `
                    <div class="wt_list-row">
                        <div class="wt_group-num" style="background:${h.total_fp>10?'var(--wt-red-tint)':'var(--wt-ok-soft)'};color:${h.total_fp>10?'var(--wt-red)':'var(--wt-ok)'};">#${esc(h.group_num)}</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:14.5px;font-weight:600;">${esc(h.group_name)}</div>
                            <div class="wt_group-meta">
                                <span>${esc(h.timestamp)}</span><span class="wt_sep"></span>
                                <span style="color:${h.synced?'var(--wt-ok)':'var(--wt-warn)'};">${h.synced?'übermittelt':'ausstehend'}</span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="wt_mono" style="font-size:16px;font-weight:600;">${h.total_fp}</div>
                            <div class="wt_caption" style="font-size:10px;">FP</div>
                        </div>
                    </div>`).join('')}
                </div>
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
            <div class="wt_section">
                <button class="wt_btn wt_btn--primary wt_btn--block" id="btnStartScoring" style="height:56px;font-size:16px;">
                    Bewertung starten ${svgArrow()}
                </button>
            </div>
        </div>`;
}

function renderScoring() {
    const g   = state.group;
    const tv  = state.scoring.taskValues;
    const fp  = calcFp(tv);
    const imp = state.scoring.impression;
    const ready = allScored(tv) && imp;

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
                        const val = tv[t.id] ?? 0;
                        return `
                    <div class="wt_row-line">
                        <div style="min-width:0;flex:1;">
                            <div class="wt_row-label">${esc(t.label)}</div>
                            <div class="wt_row-sub">${t.points} FP je Teilnehmer</div>
                        </div>
                        <div class="wt_stepper">
                            <button data-stepper="${t.id}" data-dir="-1" ${val===0?'disabled':''}>−</button>
                            <span class="wt_stepper__val" data-stepper-val="${t.id}">${val}</span>
                            <button data-stepper="${t.id}" data-dir="1">+</button>
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
                <div class="wt_eyebrow" style="padding:0 4px 8px;">Kommentar <span style="font-weight:400;text-transform:none;">(optional)</span></div>
                <textarea id="scoreNotes" class="wt_textarea" rows="3"
                    placeholder="Auffälligkeiten an die Wertungszentrale..."
                    style="width:100%;resize:none;">${esc(state.scoring.notes)}</textarea>
            </div>

            <div class="wt_section">
                <button class="wt_btn wt_btn--primary wt_btn--block" id="btnToConfirm"
                    style="height:56px;font-size:16px;" ${ready?'':'disabled'}>
                    Zur Bestätigung ${svgArrow()}
                </button>
                ${!ready ? `<div class="wt_caption" style="text-align:center;margin-top:10px;">
                    Bitte alle Kriterien beurteilen und Eindruck wählen.
                </div>` : ''}
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
                    ${state.scoring.notes ? `
                    <div style="height:1px;background:var(--wt-border);margin:16px 0;"></div>
                    <div class="wt_caption" style="line-height:1.5;">"${esc(state.scoring.notes)}"</div>` : ''}
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
    return `
        ${topHeaderHtml()}
        <div class="wt_scroll wt_fade-in">
            <div class="wt_section" style="padding-top:16px;">
                <div class="wt_eyebrow">Verlauf</div>
                <h2 class="wt_h1" style="margin-top:4px;">${state.history.length} bewertet<br>heute.</h2>
            </div>
            ${state.history.length > 0 ? `
            <div class="wt_section">
                <div class="wt_card" style="overflow:hidden;">
                    ${state.history.map(h => `
                    <div class="wt_list-row">
                        <div class="wt_group-num" style="background:${h.total_fp>10?'var(--wt-red-tint)':'var(--wt-ok-soft)'};color:${h.total_fp>10?'var(--wt-red)':'var(--wt-ok)'};">#${esc(h.group_num)}</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:14.5px;font-weight:600;">${esc(h.group_name)}</div>
                            <div class="wt_group-meta">
                                <span>${esc(h.timestamp)}</span>
                                ${h.kreis?`<span class="wt_sep"></span><span>${esc(h.kreis)}</span>`:''}
                                <span class="wt_sep"></span>
                                <span style="color:${h.synced?'var(--wt-ok)':'var(--wt-warn)'};">${h.synced?'übermittelt':'ausstehend'}</span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="wt_mono" style="font-size:16px;font-weight:600;">${h.total_fp}</div>
                            <div class="wt_caption" style="font-size:10px;">FP</div>
                        </div>
                    </div>`).join('')}
                </div>
            </div>` : `
            <div class="wt_section">
                <div class="wt_caption" style="text-align:center;padding:32px 0;">Noch keine Gruppen bewertet.</div>
            </div>`}
        </div>
        ${tabBarHtml()}`;
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
    let html;
    if (state.tab === 'verlauf')     html = renderHistory();
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
            setState({ tab: btn.dataset.tab, route: 'dashboard' });
        }));

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
            const cur    = state.scoring.taskValues[taskId] ?? 0;
            state.scoring.taskValues = { ...state.scoring.taskValues, [taskId]: Math.max(0, cur + dir) };
            updateScoringLive();
        });
    });

    // Scoring: Impression
    root.querySelectorAll('[data-impression]').forEach(btn =>
        btn.addEventListener('click', () => {
            state.scoring.impression = btn.dataset.impression;
            updateScoringLive();
        }));

    // Scoring: Notizen (debounced into state on blur)
    const notesEl = document.getElementById('scoreNotes');
    if (notesEl) notesEl.addEventListener('input', (e) => {
        state.scoring.notes = e.target.value;
    });

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
    });
    const btnSwReset = document.getElementById('btnSwReset');
    if (btnSwReset) btnSwReset.addEventListener('click', () => { swReset(); render(); });

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

    // Impression aktive Klassen
    root.querySelectorAll('[data-impression]').forEach(btn => {
        btn.classList.toggle('wt_impression-btn--active', btn.dataset.impression === state.scoring.impression);
    });

    // Submit-Button
    const btnToConfirm = document.getElementById('btnToConfirm');
    if (btnToConfirm) {
        const ready = allScored(tv) && state.scoring.impression;
        btnToConfirm.disabled = !ready;
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
            value:   tv[t.id] ?? (t.type === 'boolean' ? null : 0),
        })),
        impression: state.scoring.impression,
        time_ms:    hasTime ? Math.round(swMs) : null,
        notes:      state.scoring.notes || null,
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
                score_id:   result.score_id,
                group_id:   state.group.group_id,
                group_name: state.group.group_name,
                group_num:  state.group.group_num,
                kreis:      state.group.kreis || '',
                total_fp:   result.total_fp,
                synced:     true,
                timestamp:  new Date().toLocaleTimeString('de-DE', { hour:'2-digit', minute:'2-digit' }),
            };
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

function showGroupInfo(g) {
    const rows = (g.members || []).map((m, i) => `
        <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--wt-border);">
            <div style="width:26px;height:26px;border-radius:8px;background:var(--wt-surface-alt);display:flex;align-items:center;justify-content:center;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;color:var(--wt-text-muted);">${i+1}</div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13.5px;font-weight:600;">${esc(m.vorname)} ${esc(m.name)}</div>
                ${m.funktion?`<div style="font-size:11.5px;color:var(--wt-text-subtle);">${esc(m.funktion)}</div>`:''}
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
const svgQr = () => `<svg width="20" height="20" viewBox="0 0 22 22" fill="none" style="flex-shrink:0"><rect x="2" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="13" y="2" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="2" y="13" width="7" height="7" rx="1.4" stroke="currentColor" stroke-width="1.6"/><rect x="4.5" y="4.5" width="2" height="2" fill="currentColor"/><rect x="15.5" y="4.5" width="2" height="2" fill="currentColor"/><rect x="4.5" y="15.5" width="2" height="2" fill="currentColor"/></svg>`;
const svgArrow = () => `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 9h10M9 4l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
const svgInfo = () => `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7.2" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="5.6" r=".9" fill="currentColor"/><path d="M9 8.2v5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;
const svgFlame = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M11 2c0 4-5 5-5 10a5 5 0 1010 0c0-2-1.5-3.5-2.5-4.5C11 6 14 5 11 2z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`;
const svgList = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M4 6h14M4 11h14M4 16h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>`;
const svgUser = () => `<svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="11" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M4 19c1.5-3.5 4-5 7-5s5.5 1.5 7 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`;

// ── Start ──────────────────────────────────────
render();

// Queue-Count beim Laden anfragen
window.dispatchEvent(new Event('wt:request-queue-count'));
