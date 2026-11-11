<?php
declare(strict_types=1);
require_once __DIR__.'/../api/v1/menu_cene_edit_helper.php';
function check_menu(bool $condition,string $why):void{
 if(!$condition){fwrite(STDERR,"FAIL: ".$why."\n");exit(1);}
}
$base=[
 'Lunedì'=>['cena'=>'Pasta al pomodoro','contorno'=>'Insalata'],
 'Martedì'=>['cena'=>'Merluzzo','contorno'=>'Patate'],
 'Mercoledì'=>['cena'=>'Frittata','contorno'=>'Carote'],
 'Giovedì'=>['cena'=>'Zuppa di lenticchie','contorno'=>'Pane'],
 'Venerdì'=>['cena'=>'Pizza','contorno'=>'Insalata']
];
$replace=gf_menu_change($base,[['azione'=>'sostituisci','giorno'=>'Lunedì','piatto'=>'Pasta al pesto']],false);
check_menu($replace['Lunedì']['cena']==='Pasta al pesto','Sostituzione piatto');
check_menu($replace['Lunedì']['contorno']==='Insalata','Preservazione contorno');
$add=gf_menu_change($base,[['azione'=>'aggiungi','giorno'=>'Martedì','parte'=>'contorno','piatto'=>'Finocchi']],false);
check_menu($add['Martedì']['contorno']==='Patate + Finocchi','Aggiunta');
$again=gf_menu_change($add,[['azione'=>'aggiungi','giorno'=>'Martedì','parte'=>'contorno','piatto'=>'finocchi']],false);
check_menu($again===$add,'Aggiunta idempotente di stessa voce');
$remove=gf_menu_change($add,[['azione'=>'elimina','giorno'=>'Martedì','parte'=>'contorno','piatto'=>'Patate']],false);
check_menu($remove['Martedì']['contorno']==='Finocchi','Elimina solo elemento specifico');
$empty=gf_menu_change($base,[['azione'=>'elimina','giorno'=>'Giovedì']],false);
check_menu($empty['Giovedì']['cena']===''&&$empty['Giovedì']['contorno']==='Pane','Elimina cena singola settimana');
$swap=gf_menu_change($base,[['azione'=>'scambia','giorno'=>'Lunedì','altro_giorno'=>'Venerdì']],false);
check_menu($swap['Lunedì']===$base['Venerdì']&&$swap['Venerdì']===$base['Lunedì'],'Scambio intere cene');
$swapPart=gf_menu_change($base,[['azione'=>'scambia','giorno'=>'Lunedì','altro_giorno'=>'Martedì','parte'=>'contorno']],true);
check_menu($swapPart['Lunedì']['contorno']==='Patate'&&$swapPart['Lunedì']['cena']==='Pasta al pomodoro','Scambio contorno nel master');
$thrown=false;
try{gf_menu_change($base,[['azione'=>'elimina','giorno'=>'Giovedì']],true);}catch(InvalidArgumentException $e){$thrown=true;}
check_menu($thrown,'Master non consente cena vuota');
$thrown=false;
try{gf_menu_change($base,[['azione'=>'sostituisci','giorno'=>'Sabato','piatto'=>'Pizza']],false);}catch(InvalidArgumentException $e){$thrown=true;}
check_menu($thrown,'Giorno non previsto bloccato');
check_menu(count(gf_menu_diff($base,$swap))===2,'Diff dei due giorni');
check_menu(gf_menu_parts(gf_menu_format($base['Martedì']))===$base['Martedì'],'Formato compatible con menu esistente');
check_menu(gf_menu_hash($base)===gf_menu_hash($base),'Hash stabile');
echo "OK menu cene edit helper: 12 test\n";
