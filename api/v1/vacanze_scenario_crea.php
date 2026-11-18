<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
api_method('POST');
if (!$allowVacations) api_error(403, 'Accesso alle vacanze non abilitato');

/** Create a named, empty scenario for an existing family-owned vacation. */
try {
    $body=api_body();
    $trip=filter_var($body['id_viaggio']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$trip) api_error(422,'ID vacanza non valido');
    $name=api_str($body['nome']??null,100,true);
    $dry=($body['dry_run']??false)===true;
    if (!$dry) {
        $requestId=api_request_id();
        $expected=$body['expected_hash']??null;
        if (!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))
            api_error(422,'Anteprima mancante: expected_hash obbligatorio');
    }
    $conn->begin_transaction();
    $q=$conn->prepare('SELECT v.id_viaggio,v.titolo FROM viaggi v JOIN viaggi_api_famiglie f ON f.id_viaggio=v.id_viaggio WHERE v.id_viaggio=? AND f.id_famiglia=? FOR UPDATE');
    $q->bind_param('ii',$trip,$familyId);$q->execute();$vacation=$q->get_result()->fetch_assoc();$q->close();
    if (!$vacation) { $conn->rollback();api_error(404,'Vacanza non trovata o non autorizzata'); }
    $q=$conn->prepare('SELECT id_viaggio_alternativa,breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
    $q->bind_param('i',$trip);$q->execute();$existing=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
    foreach ($existing as $s) {
        if (mb_strtolower(trim($s['breve_descrizione']),'UTF-8')===mb_strtolower($name,'UTF-8')) {
            $conn->rollback();api_error(409,'Esiste gia uno scenario con questo nome');
        }
    }
    if (count($existing)>=20) {$conn->rollback();api_error(422,'Numero massimo di scenari raggiunto');}
    $hash=hash('sha256',json_encode(['id_viaggio'=>$trip,'scenari'=>$existing],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    if (!$dry&&!hash_equals($hash,$expected)) {
        $conn->rollback();api_error(409,'Gli scenari sono cambiati dopo l’anteprima: ripetere la verifica');
    }
    $report=['id_viaggio'=>$trip,'titolo_vacanza'=>$vacation['titolo'],'nome'=>$name,
        'scenari_esistenti'=>count($existing),'expected_hash'=>$hash,
        'avvertenza'=>'Viene creato uno scenario vuoto. Nessuna voce di costo, prenotazione o pagamento viene modificata.'];
    if ($dry) {$conn->rollback();api_reply(200,['success'=>true,'dry_run'=>true,'anteprima'=>$report]);}
    api_claim_request($conn,$familyId,'vacanze_scenario_crea',$requestId);
    $q=$conn->prepare('INSERT INTO viaggi_alternative (id_viaggio,breve_descrizione) VALUES (?,?)');
    $q->bind_param('is',$trip,$name);$q->execute();$sid=$conn->insert_id;$q->close();
    $conn->commit();
    api_reply(201,['success'=>true,'id_viaggio'=>$trip,'id_scenario'=>$sid,'nome'=>$name]);
} catch (Throwable $e) {
    $conn->rollback();api_json_error($e);
}
