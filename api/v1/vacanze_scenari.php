<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
api_method('POST');
if (!$allowVacations) api_error(403,'Vacanze non abilitate');

const GF_VOCI=[
 'trasporti'=>['viaggi_tratte','viaggi_tratte_alternative','id_tratta','descrizione',
    ['tipo_tratta'=>'enum:auto,aereo,traghetto,treno','descrizione'=>'text:5000','origine_testo'=>'text:255','destinazione_testo'=>'text:255','costo_volo_eur'=>'money','costo_traghetto_eur'=>'money','costo_noleggio_eur'=>'money','pedaggi_eur'=>'money','altri_costi_eur'=>'money','note'=>'text:5000']],
 'alloggi'=>['viaggi_alloggi','viaggi_alloggi_alternative','id_alloggio','nome_alloggio',
    ['nome_alloggio'=>'text:255','indirizzo'=>'text:255','data_checkin'=>'date','data_checkout'=>'date','costo_notte_eur'=>'money','costo_totale_eur'=>'money','note'=>'text:5000']],
 'pasti'=>['viaggi_pasti','viaggi_pasti_alternative','id_pasto','nome_locale',
    ['nome_locale'=>'text:255','tipo_pasto'=>'enum:colazione,pranzo,cena','tipologia'=>'enum:ristorante,cucinato','giorno_indice'=>'day','costo_medio_eur'=>'money','note'=>'text:5000']],
 'altri_costi'=>['viaggi_altri_costi','viaggi_altri_costi_alternative','id_costo','descrizione',
    ['descrizione'=>'text:255','importo_eur'=>'money','data'=>'date','note'=>'text:5000']]
];
function gf_fail(string $s): never {throw new InvalidArgumentException($s);}
function gf_id($v): int {if(!is_int($v)||$v<1)gf_fail('ID non valido');return $v;}
function gf_validate($v,string $type) {
 if($v===null)return null;
 if($type==='money'){if((!is_int($v)&&!is_float($v))||!is_finite((float)$v)||$v<0||$v>10000000)gf_fail('Importo non valido');return round((float)$v,2);}
 if($type==='day'){if(!is_int($v)||$v<0||$v>30)gf_fail('Giorno non valido');return $v;}
 if($type==='date'){if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v))gf_fail('Data non valida');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)gf_fail('Data non valida');return $v;}
 if(!is_string($v))gf_fail('Testo non valido');$v=trim($v);
 if(str_starts_with($type,'enum:')){if(!in_array($v,explode(',',substr($type,5)),true))gf_fail('Valore non valido');}
 elseif(mb_strlen($v,'UTF-8')>(int)substr($type,5))gf_fail('Testo troppo lungo');
 return $v;
}
function gf_rows(mysqli $db,int $trip,int $sid,array $cfg): array{
 [$table,$link,$pk]=$cfg;
 $sql='SELECT e.* FROM '.$table.' e JOIN '.$link.' x ON x.'.$pk.'=e.'.$pk.' WHERE e.id_viaggio=? AND x.id_viaggio_alternativa=? ORDER BY e.'.$pk;
 $q=$db->prepare($sql);$q->bind_param('ii',$trip,$sid);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();return $rows;
}
function gf_snapshot(mysqli $db,int $trip,int $sid):array{
 $a=[];foreach(GF_VOCI as $category=>$cfg)$a[$category]=gf_rows($db,$trip,$sid,$cfg);return $a;
}
function gf_hash(array $rows):string{return hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
function gf_changes(array $scenario,array $snapshot):array{
 $entries=$scenario['voci']??null;
 if(!is_array($entries)||!array_is_list($entries)||!count($entries)||count($entries)>60)gf_fail('Elenco voci non valido');
 $ops=[];$seen=[];
 foreach($entries as $entry){
  if(!is_array($entry)||array_is_list($entry))gf_fail('Voce non valida');
  $category=$entry['categoria']??null;
  if(!is_string($category)||!isset(GF_VOCI[$category]))gf_fail('Categoria non valida');
  [$table,$link,$pk,$label,$rules]=GF_VOCI[$category];
  $fields=$entry['campi']??null;
  if(!is_array($fields)||array_is_list($fields)||!count($fields))gf_fail('Campi non validi');
  $clean=[];
  foreach($fields as $k=>$v){if(!isset($rules[$k]))gf_fail('Campo non modificabile: '.$k);$clean[$k]=gf_validate($v,$rules[$k]);}
  if($category==='alloggi'&&isset($clean['costo_totale_eur'],$clean['costo_notte_eur']))gf_fail('Non indicare entrambi i prezzi alloggio');
  $id=isset($entry['id'])?gf_id($entry['id']):null;$found=[];
  foreach($snapshot[$category] as $row){
   if($id!==null&&(int)$row[$pk]===$id)$found[]=$row;
   if($id===null&&isset($clean[$label])&&mb_strtolower(trim((string)$row[$label]))===mb_strtolower(trim($clean[$label])))$found[]=$row;
  }
  if(count($found)>1)gf_fail('Nome ambiguo: specificare ID');
  if($id!==null&&!$found)gf_fail('Voce non presente nello scenario');
  $old=$found[0]??null;
  if(!$old&&empty($clean[$label]))gf_fail('Nome obbligatorio per nuova voce');
  if(!$old&&$category==='trasporti'&&empty($clean['tipo_tratta']))gf_fail('Tipo tratta obbligatorio');
  $identity=$category.':'.($old?(string)$old[$pk]:mb_strtolower($clean[$label]));
  if(isset($seen[$identity]))gf_fail('Voce ripetuta');$seen[$identity]=true;
  if($old&&(int)($old['pagato']??0)!==0)gf_fail('Voce gia pagata: nessuna modifica consentita');
  $before=[];foreach($clean as $k=>$v)$before[$k]=$old[$k]??null;
  $ops[]=['categoria'=>$category,'azione'=>$old?'aggiorna':'inserisci','id'=>$old?(int)$old[$pk]:null,'prima'=>$before,'dopo'=>$clean];
 }
 return $ops;
}
function gf_apply(mysqli $db,int $trip,int $sid,array $ops):void{
 foreach($ops as $op){
  [$table,$link,$pk]=GF_VOCI[$op['categoria']];$fields=$op['dopo'];
  if($op['azione']==='aggiorna'){
   $parts=[];foreach(array_keys($fields) as $key)$parts[]=$key.'=?';
   $sql='UPDATE '.$table.' SET '.implode(',',$parts).' WHERE '.$pk.'=? AND id_viaggio=? AND COALESCE(pagato,0)=0';
   $values=array_values($fields);$values[]=$op['id'];$values[]=$trip;
   $q=$db->prepare($sql);$q->bind_param(str_repeat('s',count($fields)).'ii',...$values);$q->execute();$q->close();
  }else{
   $columns=array_merge(['id_viaggio','id_viaggio_alternativa'],array_keys($fields));
   $sql='INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
   $values=array_merge([$trip,$sid],array_values($fields));
   $q=$db->prepare($sql);$q->bind_param('ii'.str_repeat('s',count($fields)),...$values);$q->execute();$id=$db->insert_id;$q->close();
   $q=$db->prepare('INSERT INTO '.$link.' ('.$pk.',id_viaggio_alternativa) VALUES (?,?)');
   $q->bind_param('ii',$id,$sid);$q->execute();$q->close();
  }
 }
}
try{
 $body=api_body();$trip=gf_id($body['id_viaggio']??null);$scenarios=$body['scenari']??null;
 if(!is_array($scenarios)||!array_is_list($scenarios)||!count($scenarios)||count($scenarios)>20)gf_fail('Scenari non validi');
 $dry=($body['dry_run']??false)===true;
 if(!$dry)$requestId=api_request_id();
 $conn->begin_transaction();
 $q=$conn->prepare('SELECT v.id_viaggio FROM viaggi v JOIN viaggi_api_famiglie f ON f.id_viaggio=v.id_viaggio WHERE v.id_viaggio=? AND f.id_famiglia=? FOR UPDATE');
 $q->bind_param('ii',$trip,$familyId);$q->execute();$valid=$q->get_result()->fetch_row();$q->close();
 if(!$valid)gf_fail('Vacanza inesistente o non autorizzata');
 $report=[];$seen=[];
 foreach($scenarios as $scenario){
  if(!is_array($scenario)||array_is_list($scenario))gf_fail('Scenario non valido');
  $sid=gf_id($scenario['id_scenario']??null);
  if(isset($seen[$sid]))gf_fail('Scenario duplicato');$seen[$sid]=true;
  $q=$conn->prepare('SELECT 1 FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
  $q->bind_param('ii',$trip,$sid);$q->execute();$valid=$q->get_result()->fetch_row();$q->close();
  if(!$valid)gf_fail('Scenario non appartenente alla vacanza');
  $snapshot=gf_snapshot($conn,$trip,$sid);$hash=gf_hash($snapshot);
  if(!$dry&&(!is_string($scenario['expected_hash']??null)||!hash_equals($hash,$scenario['expected_hash'])))gf_fail('Scenario modificato dopo anteprima: ripetere anteprima');
  $ops=gf_changes($scenario,$snapshot);
  foreach($ops as $op)if($op['id']!==null){
   [$table,$link,$pk]=GF_VOCI[$op['categoria']];
   $q=$conn->prepare('SELECT COUNT(*) n FROM '.$link.' WHERE '.$pk.'=?');
   $q->bind_param('i',$op['id']);$q->execute();$count=(int)$q->get_result()->fetch_assoc()['n'];$q->close();
   if($count!==1)gf_fail('Voce condivisa fra scenari: aggiornamento non consentito');
  }
  $report[]=['id_scenario'=>$sid,'expected_hash'=>$hash,'modifiche'=>$ops];
 }
 if($dry){$conn->rollback();api_reply(200,['success'=>true,'dry_run'=>true,'id_viaggio'=>$trip,'scenari'=>$report]);}
 api_claim_request($conn,$familyId,'vacanze_scenari',$requestId);
 foreach($report as $s)gf_apply($conn,$trip,$s['id_scenario'],$s['modifiche']);
 $conn->commit();api_reply(200,['success'=>true,'updated_scenarios'=>count($report),'scenari'=>$report]);
}catch(InvalidArgumentException $e){$conn->rollback();api_error(422,$e->getMessage());}
catch(Throwable $e){$conn->rollback();api_json_error($e);}
