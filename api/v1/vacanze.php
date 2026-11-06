<?php
require_once __DIR__ . '/bootstrap.php';
api_method('GET', 'POST');
// Lo schema attuale di viaggi NON ha id_famiglia: l'accesso va abilitato
// esplicitamente solo in installazioni dove tutto il catalogo viaggi è condivisibile.
if (!$allowVacations) api_error(403, 'Accesso alle vacanze non abilitato');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $conn->prepare('SELECT id_viaggio, titolo, data_inizio, data_fine, persone, stato, breve_descrizione, note FROM viaggi WHERE id_viaggio=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $trip = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$trip) api_error(404, 'Vacanza non trovata');
            $stmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $trip['scenari'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $categories = [
                'trasporti'=>['table'=>'viaggi_tratte','link'=>'viaggi_tratte_alternative','key'=>'id_tratta'],
                'alloggi'=>['table'=>'viaggi_alloggi','link'=>'viaggi_alloggi_alternative','key'=>'id_alloggio'],
                'altri_costi'=>['table'=>'viaggi_altri_costi','link'=>'viaggi_altri_costi_alternative','key'=>'id_costo']
            ];
            foreach ($trip['scenari'] as &$scenario) {
                $sid = (int)$scenario['id_viaggio_alternativa'];
                foreach ($categories as $name => $cat) {
                    $table = $cat['table']; $link = $cat['link']; $key = $cat['key'];
                    $q = $conn->prepare("SELECT e.* FROM `$table` e INNER JOIN `$link` x ON e.`$key`=x.`$key` WHERE e.id_viaggio=? AND x.id_viaggio_alternativa=? ORDER BY e.`$key`");
                    $q->bind_param('ii', $id, $sid);
                    $q->execute();
                    $scenario[$name] = $q->get_result()->fetch_all(MYSQLI_ASSOC);
                    $q->close();
                }
            }
            unset($scenario);
            api_reply(200, ['success'=>true, 'vacanza'=>$trip]);
        }
        $result = $conn->query('SELECT id_viaggio, titolo, data_inizio, data_fine, persone, stato FROM viaggi ORDER BY id_viaggio DESC LIMIT 100');
        api_reply(200, ['success'=>true, 'vacanze'=>$result->fetch_all(MYSQLI_ASSOC)]);
    }

    $body = api_body();
    $title = api_str($body['titolo'] ?? null, 150, true);
    $start = api_date($body['data_inizio'] ?? null);
    $end = api_date($body['data_fine'] ?? null);
    if ($start && $end && $end < $start) api_error(422, 'La data fine precede l’inizio');
    $people = $body['persone'] ?? null;
    if ($people !== null && (!is_int($people) || $people < 1 || $people > 100)) api_error(422, 'Numero persone non valido');
    $desc = api_str($body['breve_descrizione'] ?? null, 250);
    $notes = api_str($body['note'] ?? null, 30000);
    $scenarios = api_list($body['scenari'] ?? [], 20);
    $validated = [];
    foreach ($scenarios as $scenario) {
        $s = api_assoc($scenario);
        $name = api_str($s['nome'] ?? null, 100, true);
        $routes = [];
        foreach (api_list($s['trasporti'] ?? [], 30) as $route) {
            $route = api_assoc($route);
            $type = api_str($route['tipo'] ?? null, 20, true);
            if (!in_array($type, ['auto','aereo','traghetto','treno'], true)) api_error(422,'Tipo trasporto non valido');
            $routes[] = [
                $type, api_str($route['descrizione'] ?? null, 5000),
                api_str($route['origine'] ?? null, 255), api_str($route['destinazione'] ?? null, 255),
                api_number($route['costo_volo_eur'] ?? null), api_number($route['costo_traghetto_eur'] ?? null),
                api_number($route['altri_costi_eur'] ?? null), api_str($route['note'] ?? null, 5000)
            ];
        }
        $lodgings = [];
        foreach (api_list($s['alloggi'] ?? [], 30) as $lodging) {
            $a = api_assoc($lodging);
            $lodgings[] = [
                api_str($a['nome'] ?? null, 255, true), api_str($a['indirizzo'] ?? null, 255),
                api_date($a['checkin'] ?? null), api_date($a['checkout'] ?? null),
                api_number($a['costo_notte_eur'] ?? null), api_str($a['note'] ?? null, 5000)
            ];
        }
        $costs = [];
        foreach (api_list($s['altri_costi'] ?? [], 50) as $cost) {
            $c = api_assoc($cost);
            $costs[] = [api_str($c['descrizione'] ?? null, 255, true), api_number($c['importo_eur'] ?? null), api_str($c['note'] ?? null, 5000)];
        }
        $validated[] = ['nome'=>$name,'trasporti'=>$routes,'alloggi'=>$lodgings,'altri_costi'=>$costs];
    }
    if (($body['dry_run'] ?? false) === true) api_reply(200, ['success'=>true, 'dry_run'=>true,'vacanza'=>['titolo'=>$title,'data_inizio'=>$start,'data_fine'=>$end,'persone'=>$people,'scenari'=>$validated]]);
    $state = 'idea';
    $nights = $start && $end ? (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days : null;
    $conn->begin_transaction();
    $stmt = $conn->prepare('INSERT INTO viaggi (titolo, data_inizio, data_fine, notti, persone, stato, visibilita, breve_descrizione, note) VALUES (?,?,?,?,?,?,"private",?,?)');
    $stmt->bind_param('sssiisss', $title,$start,$end,$nights,$people,$state,$desc,$notes);
    $stmt->execute();
    $tripId = $conn->insert_id;
    $stmt->close();
    $scenarioIds = [];
    foreach ($validated as $s) {
        $name = $s['nome'];
        $stmt = $conn->prepare('INSERT INTO viaggi_alternative (id_viaggio, breve_descrizione) VALUES (?,?)');
        $stmt->bind_param('is',$tripId,$name);
        $stmt->execute();
        $sid = $conn->insert_id;
        $stmt->close();
        $scenarioIds[] = $sid;
        foreach ($s['trasporti'] as $r) {
            [$type,$description,$origin,$destination,$flight,$ferry,$other,$routeNotes]=$r;
            $stmt=$conn->prepare('INSERT INTO viaggi_tratte (id_viaggio,id_viaggio_alternativa,tipo_tratta,descrizione,origine_testo,destinazione_testo,costo_volo_eur,costo_traghetto_eur,altri_costi_eur,note) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iissssddds',$tripId,$sid,$type,$description,$origin,$destination,$flight,$ferry,$other,$routeNotes);
            $stmt->execute();
            $expenseId = $conn->insert_id;
            $stmt->close();
            $link = $conn->prepare('INSERT INTO viaggi_tratte_alternative (id_tratta, id_viaggio_alternativa) VALUES (?,?)');
            $link->bind_param('ii', $expenseId, $sid);
            $link->execute();
            $link->close();
        }
        foreach ($s['alloggi'] as $a) {
            [$name,$address,$checkin,$checkout,$nightCost,$lodgingNotes]=$a;
            $stmt=$conn->prepare('INSERT INTO viaggi_alloggi (id_viaggio,id_viaggio_alternativa,nome_alloggio,indirizzo,data_checkin,data_checkout,costo_notte_eur,note) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iissssds',$tripId,$sid,$name,$address,$checkin,$checkout,$nightCost,$lodgingNotes);
            $stmt->execute();
            $expenseId = $conn->insert_id;
            $stmt->close();
            $link = $conn->prepare('INSERT INTO viaggi_alloggi_alternative (id_alloggio, id_viaggio_alternativa) VALUES (?,?)');
            $link->bind_param('ii', $expenseId, $sid);
            $link->execute();
            $link->close();
        }
        foreach ($s['altri_costi'] as $c) {
            [$label,$amount,$costNotes]=$c;
            $stmt=$conn->prepare('INSERT INTO viaggi_altri_costi (id_viaggio,id_viaggio_alternativa,descrizione,importo_eur,note) VALUES (?,?,?,?,?)');
            $stmt->bind_param('iisds',$tripId,$sid,$label,$amount,$costNotes);
            $stmt->execute();
            $expenseId = $conn->insert_id;
            $stmt->close();
            $link = $conn->prepare('INSERT INTO viaggi_altri_costi_alternative (id_costo, id_viaggio_alternativa) VALUES (?,?)');
            $link->bind_param('ii', $expenseId, $sid);
            $link->execute();
            $link->close();
        }
    }
    $conn->commit();
    api_reply(201, ['success'=>true,'id_viaggio'=>$tripId,'id_scenari'=>$scenarioIds]);
} catch (Throwable $e) {
    $conn->rollback();
    api_json_error($e);
}
