<?php
declare(strict_types=1);

/** Converte associazioni esplicite nel formato storicamente usato da Verifica menù. */
function spesa_associazioni_note(array $associazioni): string {
    if (!$associazioni || count($associazioni) > 20 || array_values($associazioni) !== $associazioni) {
        api_error(422, 'Associazioni mancanti o eccessive');
    }
    $days = ['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato','Domenica'];
    $notes = [];
    foreach ($associazioni as $ref) {
        if (!is_array($ref) || !in_array($ref['giorno'] ?? null, $days, true)) {
            api_error(422, 'Giorno associazione non valido');
        }
        $piatto = api_str($ref['piatto'] ?? null, 120, true);
        if (strpbrk($piatto, ",;\n\r") !== false) {
            api_error(422, 'Nome del piatto non valido per associazione');
        }
        $notes[] = $piatto . ', ' . $ref['giorno'];
    }
    return implode('; ', array_unique($notes));
}
function spesa_associazioni_merge_note(string $prefix, ?string $extra): string {
    $extra = trim((string)$extra);
    $note = $prefix . ($extra !== '' ? '; ' . $extra : '');
    if (mb_strlen($note, 'UTF-8') > 255) api_error(422, 'Note troppo lunghe (massimo 255 caratteri): ridurre descrizioni o associazioni');
    return $note;
}
