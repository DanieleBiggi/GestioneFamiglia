<?php
declare(strict_types=1);
function api_error(int $status, string $message): never {
    throw new InvalidArgumentException($message, $status);
}
function api_str($value, int $max=255, bool $required=false): ?string {
    if (!is_string($value) || ($required && trim($value)==='') || mb_strlen($value)>$max) api_error(422,'Testo non valido');
    return trim($value);
}
require_once __DIR__ . '/../api/v1/spesa_associazioni_helper.php';
$refs=[
    ['giorno'=>'Lunedì','piatto'=>'Omelette al parmigiano'],
    ['giorno'=>'Mercoledì','piatto'=>'Patate'],
];
$note=spesa_associazioni_note($refs);
if ($note !== 'Omelette al parmigiano, Lunedì; Patate, Mercoledì') exit(1);
$withDetails=spesa_associazioni_merge_note($note,'Quantità complessiva per la settimana');
if (!str_contains($withDetails,'Quantità complessiva')) exit(2);
try {
    spesa_associazioni_note([['giorno'=>'Giorno inventato','piatto'=>'Pizza']]);
    exit(3);
} catch (InvalidArgumentException $expected) {}
echo "Associazioni spesa OK\n";
