<?php
/**
 * Azioni POST chiamate solo da vacanze_workspace.php, dopo autenticazione e verifica CSRF.
 * La tabella viaggi_pianificazione è introdotta dalla migrazione 2026_11_06.
 */
function vacanzePlanningAction(mysqli $conn, int $tripId, string $action, bool $canEdit): string {
    if (!$canEdit) { http_response_code(403); return 'Non autorizzato a modificare questa vacanza.'; }
    $returnTo = 'vacanze_workspace.php?id=' . $tripId . '&tab=overview';
    try {
        $conn->begin_transaction();
        $lock = $conn->prepare('SELECT id_viaggio FROM viaggi WHERE id_viaggio=? FOR UPDATE');
        $lock->bind_param('i', $tripId); $lock->execute();
        $exists = (bool)$lock->get_result()->fetch_row(); $lock->close();
        if (!$exists) throw new RuntimeException('Vacanza non trovata.');

        $q = $conn->prepare('INSERT IGNORE INTO viaggi_pianificazione (id_viaggio) VALUES (?)');
        $q->bind_param('i', $tripId); $q->execute(); $q->close();
        $q = $conn->prepare('SELECT * FROM viaggi_pianificazione WHERE id_viaggio=? FOR UPDATE');
        $q->bind_param('i', $tripId); $q->execute();
        $planning = $q->get_result()->fetch_assoc(); $q->close();

        if ($action === 'confirm_scenario') {
            $altId = (int)($_POST['id_viaggio_alternativa'] ?? 0);
            $q = $conn->prepare('SELECT 1 FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
            $q->bind_param('ii', $tripId, $altId); $q->execute();
            $valid = (bool)$q->get_result()->fetch_row(); $q->close();
            if (!$valid) throw new RuntimeException('Scenario non appartenente alla vacanza.');
            $q = $conn->prepare('UPDATE viaggi_pianificazione SET id_alternativa_confermata=? WHERE id_viaggio=?');
            $q->bind_param('ii', $altId, $tripId); $q->execute(); $q->close();
        } elseif ($action === 'unlink_salvadanaio') {
            $q = $conn->prepare('UPDATE viaggi_pianificazione SET id_salvadanaio=NULL, obiettivo_salvadanaio=NULL WHERE id_viaggio=?');
            $q->bind_param('i', $tripId); $q->execute(); $q->close();
        } elseif ($action === 'create_salvadanaio' || $action === 'link_salvadanaio') {
            if (empty($planning['id_alternativa_confermata'])) throw new RuntimeException('Conferma prima uno scenario.');
            if (!empty($planning['id_salvadanaio'])) throw new RuntimeException('Scollega il salvadanaio attuale prima di associarne un altro.');
            if ($action === 'create_salvadanaio') {
                if (!has_permission($conn, 'table:salvadanai', 'insert')) throw new RuntimeException('Non autorizzato a creare salvadanai.');
                $name = trim((string)($_POST['nome_salvadanaio'] ?? ''));
                $amountRaw = (string)($_POST['obiettivo_salvadanaio'] ?? '');
                $deadline = trim((string)($_POST['data_scadenza'] ?? ''));
                if ($name === '' || mb_strlen($name,'UTF-8') > 250) throw new RuntimeException('Nome del salvadanaio non valido.');
                if (!is_numeric($amountRaw) || (float)$amountRaw < 0 || (float)$amountRaw > 99999999.99) throw new RuntimeException('Obiettivo economico non valido.');
                if ($deadline !== '' && (!DateTimeImmutable::createFromFormat('!Y-m-d',$deadline) || DateTimeImmutable::createFromFormat('!Y-m-d',$deadline)->format('Y-m-d') !== $deadline)) throw new RuntimeException('Data obiettivo non valida.');
                $zero = 0.0; $now = date('Y-m-d H:i:s'); $nullableDeadline = $deadline !== '' ? $deadline : null;
                $q = $conn->prepare('INSERT INTO salvadanai (nome_salvadanaio,importo_attuale,data_aggiornamento_manuale,data_scadenza) VALUES (?,?,?,?)');
                $q->bind_param('sdss', $name, $zero, $now, $nullableDeadline); $q->execute();
                $savingId = (int)$conn->insert_id; $q->close();
                $goal = round((float)$amountRaw, 2);
            } else {
                if (!has_permission($conn, 'page:salvadanai.php', 'view')) throw new RuntimeException('Non autorizzato ad accedere ai salvadanai.');
                $savingId = (int)($_POST['id_salvadanaio'] ?? 0);
                if ($savingId <= 0) throw new RuntimeException('Seleziona un salvadanaio.');
                $q = $conn->prepare('SELECT 1 FROM salvadanai WHERE id_salvadanaio=?');
                $q->bind_param('i', $savingId); $q->execute();
                $valid = (bool)$q->get_result()->fetch_row(); $q->close();
                if (!$valid) throw new RuntimeException('Salvadanaio non trovato.');
                // Obiettivo di questa vacanza: nessuna modifica al saldo o ai dati del salvadanaio.
                $amountRaw = trim((string)($_POST['obiettivo_salvadanaio'] ?? ''));
                if ($amountRaw !== '' && (!is_numeric($amountRaw) || (float)$amountRaw < 0 || (float)$amountRaw > 99999999.99)) throw new RuntimeException('Obiettivo economico non valido.');
                $goal = $amountRaw === '' ? null : round((float)$amountRaw,2);
            }
            $q = $conn->prepare('UPDATE viaggi_pianificazione SET id_salvadanaio=?,obiettivo_salvadanaio=? WHERE id_viaggio=?');
            $q->bind_param('idi', $savingId, $goal, $tripId); $q->execute(); $q->close();
        } elseif ($action === 'sync_salvadanaio_goal') {
            if (empty($planning['id_salvadanaio']) || empty($planning['id_alternativa_confermata'])) {
                throw new RuntimeException('Conferma uno scenario e collega un salvadanaio prima di sincronizzare.');
            }
            require_once __DIR__ . '/vacanze_budget_residui.php';
            $amounts=vacanzeBudgetResidui($conn);
            $savingId=(int)$planning['id_salvadanaio'];
            if (!isset($amounts[$savingId]) || (int)$amounts[$savingId]['id_viaggio']!==$tripId) {
                throw new RuntimeException('Impossibile calcolare il residuo dello scenario confermato.');
            }
            $goal=(float)$amounts[$savingId]['importo_residuo'];
            $familyId=(int)($_SESSION['id_famiglia_gestione'] ?? 0);
            if($familyId<=0) throw new RuntimeException('Famiglia non valida.');
            // Non indovinare quale voce modificare se il salvadanaio ne ha più di una.
            $q=$conn->prepare('SELECT id_budget,importo FROM budget WHERE id_salvadanaio=? AND id_famiglia=? FOR UPDATE');
            $q->bind_param('ii',$savingId,$familyId);$q->execute();
            $budgets=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
            if(count($budgets)!==1){
                throw new RuntimeException(count($budgets)===0
                    ? 'Questo salvadanaio non ha una voce Budget. Creala prima dal dettaglio del salvadanaio.'
                    : 'Questo salvadanaio contiene più voci Budget: scegli prima quale collegare alla vacanza.');
            }
            $budgetId=(int)$budgets[0]['id_budget'];
            $q=$conn->prepare('UPDATE budget SET importo=? WHERE id_budget=? AND id_salvadanaio=? AND id_famiglia=?');
            $q->bind_param('diii',$goal,$budgetId,$savingId,$familyId);$q->execute();$q->close();
            // Mantieni coerente anche la precedente informazione di pianificazione.
            $q=$conn->prepare('UPDATE viaggi_pianificazione SET obiettivo_salvadanaio=? WHERE id_viaggio=? AND id_salvadanaio=?');
            $q->bind_param('dii',$goal,$tripId,$savingId);$q->execute();$q->close();
        } elseif ($action === 'update_salvadanaio_goal') {
            if (empty($planning['id_salvadanaio'])) throw new RuntimeException('Nessun salvadanaio collegato.');
            $raw = (string)($_POST['obiettivo_salvadanaio'] ?? '');
            if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 99999999.99) throw new RuntimeException('Obiettivo non valido.');
            $goal = round((float)$raw,2);
            $q = $conn->prepare('UPDATE viaggi_pianificazione SET obiettivo_salvadanaio=? WHERE id_viaggio=?');
            $q->bind_param('di', $goal, $tripId); $q->execute(); $q->close();
        } else {
            throw new RuntimeException('Operazione sconosciuta.');
        }
        $conn->commit();
        header('Location: '.$returnTo);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        return $e instanceof mysqli_sql_exception && (int)$e->getCode()===1062
          ? 'Questo salvadanaio è già collegato a un’altra vacanza.'
          : $e->getMessage();
    }
}
