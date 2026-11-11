<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_method('POST');
if (!$allowVacations) api_error(403, 'Accesso alle vacanze non abilitato');

/**
 * Aggiorna esclusivamente data_inizio, data_fine e notti di una vacanza.
 * Anteprima obbligatoria tramite expected_hash, richiesta idempotente.
 * Non tocca alloggi, trasporti, pagamenti, scenari o prenotazioni.
 */
function gf_vacanza_date_hash(array $row): string {
    $canonical = [
        'id_viaggio' => (int)$row['id_viaggio'],
        'data_inizio' => $row['data_inizio'],
        'data_fine' => $row['data_fine'],
        'notti' => $row['notti'] === null ? null : (int)$row['notti']
    ];
    return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES));
}
try {
    $body = api_body();
    $tripId = filter_var($body['id_viaggio'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$tripId) api_error(422, 'ID vacanza non valido');
    if (!array_key_exists('data_inizio', $body) && !array_key_exists('data_fine', $body)) {
        api_error(422, 'Indicare almeno una data da aggiornare');
    }
    $dry = ($body['dry_run'] ?? false) === true;
    $start = array_key_exists('data_inizio', $body) ? api_date($body['data_inizio']) : null;
    $end = array_key_exists('data_fine', $body) ? api_date($body['data_fine']) : null;
    if (array_key_exists('data_inizio', $body) && $start === null) api_error(422, 'Data inizio obbligatoria');
    if (array_key_exists('data_fine', $body) && $end === null) api_error(422, 'Data fine obbligatoria');
    if (!$dry) {
        $requestId = api_request_id();
        $expectedHash = $body['expected_hash'] ?? null;
        if (!is_string($expectedHash) || !preg_match('/^[a-f0-9]{64}$/D', $expectedHash)) {
            api_error(422, 'Anteprima mancante: expected_hash obbligatorio');
        }
    }

    $conn->begin_transaction();
    $stmt = $conn->prepare(
        'SELECT v.id_viaggio, v.titolo, v.data_inizio, v.data_fine, v.notti ' .
        'FROM viaggi v JOIN viaggi_api_famiglie f ON f.id_viaggio=v.id_viaggio ' .
        'WHERE v.id_viaggio=? AND f.id_famiglia=? FOR UPDATE'
    );
    $stmt->bind_param('ii', $tripId, $familyId);
    $stmt->execute();
    $original = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$original) {
        $conn->rollback();
        api_error(404, 'Vacanza non trovata o non autorizzata');
    }

    $currentHash = gf_vacanza_date_hash($original);
    if (!$dry && !hash_equals($currentHash, $expectedHash)) {
        $conn->rollback();
        api_error(409, 'Le date sono cambiate dopo l’anteprima: ripetere la verifica');
    }
    $updatedStart = $start ?? $original['data_inizio'];
    $updatedEnd = $end ?? $original['data_fine'];
    if ($updatedStart === null || $updatedEnd === null) {
        $conn->rollback();
        api_error(422, 'Per completare la modifica servono entrambe le date');
    }
    if ($updatedEnd < $updatedStart) {
        $conn->rollback();
        api_error(422, 'La data fine precede la data inizio');
    }
    $nights = (new DateTimeImmutable($updatedStart))->diff(new DateTimeImmutable($updatedEnd))->days;
    $oldDates = ['data_inizio'=>$original['data_inizio'], 'data_fine'=>$original['data_fine'], 'notti'=>$original['notti'] === null ? null : (int)$original['notti']];
    $newDates = ['data_inizio'=>$updatedStart, 'data_fine'=>$updatedEnd, 'notti'=>$nights];
    $changed = $oldDates !== $newDates;

    $report = [
        'id_viaggio'=>$tripId,
        'titolo'=>$original['titolo'],
        'precedente'=>$oldDates,
        'nuovo'=>$newDates,
        'modificato'=>$changed,
        'expected_hash'=>$currentHash,
        'avvertenza'=>'Le date delle voci di alloggio, trasporto, pasti, costi e prenotazioni non saranno modificate automaticamente.'
    ];
    if ($dry) {
        $conn->rollback();
        api_reply(200, ['success'=>true, 'dry_run'=>true, 'anteprima'=>$report]);
    }
    // La chiave idempotente viene registrata anche se le date coincidono.
    api_claim_request($conn, $familyId, 'vacanze_date', $requestId);
    if ($changed) {
        $stmt = $conn->prepare('UPDATE viaggi SET data_inizio=?, data_fine=?, notti=? WHERE id_viaggio=?');
        $stmt->bind_param('ssii', $updatedStart, $updatedEnd, $nights, $tripId);
        $stmt->execute();
        $stmt->close();
    }
    $conn->commit();
    api_reply(200, ['success'=>true, 'updated'=>$changed, 'vacanza'=>$report]);
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // Gli errori di validazione sono gestiti con api_error prima di modificare i dati.
    }
    $conn->rollback();
    api_json_error($e);
}
