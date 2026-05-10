// app.jsx — JuMA root, state machine, tweaks wiring

const { useState, useEffect, useRef, useMemo } = React;

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "dark": false,
  "inputStyle": "stepper",
  "flowOrder": "linear",
  "startAt": "dashboard"
}/*EDITMODE-END*/;

// ───────────────────────────────────────────
// Mock data
// ───────────────────────────────────────────
const STATION = {
  id: 'st-06',
  code: 'A06',
  name: 'Knotengestell',
  location: 'Wertungsplatz Nord',
  version: '2026.1',
  hasTime: false,
  criteria: [
    { id: 'mast',  label: 'Mastwurf',         hint: '2 halbe Schläge gegenläufig',  weight: 5 },
    { id: 'kreuz', label: 'Kreuzknoten',      hint: 'Auf Stockunterseite gelegt',   weight: 5 },
    { id: 'doppel',label: 'Doppelter Ankerstich', hint: 'Beide Buchten gleich groß',weight: 5 },
    { id: 'sch',   label: 'Schotenstich',     hint: 'Mit Auge nach oben',           weight: 5 },
  ],
  penalties: [
    { id: 'sicht',   label: 'Knoten nicht sichtbar', weight: 5,  max: 4 },
    { id: 'reihe',   label: 'Reihenfolge falsch',     weight: 5,  max: 4 },
    { id: 'sonst',   label: 'Sonstige Mängel',        weight: 5,  max: 4 },
  ],
};

const JUDGE = {
  id: '12-04',
  name: 'Niklas Müller',
  initials: 'NM',
  role: 'Schiedsrichter A',
};

const QUEUE = [
  { id: 'g14', num: 14, name: 'JF Mustertal',          kreis: 'KFV Lichtenfels',   altersgruppe: 'AG II', startnr: 'B-14',
    members: [
      { vorname: 'Lukas',   name: 'Berger',     alter: 14, fn: 'Gruppenführer' },
      { vorname: 'Felix',   name: 'Hartmann',   alter: 13, fn: 'Maschinist' },
      { vorname: 'Mia',     name: 'Köhler',     alter: 12, fn: 'Angriffstrupp' },
      { vorname: 'Jonas',   name: 'Lindner',    alter: 13, fn: 'Angriffstrupp' },
      { vorname: 'Emma',    name: 'Schreiber',  alter: 12, fn: 'Wassertrupp' },
      { vorname: 'Tim',     name: 'Vogel',      alter: 14, fn: 'Wassertrupp' },
      { vorname: 'Leonie',  name: 'Wagner',     alter: 13, fn: 'Schlauchtrupp' },
      { vorname: 'Ben',     name: 'Zimmermann', alter: 12, fn: 'Schlauchtrupp' },
      { vorname: 'Sophia',  name: 'Albrecht',   alter: 14, fn: 'Melder' },
    ] },
  { id: 'g15', num: 15, name: 'JF Schwarzbach',        kreis: 'KFV Mittelfranken', altersgruppe: 'AG I',  startnr: 'B-15',
    members: [
      { vorname: 'Paul',    name: 'Engel',      alter: 11, fn: 'Gruppenführer' },
      { vorname: 'Hannah',  name: 'Fischer',    alter: 10, fn: 'Maschinist' },
      { vorname: 'Noah',    name: 'Gärtner',    alter: 11, fn: 'Angriffstrupp' },
      { vorname: 'Lea',     name: 'Hoffmann',   alter: 10, fn: 'Angriffstrupp' },
      { vorname: 'Elias',   name: 'Kraus',      alter: 11, fn: 'Wassertrupp' },
      { vorname: 'Marie',   name: 'Neumann',    alter: 11, fn: 'Wassertrupp' },
      { vorname: 'Henry',   name: 'Pfeiffer',   alter: 10, fn: 'Schlauchtrupp' },
      { vorname: 'Greta',   name: 'Roth',       alter: 11, fn: 'Schlauchtrupp' },
      { vorname: 'Theo',    name: 'Sauer',      alter: 10, fn: 'Melder' },
    ] },
  { id: 'g16', num: 16, name: 'JF Hohenwald',          kreis: 'KFV Oberfranken',   altersgruppe: 'AG II', startnr: 'B-16', members: [] },
  { id: 'g17', num: 17, name: 'JF Buchhain',           kreis: 'KFV Tirschenreuth', altersgruppe: 'AG I',  startnr: 'B-17', members: [] },
];

const HISTORY = [
  { id: 'h1', num: 13, name: 'JF Auerbach',     kreis: 'KFV Lichtenfels',  timestamp: '14:18', fehlerpunkte: 5,  flag: 'ok',   synced: true },
  { id: 'h2', num: 12, name: 'JF Niederwald',   kreis: 'KFV Bayreuth',     timestamp: '14:09', fehlerpunkte: 0,  flag: 'ok',   synced: true },
  { id: 'h3', num: 11, name: 'JF Brückenfeld',  kreis: 'KFV Coburg',       timestamp: '14:01', fehlerpunkte: 15, flag: 'fail', synced: true },
  { id: 'h4', num: 10, name: 'JF Lindenhain',   kreis: 'KFV Forchheim',    timestamp: '13:53', fehlerpunkte: 5,  flag: 'ok',   synced: true },
  { id: 'h5', num: 9,  name: 'JF Forsthausen',  kreis: 'KFV Hof',          timestamp: '13:46', fehlerpunkte: 10, flag: 'fail', synced: true },
];

const emptyScoring = () => ({
  time: 0,
  checks: {},                      // criteriaId -> 'ok' | 'fail'
  penalties: { sicht: 0, reihe: 0, sonst: 0 },
  impression: null,                // 'sehr_gut' | 'gut' | 'befriedigend'
  comment: '',
});

// ───────────────────────────────────────────
// App
// ───────────────────────────────────────────
function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);

  // App routes
  // login → stationConfirm → dashboard → checkin → scoring → confirm → dashboard
  const [route, setRoute] = useState(t.startAt);
  const [tab, setTab] = useState('aktiv');
  const [activeGroup, setActiveGroup] = useState(null);
  const [scoring, setScoring] = useState(emptyScoring);
  const [syncing, setSyncing] = useState(false);
  const [done, setDone] = useState(false);
  const [history, setHistory] = useState(HISTORY);
  const [sync, setSync] = useState('online');

  // jump-to via tweak
  useEffect(() => {
    setRoute(t.startAt);
  }, [t.startAt]);

  // theme
  useEffect(() => {
    document.documentElement.setAttribute('data-theme', t.dark ? 'dark' : 'light');
  }, [t.dark]);

  const goCheckin = () => {
    setActiveGroup(QUEUE[0]);
    setScoring(emptyScoring());
    setRoute('checkin');
  };

  const onScannedGroup = () => {
    if (t.flowOrder === 'review-first') {
      setRoute('confirm-blank');
    } else {
      setRoute('scoring');
    }
  };

  const onSubmitScoring = () => setRoute('confirm');

  const onTransmit = () => {
    const totalKnotPenalty = STATION.criteria.reduce((sum, c) =>
      sum + (scoring.checks[c.id] === 'fail' ? c.weight : 0), 0);
    const totalGen = Object.entries(scoring.penalties).reduce((sum, [id, val]) => {
      const def = STATION.penalties.find(x => x.id === id);
      return sum + (def ? val * def.weight : 0);
    }, 0);
    const total = totalKnotPenalty + totalGen;
    const impressionLabel = { sehr_gut: 'Sehr gut', gut: 'Gut', befriedigend: 'Befriedigend' }[scoring.impression] || '–';

    JuMAlert.fire({
      title: 'Bewertung übermitteln?',
      html: `
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:6px;">
          Übertragung an Wertungszentrale ${STATION.code}.
        </div>
        <div class="juma-summary">
          <div class="num">${total}</div>
          <div style="flex:1;">
            <div class="lbl">Fehlerpunkte</div>
            <div class="grp">#${activeGroup.num} · ${activeGroup.name}</div>
            <div style="font-size:12px;color:var(--text-subtle);margin-top:4px;">
              ${impressionLabel} · ${totalKnotPenalty} Knoten · ${totalGen} allgemein
            </div>
          </div>
        </div>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Senden',
      cancelButtonText: 'Korrigieren',
    }).then((res) => {
      if (!res.isConfirmed) return;
      if (sync === 'offline') {
        JuMAToast.fire({
          icon: 'warning',
          title: 'Offline — wird zwischengespeichert',
          html: 'Übertragung erfolgt automatisch bei Verbindung.',
          timer: 2800,
        });
      }
      doTransmit(total);
    });
  };

  const doTransmit = (total) => {
    setSyncing(true);
    setTimeout(() => {
      setSyncing(false);
      setDone(true);
      JuMAToast.fire({
        icon: 'success',
        title: `Quittung 8F2A-${activeGroup.num}`,
        html: `${total} Fp · übermittelt`,
      });
      setTimeout(() => {
        setHistory(h => [{
          id: `h-${Date.now()}`,
          num: activeGroup.num,
          name: activeGroup.name,
          kreis: activeGroup.kreis,
          timestamp: new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }),
          fehlerpunkte: total,
          flag: total > 5 ? 'fail' : 'ok',
          synced: sync !== 'offline',
        }, ...h]);
        setDone(false);
        setActiveGroup(null);
        setRoute('dashboard');
        setTab('aktiv');
      }, 1400);
    }, 1700);
  };

  const renderRoute = () => {
    if (tab === 'verlauf') {
      return <HistoryScreen station={STATION} sync={sync} history={history}/>;
    }
    if (tab === 'profil') {
      return <ProfileScreen station={STATION} sync={sync} judge={JUDGE}/>;
    }
    switch (route) {
      case 'login':
        return <LoginScreen dark={t.dark} onScanned={() => setRoute('stationConfirm')}/>;
      case 'stationConfirm':
        return <StationConfirmScreen station={STATION} judge={JUDGE} onConfirm={() => setRoute('dashboard')}/>;
      case 'dashboard':
        return <DashboardScreen station={STATION} sync={sync} queue={QUEUE} history={history}
                                onCheckIn={goCheckin}/>;
      case 'checkin':
        return <GroupCheckinScreen
                 station={STATION} sync={sync} group={activeGroup} dark={t.dark}
                 onBack={() => setRoute('dashboard')}
                 onScanned={onScannedGroup}/>;
      case 'scoring':
        return <ScoringScreen
                 station={STATION} sync={sync} group={activeGroup}
                 scoring={scoring} setScoring={setScoring}
                 inputStyle={t.inputStyle} flowOrder={t.flowOrder}
                 onBack={() => setRoute('checkin')}
                 onSubmit={onSubmitScoring}/>;
      case 'confirm':
        return <ConfirmScreen
                 station={STATION} sync={sync} group={activeGroup}
                 scoring={scoring}
                 syncing={syncing} done={done}
                 onBack={() => setRoute('scoring')}
                 onSubmit={onTransmit}/>;
      default:
        return null;
    }
  };

  // Show tab bar except during scan / scoring full-focus screens? No — spec says tabs always visible
  // unless in deep flow. To keep it clean, hide on login + stationConfirm + checkin + scoring + confirm
  const inFlow = ['login', 'stationConfirm', 'checkin', 'scoring', 'confirm'].includes(route);
  const showTabs = !inFlow || tab !== 'aktiv';
  const showTabBar = (tab !== 'aktiv') || route === 'dashboard';

  return (
    <div className="app">
      {renderRoute()}
      {showTabBar && (
        <TabBar
          active={tab}
          onChange={(t) => {
            setTab(t);
            if (t === 'aktiv' && route !== 'dashboard') setRoute('dashboard');
          }}
        />
      )}

      <TweaksPanel>
        <TweakSection label="Erscheinung"/>
        <TweakToggle label="Dark mode" value={t.dark} onChange={(v) => setTweak('dark', v)}/>

        <TweakSection label="Bewertungs-Eingaben"/>
        <TweakRadio
          label="Fehlerpunkte"
          value={t.inputStyle}
          options={['stepper', 'slider', 'dropdown']}
          onChange={(v) => setTweak('inputStyle', v)}
        />

        <TweakSection label="Flow"/>
        <TweakRadio
          label="Reihenfolge"
          value={t.flowOrder}
          options={['linear', 'review-first']}
          onChange={(v) => setTweak('flowOrder', v)}
        />

        <TweakSection label="Sprung-Marken (Demo)"/>
        <TweakSelect
          label="Start"
          value={t.startAt}
          options={['login', 'stationConfirm', 'dashboard', 'checkin', 'scoring', 'confirm']}
          onChange={(v) => setTweak('startAt', v)}
        />
        <TweakRadio
          label="Sync-Status"
          value={sync}
          options={['online', 'offline']}
          onChange={setSync}
        />

        <TweakButton label="Demo-Bewertung füllen" onClick={() => {
          if (route !== 'scoring') return;
          setScoring(s => ({
            ...s,
            checks: { mast: 'ok', kreuz: 'fail', doppel: 'ok', sch: 'ok' },
            penalties: { sicht: 0, reihe: 1, sonst: 0 },
            impression: 'gut',
            comment: 'Kreuzknoten von TN3 ungenügend, Schotenstich saß sauber.',
          }));
        }}/>
        <TweakButton label="Reset Flow" onClick={() => {
          setActiveGroup(null);
          setScoring(emptyScoring());
          setSyncing(false);
          setDone(false);
          setRoute(t.startAt);
        }}/>
      </TweaksPanel>
    </div>
  );
}

// ───────────────────────────────────────────
// Stage — wraps the iOS device frame with a soft floor
// ───────────────────────────────────────────
function Stage() {
  return (
    <div className="stage">
      <IOSDevice width={402} height={874}>
        <App/>
      </IOSDevice>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<Stage/>);
