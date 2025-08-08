<?php
return [
    'bilancio_descrizione2id' => [
        'primary_key' => 'id_d2id',
        'columns' => ['id_d2id','id_utente','descrizione','id_gruppo_transazione','id_metodo_pagamento','id_etichetta','conto']
    ],
    'bilancio_entrate' => [
        'primary_key' => 'id_entrata',
        'columns' => ['id_entrata','id_utente','mezzo','id_tipologia','id_gruppo_transazione','id_metodo_pagamento','descrizione_operazione','descrizione_extra','importo','note','data_operazione','data_inserimento','data_aggiornamento']
    ],
    'bilancio_gruppi_categorie' => [
        'primary_key' => 'id_categoria',
        'columns' => ['id_categoria','descrizione_categoria']
    ],
    'bilancio_gruppi_transazione' => [
        'primary_key' => 'id_gruppo_transazione',
        'columns' => ['id_gruppo_transazione','id_categoria','id_utente','descrizione','tipo_gruppo','attivo','ricorsivo','ogni_quanto','cosa_quanto']
    ],
    'bilancio_uscite' => [
        'primary_key' => 'id_uscita',
        'columns' => ['id_uscita','id_utente','id_caricamento','mezzo','id_tipologia','id_gruppo_transazione','id_metodo_pagamento','descrizione_operazione','descrizione_extra','importo','note','data_operazione','data_inserimento','data_aggiornamento']
    ],
    'codici_2fa' => [
        'primary_key' => 'id',
        'columns' => ['id','id_utente','codice','scadenza']
    ],
    'dispositivi_riconosciuti' => [
        'primary_key' => 'id',
        'columns' => ['id','id_utente','token_dispositivo','user_agent','ip','data_attivazione','scadenza']
    ],
    'famiglie' => [
        'primary_key' => 'id_famiglia',
        'columns' => ['id_famiglia','nome_famiglia','in_gestione']
    ],
    'userlevels' => [
        'primary_key' => 'userlevelid',
        'columns' => ['userlevelid','userlevelname']
    ],
    'utenti' => [
        'primary_key' => 'id',
        'columns' => ['id','username','nome','cognome','soprannome','email','id_famiglia_attuale','id_famiglia_gestione','attivo']
    ],
    'utenti2famiglie' => [
        'primary_key' => 'id_u2f',
        'columns' => ['id_u2f','id_utente','id_famiglia','userlevelid']
    ],
    'utenti2ip' => [
        'primary_key' => 'id_u2i',
        'columns' => ['id_u2i','id_utente','ip_address']
    ]
];
?>
