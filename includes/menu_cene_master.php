<?php

/**
 * Menu cene famiglia 2026-2027.
 *
 * Ciclo ufficiale: la settimana che inizia il 14/09/2026 è la settimana 2;
 * il ciclo prosegue 2 -> 3 -> 4 -> 1.
 *
 * Stagione: estivo fino alla settimana precedente il primo lunedì di novembre;
 * invernale dal primo lunedì di novembre fino alla settimana che contiene il
 * secondo venerdì di aprile; dalla settimana successiva torna estivo.
 */

function menu_cene_master(): array
{
    return [
        'Estivo' => [
            1 => [
                'Lunedì' => ['cena' => 'Polpette di carne', 'contorno' => 'Insalata + pane'],
                'Martedì' => ['cena' => 'Torta salata zucchine e scamorza', 'contorno' => 'Pomodori'],
                'Mercoledì' => ['cena' => 'Salmone in padella', 'contorno' => 'Patate al forno + zucchine'],
                'Giovedì' => ['cena' => 'Pasta al pesto', 'contorno' => 'Parmigiano + carote'],
                'Venerdì' => ['cena' => 'Toast prosciutto e formaggio', 'contorno' => 'Insalata'],
            ],
            2 => [
                'Lunedì' => ['cena' => 'Omelette al parmigiano', 'contorno' => 'Pomodori + pane'],
                'Martedì' => ['cena' => 'Pasta al pomodoro', 'contorno' => 'Mozzarella + zucchine'],
                'Mercoledì' => ['cena' => 'Merluzzo alla mediterranea', 'contorno' => 'Patate'],
                'Giovedì' => ['cena' => 'Caprese', 'contorno' => 'Pane o focaccia'],
                'Venerdì' => ['cena' => 'Pizza', 'contorno' => 'Insalata'],
            ],
            3 => [
                'Lunedì' => ['cena' => 'Straccetti di pollo con peperoni', 'contorno' => 'Pane'],
                'Martedì' => ['cena' => 'Salmone in padella', 'contorno' => 'Patate + zucchine'],
                'Mercoledì' => ['cena' => 'Pasta al pomodoro', 'contorno' => 'Parmigiano + zucchine'],
                'Giovedì' => ['cena' => 'Bocconcini di pollo al limone', 'contorno' => 'Patate + carote'],
                'Venerdì' => ['cena' => 'Hamburger', 'contorno' => 'Pane + pomodori'],
            ],
            4 => [
                'Lunedì' => ['cena' => 'Pollo al limone', 'contorno' => 'Patate + insalata'],
                'Martedì' => ['cena' => 'Pasta zafferano, speck e zucchine', 'contorno' => 'Frutta a fine pasto'],
                'Mercoledì' => ['cena' => 'Toast prosciutto e formaggio', 'contorno' => 'Pomodorini'],
                'Giovedì' => ['cena' => 'Frittata di zucchine', 'contorno' => 'Pomodori + pane'],
                'Venerdì' => ['cena' => 'Pizza', 'contorno' => 'Insalata'],
            ],
        ],
        'Invernale' => [
            1 => [
                'Lunedì' => ['cena' => 'Polpette al sugo', 'contorno' => 'Patate'],
                'Martedì' => ['cena' => 'Torta salata zucchine e scamorza', 'contorno' => 'Carote'],
                'Mercoledì' => ['cena' => 'Salmone in padella', 'contorno' => 'Patate + spinaci'],
                'Giovedì' => ['cena' => 'Pasta al pesto', 'contorno' => 'Parmigiano + finocchi'],
                'Venerdì' => ['cena' => 'Toast prosciutto e formaggio', 'contorno' => 'Insalata'],
            ],
            2 => [
                'Lunedì' => ['cena' => 'Omelette al parmigiano', 'contorno' => 'Patate + carote'],
                'Martedì' => ['cena' => 'Pasta al pomodoro', 'contorno' => 'Mozzarella + spinaci'],
                'Mercoledì' => ['cena' => 'Merluzzo alla mediterranea', 'contorno' => 'Patate + finocchi gratinati'],
                'Giovedì' => ['cena' => 'Zuppa di lenticchie', 'contorno' => 'Pane o crostini'],
                'Venerdì' => ['cena' => 'Pizza', 'contorno' => 'Insalata'],
            ],
            3 => [
                'Lunedì' => ['cena' => 'Straccetti di pollo con carote', 'contorno' => 'Pane'],
                'Martedì' => ['cena' => 'Salmone in padella', 'contorno' => 'Patate + spinaci'],
                'Mercoledì' => ['cena' => 'Pasta al ragù', 'contorno' => 'Carote'],
                'Giovedì' => ['cena' => 'Bocconcini di pollo al limone', 'contorno' => 'Pane + finocchi gratinati'],
                'Venerdì' => ['cena' => 'Hamburger', 'contorno' => 'Pane + pomodori'],
            ],
            4 => [
                'Lunedì' => ['cena' => 'Pollo al Marsala', 'contorno' => 'Patate + carote'],
                'Martedì' => ['cena' => 'Pasta zafferano, speck e zucchine', 'contorno' => 'Frutta a fine pasto'],
                'Mercoledì' => ['cena' => 'Polpette di carne', 'contorno' => 'Patate + spinaci'],
                'Giovedì' => ['cena' => 'Frittata di zucchine', 'contorno' => 'Pane + finocchi'],
                'Venerdì' => ['cena' => 'Pizza', 'contorno' => 'Insalata'],
            ],
        ],
    ];
}

function menu_cene_week_start(DateTimeImmutable $date): DateTimeImmutable
{
    return $date->modify('monday this week')->setTime(0, 0);
}

function menu_cene_cycle_week(DateTimeImmutable $weekStart): int
{
    $anchor = new DateTimeImmutable('2026-09-14'); // settimana menu 2
    $anchor = menu_cene_week_start($anchor);
    $weekStart = menu_cene_week_start($weekStart);
    $days = (int)$anchor->diff($weekStart)->format('%r%a');
    $weeksDiff = intdiv($days, 7);
    return ((2 - 1 + $weeksDiff) % 4 + 4) % 4 + 1;
}

function menu_cene_first_monday_of_november(int $year): DateTimeImmutable
{
    $date = new DateTimeImmutable(sprintf('%04d-11-01', $year));
    return $date->modify('monday this week')->format('m') === '11'
        ? $date->modify('monday this week')
        : $date->modify('next monday');
}

function menu_cene_second_friday_of_april(int $year): DateTimeImmutable
{
    $date = new DateTimeImmutable(sprintf('%04d-04-01', $year));
    $firstFriday = $date->modify('friday this week');
    if ($firstFriday->format('m') !== '04') {
        $firstFriday = $date->modify('next friday');
    }
    return $firstFriday->modify('+1 week');
}

function menu_cene_season(DateTimeImmutable $weekStart): string
{
    $weekStart = menu_cene_week_start($weekStart);
    $year = (int)$weekStart->format('Y');

    $winterStart = menu_cene_first_monday_of_november($year);
    if ($weekStart >= $winterStart) {
        return 'Invernale';
    }

    $winterEndFriday = menu_cene_second_friday_of_april($year);
    $summerRestart = menu_cene_week_start($winterEndFriday)->modify('+1 week');
    return $weekStart < $summerRestart ? 'Invernale' : 'Estivo';
}

function menu_cene_plan(DateTimeImmutable $weekStart): array
{
    $season = menu_cene_season($weekStart);
    $cycleWeek = menu_cene_cycle_week($weekStart);
    $master = menu_cene_master();

    return [
        'stagione' => $season,
        'settimana_menu' => $cycleWeek,
        'giorni' => $master[$season][$cycleWeek] ?? [],
    ];
}

function menu_cene_format_dish(array $item): string
{
    $cena = trim($item['cena'] ?? '');
    $contorno = trim($item['contorno'] ?? '');
    return $contorno !== '' ? $cena . "\n" . $contorno : $cena;
}
