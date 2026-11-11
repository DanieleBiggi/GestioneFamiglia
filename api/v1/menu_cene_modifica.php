<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/../../includes/menu_cene_master.php';
require_once __DIR__.'/menu_cene_edit_helper.php';
api_method('POST');

function gf_menu_week(string $date): DateTimeImmutable {
    $week=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Europe/Rome'));
    if (!$week||$week->format('Y-m-d')!==$date||$week->format('N')!=='1')
        throw new InvalidArgumentException('week_start deve essere un lunedi YYYY-MM-DD');
    return $week;
}
function gf_menu_master_config(int $family): array {
    return food_config_read($family,'master')??[
        'version'=>1,'anno_scolastico'=>'2026-2027',
        'ciclo'=>['settimane'=>4,'data_riferimento'=>'2026-09-14','settimana_riferimento'=>2],
        'stagioni'=>menu_cene_master()
    ];
}
function gf_menu_week_plan(array $config,DateTimeImmutable $start):array {
    $season=menu_cene_season($start);
    $anchor=gf_menu_week((string)$config['ciclo']['data_riferimento']);
    $weeks=intdiv((int)$anchor->diff($start)->format('%r%a'),7);
    $reference=(int)$config['ciclo']['settimana_riferimento'];
    $cycle=(($reference-1+$weeks)%4+4)%4+1;
    return ['stagione'=>$season,'settimana_ciclo'=>$cycle];
}
function gf_menu_week_state(mysqli $db,int $family,DateTimeImmutable $start,array $config):array {
    $week=$start->format('Y-m-d');$plan=gf_menu_week_plan($config,$start);
    $master=$config['stagioni'][$plan['stagione']][$plan['settimana_ciclo']]??[];
    $q=$db->prepare('SELECT giorno,piatto FROM menu_cene_settimanale WHERE id_famiglia=? AND week_start=? ORDER BY id FOR UPDATE');
    $q->bind_param('is',$family,$week);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
    $saved=[];foreach($rows as $row)if(in_array($row['giorno'],GF_MENU_DAYS,true))$saved[$row['giorno']]=(string)$row['piatto'];
    $days=[];$origin=[];
    foreach(GF_MENU_DAYS as $day) {
        $has=array_key_exists($day,$saved);
        $days[$day]=$has?gf_menu_parts($saved[$day]):($master[$day]??['cena'=>'','contorno'=>'']);
        $origin[$day]=$has?'salvato':'previsto_master';
    }
    return ['giorni'=>$days,'origine'=>$origin,'piano'=>$plan];
}
try {
    $body=api_body();
    $scope=$body['ambito']??null;
    if (!in_array($scope,['settimana','master'],true)) api_error(422,'Specificare ambito settimana oppure master');
    $actions=$body['operazioni']??null;
    if (!is_array($actions)) api_error(422,'operazioni deve essere un elenco');
    $dry=($body['dry_run']??false)===true;
    if (!$dry) {
        $requestId=api_request_id();
        $expected=$body['expected_hash']??null;
        if (!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))
            api_error(422,'Prima del salvataggio occorre expected_hash della anteprima');
    }
    $start=null;$season=null;$cycle=null;
    if ($scope==='settimana') {
        if (!is_string($body['week_start']??null)) api_error(422,'week_start mancante');
        $start=gf_menu_week($body['week_start']);
        if (isset($body['stagione'])||isset($body['settimana_ciclo'])) api_error(422,'Parametri master non ammessi per la singola settimana');
    } else {
        $season=$body['stagione']??null;$cycle=$body['settimana_ciclo']??null;
        if (!in_array($season,['Estivo','Invernale'],true)||!is_int($cycle)||$cycle<1||$cycle>4)
            api_error(422,'Per il master servono stagione e settimana_ciclo (1-4)');
        if (isset($body['week_start'])) api_error(422,'week_start non ammesso per il master');
    }
    // Il lock copre la lettura, il confronto e il salvataggio del target.
    // GET_LOCK esiste in MySQL 5.6 e non richiede migrazioni.
    $lockKey='gf_menu_'.(int)$familyId.'_'.($scope==='master'?'master':$start->format('Ymd'));
    $lock=$conn->prepare('SELECT GET_LOCK(?,5) AS acquired');
    $lock->bind_param('s',$lockKey);$lock->execute();
    $acquired=(int)($lock->get_result()->fetch_assoc()['acquired']??0);$lock->close();
    if ($acquired!==1) api_error(409,'Menu occupato: riprovare');
    try {
        $conn->begin_transaction();
        $config=gf_menu_master_config((int)$familyId);
        if ($scope==='settimana') {
            $state=gf_menu_week_state($conn,(int)$familyId,$start,$config);
            $before=$state['giorni'];
            $snapshot=['ambito'=>$scope,'famiglia'=>(int)$familyId,'week_start'=>$start->format('Y-m-d'),
                'giorni'=>$before,'origine'=>$state['origine'],'master_hash'=>gf_menu_hash($config)];
        } else {
            $before=$config['stagioni'][$season][$cycle]??null;
            if (!is_array($before)) throw new InvalidArgumentException('Master incompleto');
            $snapshot=['ambito'=>$scope,'famiglia'=>(int)$familyId,'master'=> $config];
        }
        $hash=gf_menu_hash($snapshot);
        if (!$dry && !hash_equals($hash,$expected)) {
            $conn->rollback();api_error(409,'Menù modificato dopo anteprima: ripetere verifica');
        }
        $after=gf_menu_change($before,$actions,$scope==='master');
        $diff=gf_menu_diff($before,$after);
        $report=['ambito'=>$scope,'week_start'=>$scope==='settimana'?$start->format('Y-m-d'):null,
            'stagione'=>$scope==='master'?$season:$state['piano']['stagione'],
            'settimana_ciclo'=>$scope==='master'?$cycle:$state['piano']['settimana_ciclo'],
            'modifiche'=>$diff,'modificato'=>count($diff)>0,'expected_hash'=>$hash,
            'avviso_spesa'=>'La lista della spesa e le associazioni ingredienti-giorno non vengono modificate. Dopo il cambio verificare le associazioni e gli acquisti.',
            'avviso_master'=>$scope==='master'?'Questa modifica influenza le future previsioni del ciclo; non sovrascrive settimane gia salvate.':null];
        if ($dry) {
            $conn->rollback();
            api_reply(200,['success'=>true,'dry_run'=>true,'anteprima'=>$report]);
        }
        // Chiave univoca gia presente in api_v1_richieste.
        api_claim_request($conn,(int)$familyId,'menu_cene',$requestId);
        if ($diff) {
            if ($scope==='settimana') {
                $week=$start->format('Y-m-d');
                $q=$conn->prepare('INSERT INTO menu_cene_settimanale (id_famiglia,week_start,giorno,piatto) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE piatto=VALUES(piatto)');
                // Materializzare tutti i 5 giorni: i giorni non toccati non devono
                // essere rigenerati inaspettatamente da una successiva visita alla pagina.
                foreach(GF_MENU_DAYS as $day) {
                    $dish=gf_menu_format($after[$day]);
                    $q->bind_param('isss',$familyId,$week,$day,$dish);$q->execute();
                }
                $q->close();
            } else {
                $config['stagioni'][$season][$cycle]=$after;
                // food_config_save gia gestisce validazione, backup e scrittura atomica JSON.
                food_config_save((int)$familyId,'master',$config);
            }
        }
        $conn->commit();
        api_reply(200,['success'=>true,'aggiornato'=>count($diff)>0,'risultato'=>$report]);
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    } finally {
        $release=$conn->prepare('SELECT RELEASE_LOCK(?)');
        $release->bind_param('s',$lockKey);$release->execute();$release->close();
    }
} catch (InvalidArgumentException $e) {
    api_error(422,$e->getMessage());
} catch (Throwable $e) {
    api_json_error($e);
}
