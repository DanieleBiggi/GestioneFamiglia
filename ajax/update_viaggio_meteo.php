<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:update_viaggio_meteo', 'update')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$id = (int)($_POST['id'] ?? 0);
$meteo_json = $_POST['meteo_previsto_json'] ?? '';
$meteo_time = $_POST['meteo_aggiornato_il'] ?? '';
if (!$id || $meteo_json === '' || $meteo_time === '') { echo json_encode(['success'=>false,'error'=>'Dati mancanti']); exit; }
$stmt = $conn->prepare('UPDATE viaggi SET meteo_previsto_json=?, meteo_aggiornato_il=? WHERE id_viaggio=?');
$stmt->bind_param('ssi', $meteo_json, $meteo_time, $id);
$ok = $stmt->execute();
echo json_encode(['success'=>$ok]);
?>
