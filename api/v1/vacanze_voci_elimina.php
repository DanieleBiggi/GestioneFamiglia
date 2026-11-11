<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
api_method('POST');
if (!$allowVacations) api_error(403,'Vacanze non abilitate');
// Tabella e chiavi ammesse sono definite dal server, mai dall'utente.
const GF_DELETE_MAP=[
 'trasporti'=>['viaggi_tratte','viaggi_tratte_alternative','id_tratta','descrizione'],
 'alloggi'=>['viaggi_alloggi','viaggi_alloggi_alternative','id_alloggio','nome_alloggio'],
 'pasti'=>['viaggi_pasti','viaggi_pasti_alternative','id_pasto','nome_locale'],
 'altri_costi'=>['viaggi_altri_costi','viaggi_altri_costi_alternative','id_costo','descrizione']
];
function gf_delete_id($v):int{if(!is_int($v)||$v<1)throw new InvalidArgumentException('ID non valido');return $v;}
function gf_delete_amount(string $type,array $r):float{
 if($type==='trasporti')return round((float)($r['distanza_km']??0)*(float)($r['consumo_litri_100km']??0)/100*(float)($r['prezzo_carburante_eur_litro']??0)+(float)($r['pedaggi_eur']??0)+(float)($r['costo_traghetto_eur']??0)+(float)($r['costo_volo_eur']??0)+(float)($r['costo_noleggio_eur']??0)+(float)($r['altri_costi_eur']??0),2);
 if($type==='alloggi'){
  if($r['costo_totale_eur']!==null)return round((float)$r['costo_totale_eur'],2);
  $n=0;if(!empty($r['data_checkin'])&&!empty($r['data_checkout'])){$a=new DateTimeImmutable($r['data_checkin']);$b=new DateTimeImmutable($r['data_checkout']);$n=max(0,(int)$a->diff($b)->format('%r%a'));}
  return round($n*(float)($r['costo_notte_eur']??0),2);
 }
 return round((float)($type==='pasti'?($r['costo_medio_eur']??0):($r['importo_eur']??0)),2);
}
try{
 $body=api_body();$trip=gf_delete_id($body['id_viaggio']??null);$sid=gf_delete_id($body['id_scenario']??null);$id=gf_delete_id($body['id_voce']??null);
 $type=$body['categoria']??null;if(!is_string($type)||!isset(GF_DELETE_MAP[$type]))api_error(422,'Categoria non valida');
 $dry=($body['dry_run']??false)===true;
 if(!$dry){$requestId=api_request_id();$expected=$body['expected_hash']??null;
  if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))api_error(422,'Anteprima obbligatoria');}
 [$table,$link,$pk,$label]=GF_DELETE_MAP[$type];
 $conn->begin_transaction();
 // Serializzazione a livello vacanza e autorizzazione dal server.
 $q=$conn->prepare('SELECT v.id_viaggio FROM viaggi v JOIN viaggi_api_famiglie f ON f.id_viaggio=v.id_viaggio WHERE v.id_viaggio=? AND f.id_famiglia=? FOR UPDATE');
 $q->bind_param('ii',$trip,$familyId);$q->execute();$allowed=$q->get_result()->fetch_row();$q->close();
 if(!$allowed){$conn->rollback();api_error(404,'Vacanza inesistente o non autorizzata');}
 $q=$conn->prepare('SELECT 1 FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
 $q->bind_param('ii',$trip,$sid);$q->execute();$ok=$q->get_result()->fetch_row();$q->close();
 if(!$ok){$conn->rollback();api_error(404,'Scenario non appartenente alla vacanza');}
 $q=$conn->prepare('SELECT e.* FROM '.$table.' e JOIN '.$link.' l ON e.'.$pk.'=l.'.$pk.' WHERE e.id_viaggio=? AND l.id_viaggio_alternativa=? AND e.'.$pk.'=? FOR UPDATE');
 $q->bind_param('iii',$trip,$sid,$id);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
 if(!$row){$conn->rollback();api_error(404,'Voce non appartenente allo scenario');}
 $q=$conn->prepare('SELECT id_viaggio_alternativa FROM '.$link.' WHERE '.$pk.'=? ORDER BY id_viaggio_alternativa FOR UPDATE');
 $q->bind_param('i',$id);$q->execute();$links=array_map('intval',array_column($q->get_result()->fetch_all(MYSQLI_ASSOC),'id_viaggio_alternativa'));$q->close();
 if(!in_array($sid,$links,true))throw new RuntimeException('Collegamenti incoerenti');
 if((int)($row['pagato']??0)!==0){$conn->rollback();api_error(409,'Voce pagata: eliminazione non consentita');}
 $remaining=array_values(array_diff($links,[$sid]));$mode=$remaining?'scollega':'elimina';
 $snapshot=['vacanza'=>$trip,'scenario'=>$sid,'categoria'=>$type,'voce'=>$row,'scenari_collegati'=>$links];
 $hash=hash('sha256',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 if(!$dry&&!hash_equals($hash,$expected)){$conn->rollback();api_error(409,'Dati cambiati dopo anteprima: ripetere la verifica');}
 $report=['id_viaggio'=>$trip,'id_scenario'=>$sid,'categoria'=>$type,'id_voce'=>$id,
  'descrizione'=>$row[$label]??null,'importo_rimosso_dallo_scenario_eur'=>gf_delete_amount($type,$row),
  'operazione'=>$mode,'scenari_collegati_prima'=>$links,'scenari_collegati_dopo'=>$remaining,
  'conseguenze'=>$mode==='scollega'?'Rimuove solo il collegamento, lasciando la voce negli altri scenari.':'Elimina la voce dalla vacanza, poiche non e usata da altri scenari.',
  'expected_hash'=>$hash];
 if($dry){$conn->rollback();api_reply(200,['success'=>true,'dry_run'=>true,'anteprima'=>$report]);}
 api_claim_request($conn,$familyId,'vacanze_delete',$requestId);
 $q=$conn->prepare('DELETE FROM '.$link.' WHERE '.$pk.'=? AND id_viaggio_alternativa=?');
 $q->bind_param('ii',$id,$sid);$q->execute();$count=$q->affected_rows;$q->close();
 if($count!==1)throw new RuntimeException('Associazione non rimossa');
 if($mode==='elimina'){
  $q=$conn->prepare('DELETE FROM '.$table.' WHERE '.$pk.'=? AND id_viaggio=? AND pagato=0');
  $q->bind_param('ii',$id,$trip);$q->execute();$count=$q->affected_rows;$q->close();
  if($count!==1)throw new RuntimeException('Voce non eliminata');
 }else{
  $legacy=min($remaining);
  $q=$conn->prepare('UPDATE '.$table.' SET id_viaggio_alternativa=? WHERE '.$pk.'=? AND id_viaggio=? AND pagato=0');
  $q->bind_param('iii',$legacy,$id,$trip);$q->execute();$q->close();
 }
 $conn->commit();api_reply(200,['success'=>true,'deleted'=>true,'risultato'=>$report]);
}catch(InvalidArgumentException $e){$conn->rollback();api_error(422,$e->getMessage());}
catch(Throwable $e){$conn->rollback();api_json_error($e);}
