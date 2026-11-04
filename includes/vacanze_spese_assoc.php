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
