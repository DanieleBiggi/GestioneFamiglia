<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/../../includes/menu_cene_master.php';
api_method('GET');
try{
    $config=food_config_read((int)$familyId,'master')??[
        'version'=>1,'anno_scolastico'=>'2026-2027',
        'ciclo'=>['settimane'=>4,'data_riferimento'=>'2026-09-14','settimana_riferimento'=>2],
        'stagioni'=>menu_cene_master()
    ];
    api_reply(200,['success'=>true,'master'=>$config,
        'notice'=>'Il master definisce le previsioni cicliche. Le settimane gia salvate nel database non sono aggiornate automaticamente.']);
}catch(Throwable $e){api_json_error($e);}
