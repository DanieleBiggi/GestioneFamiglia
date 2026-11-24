<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_method('GET');

require_once __DIR__ . '/../../includes/menu_cene_master.php';
require_once __DIR__ . '/menu_cene_edit_helper.php';

/**
 * Lettura senza effetti collaterali. Diversamente da ajax/get_menu_cene.php,
 * NON genera né cancella righe in menu_cene_settimanale.
 *
 * GET ?week_start=YYYY-MM-DD (lunedì, opzionale: prossimo lunedì).
 */
try {
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
    $defaultMonday = $today->modify('next monday');
    $requested = $_GET['week_start'] ?? $defaultMonday->format('Y-m-d');
    if (!is_string($requested) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $requested)) {
        api_error(422, 'week_start deve essere un lunedì nel formato YYYY-MM-DD');
    }
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $requested, new DateTimeZone('Europe/Rome'));
    if (!$start || $start->format('Y-m-d') !== $requested || $start->format('N') !== '1') {
        api_error(422, 'week_start deve essere un lunedì valido');
    }
    $weekStart = $start->format('Y-m-d');
    $weekEnd = $start->modify('+6 days')->format('Y-m-d');

    // Caricare la configurazione della famiglia esplicita, mai dalla sessione web.
    $customMaster = menu_cene_effective_config($conn, (int)$familyId);
    $plan = menu_cene_plan($start, $customMaster);

    $stmt = $conn->prepare(
        'SELECT giorno, piatto FROM menu_cene_settimanale WHERE id_famiglia=? AND week_start=? ORDER BY id ASC'
    );
    $stmt->bind_param('is', $familyId, $weekStart);
    $stmt->execute();
    $savedRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $saved = [];
    foreach ($savedRows as $row) {
        $day = gf_menu_normalize_day((string)$row['giorno']);
        if ($day !== null) $saved[$day] = $row['piatto'];
    }

    $days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'];
    $items = [];
    foreach ($days as $i => $day) {
        $isSaved = array_key_exists($day, $saved);
        $items[] = [
            'giorno' => $day,
            'data' => $start->modify('+' . $i . ' days')->format('Y-m-d'),
            'piatto' => $isSaved ? (string)$saved[$day] : menu_cene_format_dish($plan['giorni'][$day] ?? []),
            'origine' => $isSaved ? 'salvato' : 'previsto_master'
        ];
    }

    $recipes = food_config_match_recipes($items, menu_cene_effective_recipes($conn, (int)$familyId));
    api_reply(200, [
        'success' => true,
        'week' => ['start'=>$weekStart, 'end'=>$weekEnd],
        'plan' => ['stagione'=>$plan['stagione'], 'settimana_ciclo'=>$plan['settimana_menu']],
        'items' => $items,
        'ricette' => $recipes['ricette'],
        'non_associati' => $recipes['non_associati'],
        'notice' => 'I piatti con origine previsto_master non sono ancora salvati. La lettura non modifica il menù. Le ricette contengono istruzioni, non una lista strutturata di ingredienti e quantità.'
    ]);
} catch (Throwable $e) {
    api_json_error($e);
}
