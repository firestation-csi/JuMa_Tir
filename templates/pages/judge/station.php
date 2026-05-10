<?php
ob_start();
?>
<div class="wt_station" data-station-id="<?= (int)($station['id'] ?? 0) ?>" data-judge-id="<?= (int)($judgeId ?? 0) ?>">
    <div class="wt_card">
        <h2 class="wt_card__title"><?= htmlspecialchars($station['name'] ?? '') ?></h2>
        <?php if (!empty($station['task'])): ?>
            <p class="wt_station__task"><?= htmlspecialchars($station['task']) ?></p>
        <?php endif; ?>
        <?php if (!empty($station['max_score'])): ?>
            <p class="wt_station__max-score">Max. Punkte: <strong><?= (int)$station['max_score'] ?></strong></p>
        <?php endif; ?>
    </div>

    <div class="wt_card" id="groupScanCard">
        <h3 class="wt_card__title">Gruppe scannen</h3>
        <p>Gruppe QR-Code zum Legitimieren scannen.</p>
        <div id="groupQrContainer"></div>
        <button class="wt_btn wt_btn--primary wt_btn--large" id="scanGroupBtn">
            Gruppen-QR scannen
        </button>
    </div>

    <div class="wt_card wt_card--hidden" id="scoreCard">
        <h3 class="wt_card__title">Bewertung eingeben</h3>
        <div class="wt_group-badge" id="currentGroupName"></div>

        <form class="wt_form" id="scoreForm">
            <input type="hidden" id="currentGroupId" name="group_id" value="">
            <input type="hidden" name="station_id" value="<?= (int)($station['id'] ?? 0) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

            <div class="wt_score-grid" id="scoreGrid">
                <?php
                $maxScore = (int)($station['max_score'] ?? 10);
                for ($i = 1; $i <= $maxScore; $i++):
                ?>
                    <button type="button" class="wt_score-btn" data-value="<?= $i ?>">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>
            </div>

            <div class="wt_form__group">
                <label class="wt_label" for="scoreNotes">Notizen (optional)</label>
                <textarea class="wt_textarea" id="scoreNotes" name="notes" rows="2" placeholder="Anmerkungen..."></textarea>
            </div>

            <div class="wt_selected-score">
                Ausgewählt: <strong id="selectedScoreDisplay">–</strong> Punkte
            </div>

            <button type="submit" class="wt_btn wt_btn--success wt_btn--large" id="saveScoreBtn" disabled>
                Bewertung speichern
            </button>
        </form>
    </div>

    <div class="wt_card" id="historyCard">
        <h3 class="wt_card__title">Bereits bewertet</h3>
        <ul class="wt_score-history" id="scoreHistory">
            <li class="wt_score-history__empty">Noch keine Bewertungen.</li>
        </ul>
    </div>
</div>

<script src="/assets/js/qr.js" type="module"></script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layout/judge.php';
