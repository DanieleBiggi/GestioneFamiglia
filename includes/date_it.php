<?php
// Etichette data in italiano, indipendenti da setlocale() e da strftime().
function gf_month_it(int $timestamp): string
{
    $months = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
        'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    return $months[(int)date('n', $timestamp)];
}
function gf_weekday_it(int $timestamp): string
{
    $days = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    return $days[(int)date('w', $timestamp)];
}
function gf_day_label_it(int $timestamp): string
{
    return gf_weekday_it($timestamp) . ' ' . date('j', $timestamp) . ' ' . gf_month_it($timestamp);
}
