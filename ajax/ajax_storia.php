<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
require_once __DIR__ . '/../includes/utility.php';

switch($_POST['azione'] ?? '') {
    case 'ricerca':
        $utility = new Utility();
        switch($_POST['cosa'] ?? '') {
            case 'utenti':
                $conditions = [];
                $params = [];
                $types = '';
                if(!empty($_POST['valore_ricercato'])) {
                    $valore_ricercato = strtoupper($_POST['valore_ricercato']);
                    $valore_ricercato = Utility::escapeLike($valore_ricercato);
                    $conditions[] = '(COGNOME LIKE ? OR NOME LIKE ?)';
                    $params[] = "%$valore_ricercato%";
                    $params[] = "%$valore_ricercato%";
                    $types .= 'ss';
                }
                if(!empty($_POST['ar_filtri']['codazi'])) {
                    $codazi = (int)$_POST['ar_filtri']['codazi'];
                    $conditions[] = 'CODAZI = ?';
                    $params[] = $codazi;
                    $types .= 'i';
                }
                if(!$conditions) {
                    $conditions[] = '0=1';
                }
                $SQLinv =
"SELECT TOP 10
    CODAZI, CODDIP, COGNOME, NOME, SESSO, convert(varchar, DATANAS, 103) as DATANAS, COMNAS, PRONAS, COMRES, PRORES,
    VIARES, NUMRES,
    CAPRES, COMDOM, PRODOM, VIADOM, NUMDOM, CAPDOM, CITTAD, CODTITSTU, CODFIS, TELEFONO, CODCCNL,
    CODCANT, CODSEDEAZ, CODCENCO, CODREPARTO, RAGGRU1, RAGGRU2, CODTIPASS, CODQUALIF, CODLIVELLO,
    CODFUNZIO, CODMANSIO, MATRICOLA, BADGE, PARTITIPO, PARENTELA, CATEGPROT, TIPOASSUNZ,
    CASE WHEN DATALICEN IS NULL THEN '-' ELSE convert(varchar, DATALICEN, 103) END as DATALICEN,
    convert(varchar, DATAASSUNZ, 103) as DATAASSUNZ,
    TIPORAPPOR, TEMPODETER, FINEPROVA, FINERAPDET, PROROCONTR, RAPPTRASFE, ESTEDISTAC, CODORARIO, TIPOTURNO,
    TIPOPT, OREPT, PERCENPT, STATOCIVI, NUCLEOFAM, REDDITOFAM, LIVREDFAM, SCADENZANF, VARIAZNUCL, CONIUGE,
    FIGLI, ASCENDENTI, POSINPS, CODICEINPS, CODQINPS, CODTIPCO, CODALTRO, CODALTERN, CODSGRAVIO, CODFISCALI
FROM
    dbo.ARCDIPAN
WHERE
    " . implode(' AND ', $conditions) . "
ORDER BY
    COGNOME ASC";
                $per_aziende = $utility->getDatiPrepared($SQLinv, $params, $types);
                $aziende = array();
                foreach($per_aziende as $azienda) {
                    $aziende[] = $azienda;
                }
                $ar_return = array();
                $ar_return['ar_dati'] = $aziende;
                echo json_encode($ar_return);
            break;
            case 'aziende':
                $SQLinv = <<<SQL
SELECT
    CODAZI,
    RAGSOC,
    SIGLA,
    INIZIO,
    FINE,
    PARTIVA
FROM
    dbo.ARCAZI
ORDER BY
    RAGSOC ASC
SQL;
                $per_aziende = $utility->getDati($SQLinv);
                foreach($per_aziende as $azienda) {
                    $aziende[] = $azienda;
                }
                $ar_return = array();
                $ar_return['ar_dati'] = $aziende;
                echo json_encode($ar_return);
            break;
            case 'dettagli_utente':
                $codazi = (int)($_POST['ar_filtri']['codazi'] ?? 0);
                $coddip = (int)($_POST['ar_filtri']['coddip'] ?? 0);
                $SQLinv = <<<SQL
SELECT
    ARCDIPAN.CODAZI,
    ARCDIPAN.CODDIP,
    ANNO,FORMAT (DATAASSUNZ,'dd/MM/yyyy') AS data_assunzione,ANNO-year(DATAASSUNZ) AS anzianita,ANNO-year(DATANAS) AS eta,
    SUM(CASE WHEN CODVOCE IN ('002','081','082') THEN VIW_STOVOCI.IMPORTO ELSE 0 END) AS ORDINARIA,
    SUM(CASE WHEN CODVOCE IN ('I61') THEN VIW_STOVOCI.IMPORTO ELSE 0 END) AS IRPEF,
    SUM(CASE WHEN CODVOCE IN ('C97') THEN VIW_STOVOCI.IMPORTO ELSE 0 END) AS TOTALE_COMPETENZE,
    SUM(CASE WHEN CODVOCE IN ('C98') THEN VIW_STOVOCI.IMPORTO ELSE 0 END) AS TOTALE_RITENUTE,
    SUM(CASE WHEN CODVOCE IN ('C99') THEN VIW_STOVOCI.IMPORTO ELSE 0 END) AS NETTO
FROM dbo.VIW_STOVOCI
    JOIN ARCDIPAN ON (ARCDIPAN.CODAZI=VIW_STOVOCI.CODAZI AND ARCDIPAN.CODDIP=VIW_STOVOCI.CODDIP)
    LEFT JOIN TABVOCED ON (TABVOCED.CODICE=CODVOCE)
WHERE ARCDIPAN.CODAZI = ? AND ARCDIPAN.CODDIP = ? AND
    CODVOCE IN ('002','081','082','C97','C98','C99','I15','V61','V51','I61','019')
GROUP BY ARCDIPAN.CODAZI,
    ARCDIPAN.CODDIP,
    FORMAT (DATAASSUNZ,'dd/MM/yyyy') ,
    ANNO-year(DATAASSUNZ) ,
    ANNO-year(DATANAS),
    ANNO
ORDER BY ANNO DESC
SQL;
                $risultato = $utility->getDatiPrepared($SQLinv, [$codazi, $coddip], 'ii');
                $ar_return = array();
                $ar_return['ar_dati'] = $risultato;
                echo json_encode($ar_return);
            break;
            case 'anno':
                $codazi = (int)($_POST['ar_filtri']['codazi'] ?? 0);
                $coddip = (int)($_POST['ar_filtri']['coddip'] ?? 0);
                $anno = (int)($_POST['ar_filtri']['anno'] ?? 0);
                $SQLinv = <<<SQL
SELECT
    ? AS CODAZI,
    ? AS CODDIP,
    CONCAT(ANNO,'/',MESE) AS ANNO_MESE,
    ANNO,
    MESE,
    SUM(CASE WHEN CODVOCE = 'C06' THEN IMPORTO ELSE 0 END) AS TFR,
    SUM(CASE WHEN CODVOCE = '002' THEN IMPORTO ELSE 0 END) AS ORDINARIA,
    SUM(CASE WHEN CODVOCE = 'C97' THEN IMPORTO ELSE 0 END) AS COMPETENZE,
    SUM(CASE WHEN CODVOCE = 'I16' THEN IMPORTO ELSE 0 END) AS DETRAZIONI,
    SUM(CASE WHEN CODVOCE = '019' THEN IMPORTO ELSE 0 END) AS BONUS,
    SUM(CASE WHEN CODVOCE = '018' THEN IMPORTO ELSE 0 END) AS PREMIO,
    SUM(CASE WHEN CODVOCE = '176' THEN IMPORTO ELSE 0 END) AS PREMIO_RISULTATO,
    SUM(CASE WHEN CODVOCE = 'C99' THEN IMPORTO ELSE 0 END) AS NETTO
    FROM dbo.VIW_STOVOCI
WHERE
    CODAZI = ? AND
    CODDIP = ? AND
    ANNO = ?
 GROUP BY
    ANNO,
    MESE
 ORDER BY
    MESE DESC
SQL;
                $risultato = $utility->getDatiPrepared($SQLinv, [$codazi, $coddip, $codazi, $coddip, $anno], 'iiiii');
                $ar_return = array();
                $ar_return['ar_dati'] = $risultato;
                echo json_encode($ar_return);
            break;
            case 'cedolino':
                $codazi = (int)($_POST['ar_filtri']['codazi'] ?? 0);
                $coddip = (int)($_POST['ar_filtri']['coddip'] ?? 0);
                $mese = (int)($_POST['ar_filtri']['mese'] ?? 0);
                $anno = (int)($_POST['ar_filtri']['anno'] ?? 0);
                $SQLinv = <<<SQL
SELECT
    TABVOCED.DESCRIZ,
    CODVOCE,
    VIW_STOVOCI.IMPORTO
FROM
    dbo.VIW_STOVOCI
    LEFT JOIN TABVOCED ON (TABVOCED.CODICE=CODVOCE)
WHERE
    CODAZI = ? AND
    CODDIP = ? AND
    MESE = ? AND
    ANNO = ?
SQL;
                $risultato = $utility->getDatiPrepared($SQLinv, [$codazi, $coddip, $mese, $anno], 'iiii');
                $ar_return = array();
                $ar_return['ar_dati'] = $risultato;
                echo json_encode($ar_return);
            break;
        }
    break;
}
?>
