<?php

// src/Examples/guzzle-ingestion-uploadhttps.php

/**
 * Porting PHP completo del flusso di "Ingestion via uploadHTTPS" di Gianfranco.
 *
 * Questo script usa Guzzle per tutte le chiamate HTTP per replicare fedelmente
 * la logica dello script Perl originale e bypassare i problemi di serializzazione
 * riscontrati con il client Kiota per le richieste multipart.
 *
 * ESECUZIONE:
 * php src/Examples/ingestion-uploadhttps.php /percorso/del/tuo/file.zip
 */

namespace App\Examples;

require __DIR__.'/../../vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use IPaC\AccessTokenProvider;

// --- 1. CARICAMENTO CONFIGURAZIONE ---
echo "=================================================\n";
echo "  I.PaC Ingestion UploadHTTPS\n";
echo "=================================================\n\n";
echo "1. Caricamento configurazione dal file .env...\n";

if (file_exists(__DIR__.'/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

$config = [
    'clientId'        => $_ENV['IPAC_CLIENT_ID'],
    'clientSecret'    => $_ENV['IPAC_CLIENT_SECRET'],
    'tokenEndpoint'   => $_ENV['IPAC_TOKEN_ENDPOINT'],
    'secCtxUrl'       => 'https://cap-apicast-preprod.prod.os01.ocp.cineca.it/capautorizzazionesoggettosistema/api/v1/cap/autorizzazionesoggettosistema/predisponeAutenticazione',
    'uploadUrl'       => 'https://ingestion-apicast-preprod.prod.os01.ocp.cineca.it/ingestionupload/api/v1/ingestion/uploadhttps',
    'monitorUrlTmpl'  => 'https://batch-apicast-preprod.prod.os01.ocp.cineca.it/batchasincrono/api/v1/batch/tasks/uuids/{{uuid_task}}/full',
    'ackUrlTmpl'      => 'https://ingestion-apicast-preprod.prod.os01.ocp.cineca.it/ingestioning/api/v1/ingestion/ack/{{id_pacchetto}}',
    'sistemaUUID'     => $_ENV['sistemaUUID'],
    'enteAderenteUUID'=> $_ENV['enteAderenteUUID'],
    'tenancyUUID'     => $_ENV['tenancyUUID'],
    'labelDescrittivaUtente' => $_ENV['labelDescrittivaUtente'],
    'idUtente'        => $_ENV['idUtente'],
    'codiceRuolo'     => $_ENV['codiceRuolo'],
    'poll_every_s'    => $_ENV['POLL_EVERY_S'] ?? 5,
    'poll_timeout_s'  => $_ENV['POLL_TIMEOUT_S'] ?? 300,
];

if (!isset($argv[1])) die("ERRORE: Manca il percorso del file ZIP.\n");
$zipPath = $argv[1];
if (!file_exists($zipPath)) die("ERRORE: File non trovato: $zipPath\n");

echo "   -> OK.\n\n";

// Inizializza il client Guzzle che useremo per tutte le chiamate
$guzzle = new GuzzleHttpClient(['verify' => false, 'http_errors' => true, 'timeout' => 60.0]);

try {
    // --- PASSO 1: OTTIENI TOKEN DA IAM/WSO2 ---
    echo "PASSO 1: Ottenimento del token di accesso da IAM...\n";
    $tokenProvider = new AccessTokenProvider($config['clientId'], $config['clientSecret'], $config['tokenEndpoint']);
    $accessToken = $tokenProvider->getAuthorizationTokenAsync('')->wait();
    if (!$accessToken) throw new \Exception("Impossibile ottenere un token di accesso valido.");
    echo "   -> OK: Token ottenuto.\n\n";
    
    // --- PASSO 2: CREA CONTESTO DI SICUREZZA SU I.PAC ---
    echo "PASSO 2: Creazione del Contesto di Sicurezza su I.PaC...\n";
    
    $contextBody = [
        'sistemaUUID' => $config['sistemaUUID'], 'enteAderenteUUID' => $config['enteAderenteUUID'],
        'tenancyUUID' => $config['tenancyUUID'], 'labelDescrittivaUtente' => $config['labelDescrittivaUtente'],
        'idUtente' => $config['idUtente'], 'codiceRuolo' => $config['codiceRuolo'],
    ];

    $guzzle->post($config['secCtxUrl'], [
        'headers' => ['Authorization' => "Bearer {$accessToken}"],
        'json' => $contextBody
    ]);
    echo "   -> OK: Contesto creato. Il token è ora attivo per I.PaC.\n\n";

    // --- PASSO 3: ESEGUI UPLOAD ---
    echo "PASSO 3: Esecuzione dell'upload di '$zipPath'...\n";
    
    $response = $guzzle->post($config['uploadUrl'], [
        'headers' => ['Authorization' => "Bearer {$accessToken}"],
        'multipart' => [
            ['name' => 'file', 'contents' => fopen($zipPath, 'r'), 'filename' => basename($zipPath)]
        ]
    ]);
    $uploadData = json_decode($response->getBody()->getContents(), true);

    $uuidTask = $uploadData['uuidTask'] ?? null;
    $idPacchetto = $uploadData['idPacchetto'] ?? null;

    echo "   -> OK: Upload completato.\n";
    printf("      - uuidTask: %s\n", $uuidTask ?? '(mancante)');
    printf("      - idPacchetto: %s\n", $idPacchetto ?? '(mancante)');
    if (!$uuidTask) throw new \Exception("'uuidTask' non presente nella risposta di upload. Impossibile monitorare.");
    echo "\n";

    // --- PASSO 4: MONITORAGGIO ASINCRONO ---
    echo "PASSO 4: Avvio del monitoraggio del task (timeout: {$config['poll_timeout_s']}s)...\n";
    $startTime = microtime(true);
    $finalStatus = null;
    $finalStatuses = ['COMPLETED', 'FAILED', 'ERROR', 'DONE', 'SUCCESS', 'FINITO'];

    while (true) {
        $elapsed = microtime(true) - $startTime;
        if ($elapsed >= $config['poll_timeout_s']) throw new \Exception("Timeout raggiunto durante il monitoraggio del task.");

        $monitorUrl = str_replace('{{uuid_task}}', $uuidTask, $config['monitorUrlTmpl']);
        $monitorResponse = $guzzle->get($monitorUrl, ['headers' => ['Authorization' => "Bearer {$accessToken}"]]);
        $monitorData = json_decode($monitorResponse->getBody()->getContents(), true);
        
        $codiceFase = $monitorData['payload']['codiceFase'] ?? null;
        
        if (strtoupper($codiceFase ?? '') === 'ERROR') {
            echo "--- DEBUG: Dettaglio del Task in ERRORE ---\n";
            print_r($monitorData);
            echo "-------------------------------------------\n";
        }
    
        $finalStatus = $codiceFase;
        
        printf("   -> [%.0fs/%ds] Stato del task: %s\n", $elapsed, $config['poll_timeout_s'], $codiceFase ?? '(sconosciuto)');

        // Aggiorna idPacchetto se viene fornito durante il monitoraggio
        $idPacchettoFromMonitor = $monitorData['payload']['informazioniExtra']['idPacchetto'] ?? null;
        if ($idPacchettoFromMonitor) {
            $idPacchetto = $idPacchettoFromMonitor;
        }

        if ($codiceFase && in_array(strtoupper($codiceFase), $finalStatuses, true)) {
            echo "   -> Stato finale raggiunto: $codiceFase.\n";
            break;
        }
        sleep($config['poll_every_s']);
    }
    echo "\n";

    // --- PASSO 5: RECUPERO DELL'ACK ---
    if (!$idPacchetto) throw new \Exception("'idPacchetto' non disponibile. Impossibile recuperare l'ACK.");
    
    if (strtoupper($finalStatus) !== 'COMPLETED') {
        echo "ATTENZIONE: Lo stato finale del task non è 'COMPLETED' ($finalStatus). L'ACK potrebbe contenere un errore.\n";
    }
    
    echo "PASSO 5: Recupero del report ACK per idPacchetto '$idPacchetto'...\n";
    
    $ackUrl = str_replace('{{id_pacchetto}}', $idPacchetto, $config['ackUrlTmpl']);
    $ackResponse = $guzzle->get($ackUrl, ['headers' => ['Authorization' => "Bearer {$accessToken}"]]);
    $ackData = json_decode($ackResponse->getBody()->getContents(), true);

    echo "   -> OK: ACK ricevuto con successo.\n\n";

    echo "--- REPORT ACK ---\n";
    echo json_encode($ackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n------------------\n";

    echo "\nFlusso completato con successo!\n";

} catch (RequestException $e) {
    echo "\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "    ERRORE HTTP DURANTE IL FLUSSO \n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "Messaggio: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "\n--- Corpo della Risposta di Errore ---\n";
        echo $e->getResponse()->getBody()->getContents() . "\n";
        echo "-------------------------------------\n";
    }
    exit(1);
} catch (\Exception $e) {
    echo "\nERRORE GENERICO: " . $e->getMessage() . "\n";
    exit(1);
}