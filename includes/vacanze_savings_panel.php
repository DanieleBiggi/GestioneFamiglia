<?php
// Included from vacanze_workspace.php after loading $trip, $scenarios and $planning.
?>
<div class="vw-panel" id="vacanza-salvadanaio">
 <div class="vw-section-head"><h2>Scenario confermato e salvadanaio</h2></div>
 <?php if($confirmedName!==null): ?><p><strong><?= vwEsc($confirmedName) ?></strong> · Totale <?= vwEuro($confirmedTotal) ?> · <strong>Ancora da pagare <?= vwEuro($confirmedRemaining) ?></strong></p>
 <?php else: ?><p class="vw-muted">Conferma uno scenario qui sotto prima di collegare un salvadanaio.</p><?php endif; ?>
 <?php if(!empty($planning['id_salvadanaio'])): ?>
  <p><strong><?= vwEsc($planning['nome_salvadanaio']) ?></strong> · Accantonato <?= vwEuro($planning['importo_attuale']) ?></p>
  <?php
    $hasOneBudget=count($linkedBudgetRows)===1;
    $budgetAmount=$hasOneBudget?(float)$linkedBudgetRows[0]['importo']:null;
    $hasRemaining=$confirmedRemaining!==null;
    $budgetMismatch=$hasRemaining && $hasOneBudget && abs($budgetAmount-$confirmedRemaining)>0.009;
  ?>
  <?php if($hasOneBudget): ?>
   <p><strong>Importo Budget:</strong> <?= vwEuro($budgetAmount) ?>
    <a href="salvadanaio_dettaglio.php?id=<?= (int)$planning['id_salvadanaio'] ?>">Apri la voce</a>
   </p>
  <?php elseif(count($linkedBudgetRows)>1): ?>
   <div class="vw-savings-warning" role="alert"><div class="vw-savings-warning-title"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Più voci Budget collegate</div>
   <p>Questo salvadanaio contiene <?= count($linkedBudgetRows) ?> voci Budget: non posso scegliere automaticamente quale importo aggiornare.</p>
   <a class="vw-btn" href="salvadanaio_dettaglio.php?id=<?= (int)$planning['id_salvadanaio'] ?>">Gestisci le voci Budget</a></div>
  <?php else: ?>
   <div class="vw-savings-warning" role="alert"><div class="vw-savings-warning-title"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Nessuna voce Budget collegata</div>
   <p>Aggiungi una voce Budget nel dettaglio del salvadanaio per poter sincronizzare l'importo.</p>
   <a class="vw-btn" href="salvadanaio_dettaglio.php?id=<?= (int)$planning['id_salvadanaio'] ?>">Apri salvadanaio</a></div>
  <?php endif; ?>
  <?php if($budgetMismatch): ?>
   <div class="vw-savings-warning" role="alert" aria-live="polite">
    <div class="vw-savings-warning-title"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Attenzione: importo Budget non allineato alla vacanza</div>
    <p>La voce Budget indica <strong><?= vwEuro($budgetAmount) ?></strong>, mentre il residuo da pagare dello scenario confermato è <strong><?= vwEuro($confirmedRemaining) ?></strong>.</p>
    <?php if($canEdit && has_permission($conn,'table:budget','update')): ?>
    <form method="post" class="mt-2">
     <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>">
     <input type="hidden" name="vw_action" value="sync_salvadanaio_goal">
     <button type="submit" class="vw-btn vw-btn-primary"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Aggiorna importo Budget a <?= vwEuro($confirmedRemaining) ?></button>
    </form>
    <?php endif; ?>
    <div class="vw-muted mt-2">Sarà modificato il campo Importo della voce Budget. Il saldo già accantonato non verrà toccato.</div>
   </div>
  <?php elseif($hasRemaining && $hasOneBudget): ?>
   <p class="vw-savings-aligned"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Importo Budget allineato al residuo da pagare (<?= vwEuro($confirmedRemaining) ?>).</p>
  <?php endif; ?>
  <div class="d-flex flex-wrap gap-2">
   <a class="vw-btn" href="salvadanaio_dettaglio.php?id=<?= (int)$planning['id_salvadanaio'] ?>">Apri salvadanaio</a>
   <?php if($canEdit): ?><form method="post"><input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>"><input type="hidden" name="vw_action" value="unlink_salvadanaio"><button class="vw-btn" type="submit">Scollega senza eliminare</button></form><?php endif; ?>
  </div>
 <?php elseif($confirmedTotal!==null && $canEdit): ?>
  <?php if(has_permission($conn,'table:salvadanai','insert')): ?>
  <details class="mb-3"><summary class="vw-btn">Crea nuovo salvadanaio</summary>
   <form method="post" class="d-flex flex-wrap gap-2 mt-3 align-items-end">
    <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>"><input type="hidden" name="vw_action" value="create_salvadanaio">
    <div><label class="form-label">Nome</label><input class="vw-input" name="nome_salvadanaio" maxlength="250" required value="<?= vwEsc($trip['titolo']) ?>"></div>
    <div><label class="form-label">Obiettivo (€), proposto sul residuo da pagare</label><input class="vw-input" type="number" step="0.01" min="0" name="obiettivo_salvadanaio" required value="<?= vwEsc(number_format($confirmedRemaining,2,'.','')) ?>"></div>
    <div><label class="form-label">Data obiettivo</label><input class="vw-input" type="date" name="data_scadenza" value="<?= vwEsc($trip['data_inizio']) ?>"></div>
    <button type="submit" class="vw-btn vw-btn-primary">Crea e collega</button>
   </form>
  </details><?php endif; ?>
  <?php if($savingsOptions): ?>
  <details><summary class="vw-btn">Collega salvadanaio esistente</summary>
   <form method="post" class="d-flex flex-wrap gap-2 mt-3 align-items-end">
    <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>"><input type="hidden" name="vw_action" value="link_salvadanaio">
    <div><label class="form-label">Salvadanaio</label><select name="id_salvadanaio" class="vw-input" required>
    <?php foreach($savingsOptions as $saving): ?><option value="<?= (int)$saving['id_salvadanaio'] ?>"><?= vwEsc($saving['nome_salvadanaio']) ?> · <?= vwEuro($saving['importo_attuale']) ?></option><?php endforeach; ?>
    </select></div>
    <div><label class="form-label">Obiettivo per la vacanza (€), proposto sul residuo</label><input class="vw-input" type="number" step="0.01" min="0" name="obiettivo_salvadanaio" value="<?= vwEsc(number_format($confirmedRemaining,2,'.','')) ?>"></div>
    <button type="submit" class="vw-btn vw-btn-primary">Collega</button>
   </form>
  </details><?php endif; ?>
 <?php endif; ?>
</div>
