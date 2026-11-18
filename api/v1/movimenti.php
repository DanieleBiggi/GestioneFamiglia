<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_method('GET');

// Bank transactions are private to a user, not to the entire family.
// Never accept user_id from the HTTP client or default to a family member.
$userId = filter_var($apiConfig['movimenti_user_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$userId) api_error(503, 'Lettura movimenti non configurata');
if ($oauthAccess !== null && (int)$oauthAccess['id_utente'] !== (int)$userId) api_error(403, 'Accesso ai movimenti non autorizzato');
$membership = $conn->prepare('SELECT 1 FROM utenti2famiglie uf JOIN utenti u ON u.id=uf.id_utente WHERE uf.id_utente=? AND uf.id_famiglia=? AND u.attivo=1 LIMIT 1');
$membership->bind_param('ii', $userId, $familyId);
$membership->execute();
$member = $membership->get_result()->fetch_row();
$membership->close();
if (!$member) api_error(403, 'Utente non associato alla famiglia');

$mode = $_GET['mode'] ?? 'search';
if (!in_array($mode, ['search','detail'], true)) api_error(422, 'Modalità non valida');
$tables = ['bilancio_uscite','bilancio_entrate','movimenti_revolut'];
$source = $_GET['source'] ?? '';
if (!is_string($source) || ($source !== '' && !in_array($source, $tables, true))) api_error(422, 'Fonte non valida');
if ($source === 'movimenti_revolut' && (int)$familyId !== 1) api_error(403, 'Fonte non disponibile');
try {
    $parts = [];
    // Imported bank files may encode debit amounts as negative already.
    // ABS ensures a consistent sign without modifying the stored original.
    if ($source === '' || $source === 'bilancio_uscite') {
        $parts[] = "SELECT id_uscita AS id, 'bilancio_uscite' AS source, data_operazione AS data, -ABS(importo) AS importo, descrizione_operazione AS descrizione_originale, descrizione_extra AS descrizione_extra, mezzo FROM bilancio_uscite WHERE id_utente=" . (int)$userId;
    }
    if ($source === '' || $source === 'bilancio_entrate') {
        $parts[] = "SELECT id_entrata AS id, 'bilancio_entrate' AS source, data_operazione AS data, ABS(importo) AS importo, descrizione_operazione AS descrizione_originale, descrizione_extra AS descrizione_extra, mezzo FROM bilancio_entrate WHERE id_utente=" . (int)$userId;
    }
    // Revolut records in the legacy schema are shared by the specific family #1.
    // This source is available only to the explicitly configured bank-data owner.
    if (($source === '' || $source === 'movimenti_revolut') && (int)$familyId === 1) {
        $parts[] = "SELECT id_movimento_revolut AS id, 'movimenti_revolut' AS source, started_date AS data, amount AS importo, description AS descrizione_originale, descrizione_extra AS descrizione_extra, 'revolut' AS mezzo FROM v_movimenti_revolut_filtrati";
    }
    $base = 'SELECT * FROM (' . implode(' UNION ALL ', $parts) . ') AS t';
    if ($mode === 'detail') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id || $source === '') api_error(422, 'source e id obbligatori');
        $sql = $base . ' WHERE source=? AND id=? LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si',$source,$id);
        $stmt->execute();
        $movement = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$movement) api_error(404, 'Movimento non trovato');
        api_reply(200,['success'=>true,'movimento'=>$movement]);
    }

    $from = api_date($_GET['from'] ?? null);
    $to = api_date($_GET['to'] ?? null);
    if (!$from || !$to || $from > $to) api_error(422,'Intervallo from/to obbligatorio e valido');
    $start = new DateTimeImmutable($from);
    $end = new DateTimeImmutable($to);
    if ($start->diff($end)->days > 366) api_error(422,'Intervallo massimo 366 giorni');
    $q = api_str($_GET['q'] ?? null, 150);
    $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT);
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 100 || $offset === false || $offset < 0 || $offset > 5000) api_error(422,'Paginazione non valida');
    $where = ' WHERE data >= ? AND data < DATE_ADD(?, INTERVAL 1 DAY)';
    if ($q !== null) $where .= ' AND (descrizione_originale LIKE ? OR descrizione_extra LIKE ?)';
    $sql = $base . $where . ' ORDER BY data DESC, source, id DESC LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);
    if ($q !== null) {
        $like = '%' . $q . '%';
        $stmt->bind_param('ssssii',$from,$to,$like,$like,$limit,$offset);
    } else {
        $stmt->bind_param('ssii',$from,$to,$limit,$offset);
    }
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    api_reply(200,['success'=>true,'movimenti'=>$items,'limit'=>$limit,'offset'=>$offset,'has_more'=>count($items)===$limit]);
} catch (Throwable $e) {
    api_json_error($e);
}
