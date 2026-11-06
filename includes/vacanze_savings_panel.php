<?php
// Included from vacanze_workspace.php after loading $trip, $scenarios and $planning.
?>
<div class="vw-panel" id="vacanza-salvadanaio">
 <div class="vw-section-head"><h2>Scenario confermato e salvadanaio</h2></div>
 <?php if($confirmedName!==null): ?><p><strong><?= vwEsc($confirmedName) ?></strong> · Preventivo <?= vwEuro($confirmedTotal) ?></p>
 <?php else: ?><p class="vw-muted">Conferma uno scenario qui sotto prima di collegare un salvadanaio.</p><?php endif; ?>
 <?php if(!empty($planning['id_salvadanaio'])): ?>
  <p><strong><?= vwEsc($planning['nome_salvadanaio']) ?></strong> · Accantonato <?= vwEuro($planning['importo_attuale']) ?></p>
  <?php if($planning['obiettivo_salvadanaio']!==null): $goal=(float)$planning['obiettivo_salvadanaio']; ?>
   <p>Obiettivo <?= vwEuro($goal) ?> · <?= $goal>0?number_format(min(100,100*(float)$planning['importo_attuale']/$goal),1,',','.').'%':'Obiettivo da definire' ?></p>
   <?php if($confirmedTotal!==null && abs($goal-$confirmedTotal)>0.009 && $canEdit): ?>
   <form method="post" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>"><input type="hidden" name="vw_action" value="update_salvadanaio_goal">
    <input type="hidden" name="obiettivo_salvadanaio" value="<?= vwEsc(number_format($confirmedTotal,2,'.','')) ?>">
    <span class="vw-muted">Il preventivo è cambiato.</span><button class="vw-btn" type="submit">Aggiorna obiettivo a <?= vwEuro($confirmedTotal) ?></button>
   </form><?php endif; ?>
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
    <div><label class="form-label">Obiettivo (€)</label><input class="vw-input" type="number" step="0.01" min="0" name="obiettivo_salvadanaio" required value="<?= vwEsc(number_format($confirmedTotal,2,'.','')) ?>"></div>
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
    </select></div><button type="submit" class="vw-btn vw-btn-primary">Collega</button>
   </form>
  </details><?php endif; ?>
 <?php endif; ?>
</div>
