<?php
require_once __DIR__ . '/../includes/date_it.php';
date_default_timezone_set('Europe/Rome');
$cases = [
    ['2026-11-06 12:00:00', 'novembre', 'venerdì 6 novembre'],
    ['2027-05-08 12:00:00', 'maggio', 'sabato 8 maggio'],
    ['2027-02-01 12:00:00', 'febbraio', 'lunedì 1 febbraio'],
];
foreach ($cases as $case) {
    $timestamp = strtotime($case[0]);
    if (gf_month_it($timestamp) !== $case[1] || gf_day_label_it($timestamp) !== $case[2]) {
        fwrite(STDERR, 'Data italiana non corretta: ' . $case[0] . PHP_EOL);
        exit(1);
    }
}
echo "Date italiane OK\n";
