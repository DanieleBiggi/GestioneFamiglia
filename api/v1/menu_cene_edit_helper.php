<?php
declare(strict_types=1);

/** Funzioni pure per modificare i piatti; nessuna scrittura nel database o su file. */
const GF_MENU_DAYS = ['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì'];
// Normalizza etichette accentate, senza accento e mojibake storici.
// Non altera le righe del database; serve anche per la lettura web.
function gf_menu_normalize_day(string $value): ?string {
    $value = trim($value);
    $ascii = ['lunedi'=>'Lunedì','martedi'=>'Martedì','mercoledi'=>'Mercoledì','giovedi'=>'Giovedì','venerdi'=>'Venerdì'];
    $key = mb_strtolower($value, 'UTF-8');
    $key = str_replace(['ì','ã¬','ãƒâ¬'], 'i', $key);
    return $ascii[$key] ?? null;
}

function gf_menu_parts(string $dish): array {
    $lines = preg_split('/\r?\n/u', $dish, 2);
    return ['cena'=>trim($lines[0]??''),'contorno'=>trim($lines[1]??'')];
}
function gf_menu_format(array $parts): string {
    $dinner=trim($parts['cena']??'');$side=trim($parts['contorno']??'');
    return $side!=='' ? $dinner."\n".$side : $dinner;
}
function gf_menu_change(array $days,array $actions,bool $master): array {
    if (!array_is_list($actions) || count($actions)<1 || count($actions)>30)
        throw new InvalidArgumentException('Sono richieste da 1 a 30 operazioni');
    $updated=$days;
    foreach ($actions as $action) {
        if (!is_array($action)||array_is_list($action)) throw new InvalidArgumentException('Operazione non valida');
        $type=$action['azione']??null;$day=$action['giorno']??null;
        if (!in_array($day,GF_MENU_DAYS,true)||!in_array($type,['sostituisci','aggiungi','elimina','scambia'],true))
            throw new InvalidArgumentException('Giorno o azione non validi');
        $part=$action['parte']??null;
        if ($part!==null&&!in_array($part,['cena','contorno'],true))
            throw new InvalidArgumentException('parte deve essere cena o contorno');
        if ($type==='scambia') {
            $second=$action['altro_giorno']??null;
            if (!in_array($second,GF_MENU_DAYS,true)||$second===$day)
                throw new InvalidArgumentException('Indicare un secondo giorno diverso');
            if ($part===null) {
                [$updated[$day],$updated[$second]]=[$updated[$second],$updated[$day]];
            } else {
                [$updated[$day][$part],$updated[$second][$part]]=[$updated[$second][$part],$updated[$day][$part]];
            }
            continue;
        }
        // Per operazioni sui piatti la parte predefinita e la cena.
        $part=$part??'cena';
        $current=trim((string)($updated[$day][$part]??''));
        $dish=$action['piatto']??null;
        if ($dish!==null) {
            if (!is_string($dish)) throw new InvalidArgumentException('Piatto non valido');
            $dish=trim($dish);
            if (str_contains($dish,"\n")||str_contains($dish,"\r")||mb_strlen($dish,'UTF-8')>500)
                throw new InvalidArgumentException('Piatto troppo lungo o multilinea');
        }
        if (in_array($type,['aggiungi','sostituisci'],true)&&($dish===null||$dish===''))
            throw new InvalidArgumentException('Il piatto e obbligatorio');
        if ($type==='sostituisci') {
            $updated[$day][$part]=$dish;
        } elseif ($type==='aggiungi') {
            $existing=$current===''?[]:array_map('trim',explode(' + ',$current));
            if (!in_array(mb_strtolower($dish,'UTF-8'),array_map(static fn($x)=>mb_strtolower($x,'UTF-8'),$existing),true))
                $existing[]=$dish;
            $updated[$day][$part]=implode(' + ',$existing);
        } else {
            if ($dish===null) {
                $updated[$day][$part]='';
            } else {
                $existing=$current===''?[]:array_map('trim',explode(' + ',$current));
                $matched=false;$filtered=[];
                foreach($existing as $value) {
                    if (mb_strtolower($value,'UTF-8')===mb_strtolower($dish,'UTF-8')){$matched=true;continue;}
                    $filtered[]=$value;
                }
                if (!$matched) throw new InvalidArgumentException('Piatto da eliminare non trovato nel giorno');
                $updated[$day][$part]=implode(' + ',$filtered);
            }
        }
        if (mb_strlen((string)$updated[$day][$part],'UTF-8')>500)
            throw new InvalidArgumentException('Descrizione troppo lunga');
    }
    foreach(GF_MENU_DAYS as $day) {
        if (!isset($updated[$day])||!is_array($updated[$day])) throw new InvalidArgumentException('Giorno mancante');
        foreach(['cena','contorno'] as $part) {
            if (!is_string($updated[$day][$part]??null)||mb_strlen($updated[$day][$part],'UTF-8')>500)
                throw new InvalidArgumentException('Contenuto del giorno non valido');
        }
        if ($master&&trim($updated[$day]['cena'])==='')
            throw new InvalidArgumentException('Il master richiede una cena per '.$day.'; sostituire il piatto invece di svuotarlo');
    }
    return $updated;
}
function gf_menu_diff(array $before,array $after):array {
    $changes=[];
    foreach(GF_MENU_DAYS as $day) {
        if ($before[$day]!==$after[$day])
            $changes[]=['giorno'=>$day,'prima'=>gf_menu_format($before[$day]),'dopo'=>gf_menu_format($after[$day]),
                'parti_precedenti'=>$before[$day],'parti_nuove'=>$after[$day]];
    }
    return $changes;
}
function gf_menu_hash(array $data):string {
    return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}
