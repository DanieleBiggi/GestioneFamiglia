<?php
// URL dell'alloggio nelle note, senza modifiche allo schema MySQL.
function vacanze_alloggio_url_valido($url) {
    $url = trim((string)$url);
    if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : false;
}
function vacanze_alloggio_link_da_note($note) {
    $note = (string)$note;
    if (preg_match('/(?:^|\R)Link alloggio:\s*(https?:\/\/[^\s]+)/iu', $note, $m)) {
        return vacanze_alloggio_url_valido($m[1]) ?: '';
    }
    // Compatibilita' con URL gia' salvati nelle note tramite MCP.
    if (preg_match('~https?://[^\s<>"\']+~iu', $note, $m)) {
        return vacanze_alloggio_url_valido(rtrim($m[0], '.,;')) ?: '';
    }
    return '';
}
function vacanze_alloggio_note_con_link($note, $url) {
    $note = preg_replace('/(?:^|\R)Link alloggio:\s*https?:\/\/[^\s]+/iu', '', (string)$note);
    $note = trim($note);
    // Rimuovi il vecchio URL isolato se coincide con quello del campo dedicato.
    if ($url !== '') {
        $note = trim(preg_replace('~(?:^|\R)'.preg_quote($url, '~').'(?=\s|$)~u', '', $note));
        $note .= ($note === '' ? '' : "\n") . 'Link alloggio: ' . $url;
    }
    return $note;
}
