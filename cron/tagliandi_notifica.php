<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../lib/mezzi_tagliandi.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$daysThreshold = (int) getenv('TAGLIANDO_GIORNI_SOGLIA');
if ($daysThreshold <= 0) { $daysThreshold = 30; }
$kmThreshold = (int) getenv('TAGLIANDO_KM_SOGLIA');
if ($kmThreshold <= 0) { $kmThreshold = 1000; }

$scadenze = get_tagliandi_scadenze($conn);

foreach ($scadenze as $s) {
    $inScadenza = false;
    if ($s['days_remaining'] !== null && $s['days_remaining'] <= $daysThreshold) {
        $inScadenza = true;
    }
    if ($s['km_remaining'] !== null && $s['km_remaining'] <= $kmThreshold) {
        $inScadenza = true;
    }
    if (!$inScadenza) { continue; }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtps.aruba.it';
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mail_user'];
        $mail->Password   = $config['mail_pwd'];
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];
        $mail->setFrom('assistenza@gestionefamiglia.it', 'Gestione Famiglia');
        $mail->addAddress($s['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Tagliando in scadenza';
        $template = file_get_contents(__DIR__ . '/../assets/html/codice_verifica.html');
        $message = sprintf("Il tagliando '%s' per il mezzo '%s' è in scadenza il %s o a %d km.",
            $s['nome_tagliando'],
            $s['nome_mezzo'],
            $s['next_date'],
            $s['next_km']
        );
        $mail->Body = str_replace(['[content]', '[message]'], ['Tagliando in scadenza', $message], $template);
        $mail->send();
    } catch (Exception $e) {
        // Silenzia eventuali errori di invio
    }
}
?>
