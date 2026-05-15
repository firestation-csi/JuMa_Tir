<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmeldung erfolgreich</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        body { background: var(--wt-bg); color: var(--wt-text); font-family: system-ui, sans-serif;
               display: flex; align-items: center; justify-content: center;
               min-height: 100dvh; padding: 24px; box-sizing: border-box; }
        .reg_success { max-width: 420px; text-align: center; }
        .reg_success__icon { font-size: 56px; margin-bottom: 16px; }
        .reg_success__title { font-size: 24px; font-weight: 900; margin-bottom: 8px; color: var(--wt-text); }
        .reg_success__sub   { font-size: 15px; color: var(--wt-text-muted); line-height: 1.55; }
        .reg_success__name  { font-weight: 700; color: var(--wt-text); }
        .reg_success__hint  { margin-top: 20px; padding: 14px 16px; background: var(--wt-surface);
                              border: 1px solid var(--wt-border); border-radius: var(--wt-r-sm);
                              font-size: 13px; color: var(--wt-text-muted); }
    </style>
</head>
<body>
<div class="reg_success">
    <div class="reg_success__icon">✅</div>
    <div class="reg_success__title">Anmeldung eingegangen!</div>
    <div class="reg_success__sub">
        Die Gruppe <span class="reg_success__name">«<?= htmlspecialchars($name ?? '') ?>»</span>
        wurde erfolgreich für
        <span class="reg_success__name">«<?= htmlspecialchars($competition['name'] ?? '') ?>»</span>
        angemeldet.
    </div>
    <div class="reg_success__hint">
        Eure Anmeldung wird vom Wertungsbüro geprüft und freigeschaltet.<br>
        Ihr erhaltet dann weitere Informationen.
    </div>
</div>
</body>
</html>
