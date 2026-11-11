<?php
/**
 * Collegamenti spesa/scenari. L'ID legacy rimane un riferimento di compatibilità
 * per il vecchio codice, ma non è più la fonte per i totali.
 */
function vacanze_sync_spesa_alternative(mysqli $conn, string $table, int $idSpesa, int $idViaggio, array $ids): void {
    $map = [
        'viaggi_tratte' => ['viaggi_tratte_alternative','id_tratta'],
        'viaggi_alloggi' => ['viaggi_alloggi_alternative','id_alloggio'],
        'viaggi_pasti' => ['viaggi_pasti_alternative','id_pasto'],
        'viaggi_altri_costi' => ['viaggi_altri_costi_alternative','id_costo']
    ];
    if (!isset($map[$table]) || $idSpesa <= 0 || $idViaggio <= 0) {
        throw new InvalidArgumentException('Spesa non valida');
    }
    [$linkTable,$idField] = $map[$table];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    foreach ($ids as $idAlt) {
        if ($idAlt <= 0) { throw new InvalidArgumentException('Alternativa non valida'); }
        $check = $conn->prepare('SELECT 1 FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
        $check->bind_param('ii',$idViaggio,$idAlt);
        $check->execute();
        if (!$check->get_result()->fetch_row()) { throw new InvalidArgumentException('Alternativa non appartenente alla vacanza'); }
        $check->close();
    }
    $exists = $conn->prepare("SELECT 1 FROM `$table` WHERE `$idField`=? AND id_viaggio=?");
    $exists->bind_param('ii',$idSpesa,$idViaggio);
    $exists->execute();
    if (!$exists->get_result()->fetch_row()) { throw new InvalidArgumentException('Spesa non appartenente alla vacanza'); }
    $exists->close();
    $del = $conn->prepare("DELETE FROM `$linkTable` WHERE `$idField`=?");
    $del->bind_param('i',$idSpesa);
    $del->execute();
    $del->close();
    $ins = $conn->prepare("INSERT INTO `$linkTable` (`$idField`,id_viaggio_alternativa) VALUES (?,?)");
    foreach ($ids as $idAlt) {
        $ins->bind_param('ii',$idSpesa,$idAlt);
        if (!$ins->execute()) { throw new RuntimeException('Errore collegamento spesa'); }
    }
    $ins->close();
    $legacy = count($ids) ? $ids[0] : null;
    $upd = $conn->prepare("UPDATE `$table` SET id_viaggio_alternativa=? WHERE `$idField`=? AND id_viaggio=?");
    $upd->bind_param('iii',$legacy,$idSpesa,$idViaggio);
    $upd->execute();
    $upd->close();
}
function vacanze_spesa_alternative(mysqli $conn, string $linkTable, string $idField, int $idSpesa): array {
    $q=$conn->prepare("SELECT id_viaggio_alternativa FROM `$linkTable` WHERE `$idField`=? ORDER BY id_viaggio_alternativa");
    $q->bind_param('i',$idSpesa);$q->execute();
    $result=array_map('intval',array_column($q->get_result()->fetch_all(MYSQLI_ASSOC),'id_viaggio_alternativa'));
    $q->close();
    return $result;
}

/**
 * Separa una spesa condivisa per un solo scenario.
 * Il chiamante deve avere gia' verificato CSRF e permessi di inserimento/modifica.
 * La transazione comprende copia, associazioni e riallineamento del riferimento legacy.
 */
function vacanze_separa_spesa_scenario(mysqli $conn, string $table, int $idSpesa, int $idViaggio, int $idAlt): int {
    $map = [
        'viaggi_tratte' => ['viaggi_tratte_alternative','id_tratta'],
        'viaggi_alloggi' => ['viaggi_alloggi_alternative','id_alloggio'],
        'viaggi_pasti' => ['viaggi_pasti_alternative','id_pasto'],
        'viaggi_altri_costi' => ['viaggi_altri_costi_alternative','id_costo']
    ];
    if (!isset($map[$table]) || $idSpesa <= 0 || $idViaggio <= 0 || $idAlt <= 0) {
        throw new InvalidArgumentException('Parametri spesa non validi');
    }
    [$linkTable, $idField] = $map[$table];
    $conn->begin_transaction();
    try {
        $q = $conn->prepare("SELECT `$idField` FROM `$table` WHERE `$idField`=? AND id_viaggio=? FOR UPDATE");
        $q->bind_param('ii', $idSpesa, $idViaggio);
        $q->execute();
        $exists = $q->get_result()->fetch_row();
        $q->close();
        if (!$exists) throw new InvalidArgumentException('Spesa non appartenente alla vacanza');
        $q = $conn->prepare('SELECT id_viaggio_alternativa FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
        $q->bind_param('ii', $idViaggio, $idAlt);
        $q->execute();
        $validAlt = $q->get_result()->fetch_row();
        $q->close();
        if (!$validAlt) throw new InvalidArgumentException('Scenario non appartenente alla vacanza');
        $ids = vacanze_spesa_alternative($conn, $linkTable, $idField, $idSpesa);
        if (count($ids) < 2 || !in_array($idAlt, $ids, true)) {
            throw new InvalidArgumentException('La spesa non e\' condivisa con lo scenario scelto');
        }
        // Copia tutte le colonne dati, comprese quelle aggiunte in futuro, esclusa la PK.
        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM `$table`");
        while ($column = $res->fetch_assoc()) {
            if ($column['Field'] !== $idField) $columns[] = '`' . $column['Field'] . '`';
        }
        $list = implode(',', $columns);
        $copy = $conn->prepare("INSERT INTO `$table` ($list) SELECT $list FROM `$table` WHERE `$idField`=? AND id_viaggio=?");
        $copy->bind_param('ii', $idSpesa, $idViaggio);
        $copy->execute();
        if ($copy->affected_rows !== 1) throw new RuntimeException('Copia della spesa non riuscita');
        $newId = (int)$conn->insert_id;
        $copy->close();
        $remaining = array_values(array_diff($ids, [$idAlt]));
        vacanze_sync_spesa_alternative($conn, $table, $idSpesa, $idViaggio, $remaining);
        vacanze_sync_spesa_alternative($conn, $table, $newId, $idViaggio, [$idAlt]);
        $conn->commit();
        return $newId;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
