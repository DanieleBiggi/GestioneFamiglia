<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_method('GET');
$userId = filter_var($apiConfig['movimenti_user_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$userId) api_error(503,'Movimenti non configurati');
if ($oauthAccess !== null && (int)$oauthAccess['id_utente'] !== (int)$userId) api_error(403,'Utente non autorizzato');
$q=$conn->prepare('SELECT 1 FROM utenti2famiglie uf JOIN utenti u ON u.id=uf.id_utente WHERE uf.id_utente=? AND uf.id_famiglia=? AND u.attivo=1 LIMIT 1');
$q->bind_param('ii',$userId,$familyId);$q->execute();$member=(bool)$q->get_result()->fetch_row();$q->close();
if (!$member) api_error(403,'Utente non autorizzato');
$mode=$_GET['mode'] ?? '';
if (!in_array($mode,['etichette','gruppi'],true)) api_error(422,'Modalità non valida');
try {
    if ($mode==='gruppi') {
        $q=$conn->prepare('SELECT id_gruppo_transazione AS id,descrizione,tipo_gruppo,attivo FROM bilancio_gruppi_transazione WHERE id_utente=? ORDER BY descrizione');
        $q->bind_param('i',$userId);
    } else {
        if ((int)$familyId!==1) api_error(403,'Etichette legacy non disponibili');
        $q=$conn->prepare('SELECT id_etichetta AS id,descrizione,attivo,anno,mese FROM bilancio_etichette ORDER BY descrizione');
    }
    $q->execute();
    $items=$q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();
    api_reply(200,['success'=>true,'tipo'=>$mode,'elementi'=>$items]);
} catch(Throwable $e) { api_json_error($e); }
