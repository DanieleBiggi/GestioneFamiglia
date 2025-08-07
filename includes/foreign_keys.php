<?php
return [
    'id_utente' => [
        'table' => 'utenti',
        'key'   => 'id',
        'label' => "CONCAT(nome, ' ', cognome)"
    ],
    'id_gruppo_transazione' => [
        'table' => 'bilancio_gruppi_transazione',
        'key'   => 'id_gruppo_transazione',
        'label' => 'descrizione'
    ],
    'id_categoria' => [
        'table' => 'bilancio_gruppi_categorie',
        'key'   => 'id_categoria',
        'label' => 'descrizione_categoria'
    ],
    'id_famiglia' => [
        'table' => 'famiglie',
        'key'   => 'id_famiglia',
        'label' => 'nome_famiglia'
    ]
];
?>
