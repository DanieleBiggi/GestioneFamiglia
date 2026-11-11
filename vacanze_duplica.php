<?php
// Duplicazione indipendente di una vacanza e delle relative stime, senza dati operativi.
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn,'table:viaggi','insert') || !has_permission($conn,'table:viaggi','view')) {
    http_response_code(403); exit('Accesso negato');
}
$conn->set_charset('utf8mb4');
$id = (int)($_GET['id'] ?? $_POST['id_viaggio'] ?? 0);
$q=$conn->prepare('SELECT * FROM viaggi WHERE id_viaggio=?');
$q->bind_param('i',$id);$q->execute();$trip=$q->get_result()->fetch_assoc();$q->close();
if (!$trip) { http_response_code(404); exit('Vacanza non trovata'); }
if(empty($_SESSION['vacanze_csrf'])) $_SESSION['vacanze_csrf']=bin2hex(random_bytes(32));
if(empty($_SESSION['vacanze_duplicate_nonce'])) $_SESSION['vacanze_duplicate_nonce']=bin2hex(random_bytes(16));
function duplicateVacationColumns(mysqli $conn, string $table): array {
    $result=$conn->query('SHOW COLUMNS FROM `'.$table.'`');
    return array_column($result->fetch_all(MYSQLI_ASSOC),'Field');
}
function duplicateVacationRows(mysqli $conn, string $table, string $pk, int $oldTrip, int $newTrip, array $scenarioIds): array {
    // I nomi delle tabelle sono esclusivamente quelli dichiarati nel chiamante.
    $columns=duplicateVacationColumns($conn,$table);
    $rows=$conn->query('SELECT * FROM `'.$table.'` WHERE id_viaggio='.(int)$oldTrip)->fetch_all(MYSQLI_ASSOC);
    $map=[];
    foreach($rows as $row) {
        $oldId=(int)$row[$pk]; $legacyAlt=(int)($row['id_viaggio_alternativa'] ?? 0);
        $insertCols=[]; $expressions=[];
        foreach($columns as $col) {
            if ($col===$pk) continue;
            $insertCols[]='`'.$col.'`';
            if ($col==='id_viaggio') $expressions[]=(string)$newTrip;
            elseif ($col==='id_viaggio_alternativa') $expressions[]=isset($scenarioIds[$legacyAlt])?(string)$scenarioIds[$legacyAlt]:'NULL';
            elseif ($col==='pagato') $expressions[]='0';
            elseif ($table==='viaggi_alloggi' && $col==='costo_totale_eur' && in_array('costo_totale_eur',$columns,true))
                $expressions[]='COALESCE(costo_totale_eur,GREATEST(COALESCE(DATEDIFF(data_checkout,data_checkin),0),0)*COALESCE(costo_notte_eur,0))';
            elseif (in_array($col,['data','data_checkin','data_checkout','data_prenotazione','data_pagamento'],true)) $expressions[]='NULL';
            else $expressions[]='`'.$col.'`';
        }
        $sql='INSERT INTO `'.$table.'` ('.implode(',',$insertCols).') SELECT '.implode(',',$expressions).' FROM `'.$table.'` WHERE `'.$pk.'`='.(int)$oldId.' AND id_viaggio='.(int)$oldTrip;
        if(!$conn->query($sql)) throw new RuntimeException('Impossibile duplicare '.$table);
        $map[$oldId]=(int)$conn->insert_id;
    }
    return $map;
}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['vacanze_csrf'],(string)($_POST['vacanze_csrf'] ?? ''))) { http_response_code(403); exit('Token non valido'); }
 $nonce=(string)($_POST['duplica_nonce'] ?? '');
 if(!hash_equals((string)$_SESSION['vacanze_duplicate_nonce'],$nonce)){ http_response_code(409); exit('Richiesta già inviata. Riapri la vacanza per una nuova duplicazione.'); }
 // La duplicazione crea copie reali: occorrono i permessi di inserimento.
 foreach(['viaggi_tratte','viaggi_alloggi','viaggi_pasti','viaggi_altri_costi','viaggi_checklist'] as $resource){
   if(!has_permission($conn,'table:'.$resource,'insert')) { http_response_code(403); exit('Permessi insufficienti per duplicare le voci della vacanza.'); }
 }
 if(!has_permission($conn,'ajax:add_viaggi_alternativa','insert')) { http_response_code(403); exit('Permessi insufficienti per duplicare gli scenari.'); }
 $title=trim((string)($_POST['titolo'] ?? ''));
 if($title==='' || mb_strlen($title,'UTF-8')>150) $error='Inserisci un titolo di massimo 150 caratteri.';
 else {
    try {
      $conn->begin_transaction();
      $q=$conn->prepare("INSERT INTO viaggi (titolo,id_luogo,data_inizio,data_fine,notti,persone,id_foto,breve_descrizione,stato,priorita,visibilita,note) SELECT ?,id_luogo,NULL,NULL,NULL,persone,id_foto,breve_descrizione,'idea',priorita,'private',note FROM viaggi WHERE id_viaggio=?");
      $q->bind_param('si',$title,$id);$q->execute();$newTrip=(int)$conn->insert_id;$q->close();
      if(!$newTrip) throw new RuntimeException('Non è stato possibile creare la vacanza.');
      $idFamiglia = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
      if ($idFamiglia <= 0) throw new RuntimeException('Famiglia non valida');
      $assoc = $conn->prepare('INSERT INTO viaggi_api_famiglie (id_viaggio, id_famiglia) VALUES (?, ?)');
      $assoc->bind_param('ii', $newTrip, $idFamiglia);
      if (!$assoc->execute()) throw new RuntimeException('Associazione famiglia non riuscita');
      $assoc->close();
      $alts=$conn->query('SELECT id_viaggio_alternativa,breve_descrizione FROM viaggi_alternative WHERE id_viaggio='.(int)$id)->fetch_all(MYSQLI_ASSOC);
      $scenarioIds=[];
      $q=$conn->prepare('INSERT INTO viaggi_alternative(id_viaggio,breve_descrizione) VALUES (?,?)');
      foreach($alts as $a){
         $description=$a['breve_descrizione'];
         $q->bind_param('is',$newTrip,$description);$q->execute();
         $scenarioIds[(int)$a['id_viaggio_alternativa']]=(int)$conn->insert_id;
      }
      $q->close();
      $tables=[
          ['viaggi_tratte','id_tratta','viaggi_tratte_alternative'],
          ['viaggi_alloggi','id_alloggio','viaggi_alloggi_alternative'],
          ['viaggi_pasti','id_pasto','viaggi_pasti_alternative'],
          ['viaggi_altri_costi','id_costo','viaggi_altri_costi_alternative']
      ];
      foreach($tables as [$table,$pk,$junction]){
          $map=duplicateVacationRows($conn,$table,$pk,$id,$newTrip,$scenarioIds);
          $jRows=$conn->query('SELECT a.`'.$pk.'` AS original_id,a.id_viaggio_alternativa FROM `'.$junction.'` a JOIN `'.$table.'` e ON a.`'.$pk.'`=e.`'.$pk.'` WHERE e.id_viaggio='.(int)$id)->fetch_all(MYSQLI_ASSOC);
          $q=$conn->prepare('INSERT INTO `'.$junction.'` (`'.$pk.'`,id_viaggio_alternativa) VALUES (?,?)');
          foreach($jRows as $jr){
              $oldElement=(int)$jr['original_id']; $oldAlt=(int)$jr['id_viaggio_alternativa'];
              if(!isset($map[$oldElement],$scenarioIds[$oldAlt])) continue;
              $newElement=$map[$oldElement];$newAlt=$scenarioIds[$oldAlt];
              $q->bind_param('ii',$newElement,$newAlt);$q->execute();
          }
          $q->close();
      }
      $q=$conn->prepare('INSERT INTO viaggi_checklist(id_viaggio,voce,completata,id_utente) SELECT ?,voce,0,id_utente FROM viaggi_checklist WHERE id_viaggio=?');
      $q->bind_param('ii',$newTrip,$id);$q->execute();$q->close();
      // Non duplicare viaggi_pianificazione, prenotazioni confermate, commenti,
      // feedback, condivisioni, messaggi, importazioni o movimenti finanziari.
      $conn->commit();
      $_SESSION['vacanze_duplicate_nonce']=bin2hex(random_bytes(16));
      header('Location: vacanze_workspace.php?id='.$newTrip.'&tab=overview');exit;
    } catch(Throwable $e) {
      $conn->rollback();
      $error='Duplicazione non riuscita: '.$e->getMessage();
    }
 }
}
include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/vacanze_workspace.css?v=6">
<main class="vw">
 <div class="vw-panel" style="max-width:650px;margin:25px auto">
  <h1>Duplica vacanza</h1>
  <p class="vw-muted">Originale: <?= htmlspecialchars($trip['titolo'],ENT_QUOTES,'UTF-8') ?>. La copia partirà da Idea, con date da definire, stime e checklist azzerate. Nessuna prenotazione, pagamento, condivisione o salvadanaio sarà trasferito.</p>
  <?php if($error!==''): ?><p role="alert" class="vw-empty"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?>
  <form method="post">
   <input type="hidden" name="id_viaggio" value="<?= $id ?>">
   <input type="hidden" name="duplica_nonce" value="<?= htmlspecialchars($_SESSION['vacanze_duplicate_nonce'],ENT_QUOTES,'UTF-8') ?>">
   <input type="hidden" name="vacanze_csrf" value="<?= htmlspecialchars($_SESSION['vacanze_csrf'],ENT_QUOTES,'UTF-8') ?>">
   <label class="form-label" for="newTripTitle">Nome nuova vacanza</label>
   <input class="vw-input w-100 mb-3" id="newTripTitle" name="titolo" maxlength="150" required value="<?= htmlspecialchars($_POST['titolo'] ?? ($trip['titolo'].' (Copia)'),ENT_QUOTES,'UTF-8') ?>">
   <div class="d-flex gap-2"><button type="submit" class="vw-btn vw-btn-primary">Duplica vacanza</button><a class="vw-btn" href="vacanze_workspace.php?id=<?= $id ?>">Annulla</a></div>
  </form>
 </div>
</main>
<?php include 'includes/footer.php'; ?>
