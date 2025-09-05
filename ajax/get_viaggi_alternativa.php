<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:get_viaggi_alternativa', 'view')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idViaggio = (int)($_GET['id_viaggio'] ?? 0);
$idAlt = (int)($_GET['id_alternativa'] ?? 0);
if (!$idViaggio || !$idAlt) { echo json_encode(['success'=>false,'error'=>'Parametri mancanti']); exit; }

$stmt = $conn->prepare('SELECT breve_descrizione, totale_trasporti, totale_alloggi, totale_pasti, totale_altri_costi, totale_viaggio FROM v_totali_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
$stmt->bind_param('ii', $idViaggio, $idAlt);
$stmt->execute();
$alt = $stmt->get_result()->fetch_assoc();
if (!$alt) { echo json_encode(['success'=>false,'error'=>'Alternativa non trovata']); exit; }

$trStmt = $conn->prepare('SELECT descrizione, tipo_tratta, origine_testo, destinazione_testo, ((COALESCE(distanza_km,0)*COALESCE(consumo_litri_100km,0)/100)*COALESCE(prezzo_carburante_eur_litro,0) + COALESCE(pedaggi_eur,0) + COALESCE(costo_traghetto_eur,0) + COALESCE(costo_volo_eur,0) + COALESCE(costo_noleggio_eur,0) + COALESCE(altri_costi_eur,0)) AS totale FROM viaggi_tratte WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY id_tratta');
$trStmt->bind_param('ii', $idViaggio, $idAlt);
$trStmt->execute();
$trRes = $trStmt->get_result();
$tratte = [];
while ($row = $trRes->fetch_assoc()) {
    $tratte[] = [
        'descrizione' => $row['descrizione'],
        'tipo_tratta' => $row['tipo_tratta'],
        'origine_testo' => $row['origine_testo'],
        'destinazione_testo' => $row['destinazione_testo'],
        'totale' => number_format($row['totale'], 2, ',', '.')
    ];
}

$allStmt = $conn->prepare('SELECT nome_alloggio, data_checkin, data_checkout, DATEDIFF(data_checkout, data_checkin) * COALESCE(costo_notte_eur,0) AS totale FROM viaggi_alloggi WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY id_alloggio');
$allStmt->bind_param('ii', $idViaggio, $idAlt);
$allStmt->execute();
$allRes = $allStmt->get_result();
$alloggi = [];
while ($row = $allRes->fetch_assoc()) {
    $alloggi[] = [
        'nome_alloggio' => $row['nome_alloggio'],
        'data_checkin' => $row['data_checkin'],
        'data_checkout' => $row['data_checkout'],
        'totale' => number_format($row['totale'], 2, ',', '.')
    ];
}

$paStmt = $conn->prepare('SELECT giorno_indice, tipo_pasto, nome_locale, tipologia FROM viaggi_pasti WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY giorno_indice, id_pasto');
$paStmt->bind_param('ii', $idViaggio, $idAlt);
$paStmt->execute();
$paRes = $paStmt->get_result();
$pasti = [];
$mealCounts = [
    'colazione' => ['ristorante' => 0, 'cucinato' => 0],
    'pranzo'    => ['ristorante' => 0, 'cucinato' => 0],
    'cena'      => ['ristorante' => 0, 'cucinato' => 0],
];
while ($row = $paRes->fetch_assoc()) {
    $pasti[] = [
        'giorno_indice' => (int)$row['giorno_indice'],
        'tipo_pasto' => $row['tipo_pasto'],
        'nome_locale' => $row['nome_locale'],
        'tipologia' => $row['tipologia'],
    ];
    $tipo = $row['tipo_pasto'];
    $key = $row['tipologia'] === 'cucinato' ? 'cucinato' : 'ristorante';
    $mealCounts[$tipo][$key]++;
}

$coStmt = $conn->prepare('SELECT data, importo_eur, note FROM viaggi_altri_costi WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY data, id_costo');
$coStmt->bind_param('ii', $idViaggio, $idAlt);
$coStmt->execute();
$coRes = $coStmt->get_result();
$altriCosti = [];
while ($row = $coRes->fetch_assoc()) {
    $altriCosti[] = [
        'data' => $row['data'],
        'note' => $row['note'],
        'totale' => number_format($row['importo_eur'], 2, ',', '.')
    ];
}

echo json_encode([
    'success' => true,
    'breve_descrizione' => $alt['breve_descrizione'],
    'totale_trasporti' => number_format($alt['totale_trasporti'], 2, ',', '.'),
    'totale_alloggi' => number_format($alt['totale_alloggi'], 2, ',', '.'),
    'totale_pasti' => number_format($alt['totale_pasti'], 2, ',', '.'),
    'totale_altri_costi' => number_format($alt['totale_altri_costi'], 2, ',', '.'),
    'totale_viaggio' => number_format($alt['totale_viaggio'], 2, ',', '.'),
    'tratte' => $tratte,
    'alloggi' => $alloggi,
    'pasti' => $pasti,
    'meal_counts' => $mealCounts,
    'altri_costi' => $altriCosti
]);
?>
