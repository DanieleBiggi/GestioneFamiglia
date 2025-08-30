<?php
include '../includes/db.php';
header('Content-Type: application/json');
$placeId = $_GET['place_id'] ?? '';
$apiKey = $config['GOOGLE_PLACES_FOTO_API'] ?? '';
if (!$apiKey || !$placeId) {
    echo json_encode(['photos' => []]);
    exit;
}
$url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id=' . urlencode($placeId) . '&fields=photos&key=' . $apiKey;
//echo $url;
$resp = @file_get_contents($url);
if ($resp === false) {
    echo json_encode(['photos' => []]);
    exit;
}
$data = json_decode($resp, true);
$out = [];
if (!empty($data['result']['photos'])) {
    foreach ($data['result']['photos'] as $idx => $ph) {
        $ref = $ph['photo_reference'] ?? '';
        if (!$ref) continue;
        $thumb = 'https://maps.googleapis.com/maps/api/place/photo?maxwidth=200&photo_reference=' . urlencode($ref) . '&key=' . $apiKey;
        $attrib = $ph['html_attributions'][0] ?? '';
        $out[] = [
            'photo_reference' => $ref,
            'thumb_url' => $thumb,
            'attribution_html' => $attrib,
            'posizione' => $idx
        ];
    }
}
echo json_encode(['photos' => $out]);
