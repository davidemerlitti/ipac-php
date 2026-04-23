<?php

// src/Examples/ingestion-uploadhttps.php

/**
 * Esegue il flusso completo di "Ingestion via uploadHTTPS" utilizzando il client Kiota.
 *
 * 1. Ottiene un token da IAM/WSO2.
 * 2. Imposta il contesto di sicurezza sull'endpoint CAP.
 * 3. Fa l'upload di un file ZIP all'endpoint di Ingestion.
 * 4. Monitora il task asincrono sull'endpoint Batch.
 * 5. Recupera l'ACK finale dall'endpoint di Ingestion.
 *
 * ESECUZIONE:
 * php src/Examples/ingestion-uploadhttps.php /percorso/del/tuo/file.zip
 */

namespace App\Examples;

// L'autoloader gestirà tutte le classi, inclusi il client Kiota e i DTO.
require __DIR__.'/../../vendor/autoload.php';

use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Client as CapAutorizzazioneSoggettoSistemaClient;
use IPaC\Ingestion\Upload\Client\Client as IngestionClient;
use IPaC\Batch\Asincrono\Client\Client as BatchClient;
use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Models\AutenticazionePostRequestDTO;
use Microsoft\Kiota\Abstractions\ApiException;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use Microsoft\Kiota\Abstractions\MultiPartBody;
use Dotenv\Dotenv;
use IPaC\AccessTokenProvider;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Utils;

// --- 1. CARICAMENTO CONFIGURAZIONE ---
echo "1. Caricamento della configurazione dal file .env...\n";

if (file_exists(__DIR__.'/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

// Carica tutte le variabili necessarie
$config = [
    'clientId' => $_ENV['IPAC_CLIENT_ID'] ?? null,
    'clientSecret' => $_ENV['IPAC_CLIENT_SECRET'] ?? null,
    'tokenEndpoint' => $_ENV['IPAC_TOKEN_ENDPOINT'] ?? null,
    'apiBaseUrl' => $_ENV['IPAC_API_BASE_URL'] ?? 'https://ispc-preprod.prod.os01.ocp.cineca.it',
    'sistemaUUID' => $_ENV['sistemaUUID'] ?? null,
    'enteAderenteUUID' => $_ENV['enteAderenteUUID'] ?? null,
    'tenancyUUID' => $_ENV['tenancyUUID'] ?? null,
    'labelDescrittivaUtente' => $_ENV['labelDescrittivaUtente'] ?? 'operatore_upload',
    'idUtente' => $_ENV['idUtente'] ?? 'upload_user',
    'codiceRuolo' => $_ENV['codiceRuolo'] ?? 'AMMINISTRATORE',
    'debug' => $_ENV['DEBUG'] ?? false,
    'poll_every_s' => $_ENV['POLL_EVERY_S'] ?? 5,
    'poll_timeout_s' => $_ENV['POLL_TIMEOUT_S'] ?? 300,
];

// Validazione della configurazione
foreach ($config as $key => $value) {
    if ($value === null) {
        die("ERRORE: Variabile d'ambiente mancante: $key. Controlla il file .env.\n");
    }
}

// Validazione del percorso del file ZIP
if (!isset($argv[1])) {
    die("ERRORE: Manca il percorso del file ZIP.\nUso: php " . __FILE__ . " /percorso/del/file.zip\n");
}
$zipPath = $argv[1];
if (!file_exists($zipPath)) {
    die("ERRORE: File non trovato: $zipPath\n");
}

echo "   -> Configurazione caricata con successo.\n\n";

// --- 2. INIZIALIZZAZIONE DEL CLIENT API ---
echo "2. Inizializzazione del client API I.PaC...\n";

try {
    // Il provider del token è lo stesso per tutte le chiamate
    $tokenProvider = new AccessTokenProvider($config['clientId'], $config['clientSecret'], $config['tokenEndpoint']);
    $authProvider = new BaseBearerTokenAuthenticationProvider($tokenProvider);

    // Configura Guzzle con il middleware di debug se necessario
    if ($config['debug']) {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            // Logga solo le risposte di errore
            if ($response->getStatusCode() >= 400) {
                echo "\n--- DEBUG: Errore HTTP ---\n";
                echo "Status: " . $response->getStatusCode() . "\n";
                $body = (string) $response->getBody();
                echo "Body: " . $body . "\n";
                if ($response->getBody()->isSeekable()) $response->getBody()->rewind();
                echo "--------------------------\n";
            }
            return $response;
        }));
        $customGuzzleClient = new GuzzleHttpClient(['handler' => $handlerStack]);
        $capAutRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
        $ingestionRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
        $batchRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
    } else {
        $capAutRequestAdapter = new GuzzleRequestAdapter($authProvider);
        $ingestionRequestAdapter = new GuzzleRequestAdapter($authProvider);
        $batchRequestAdapter = new GuzzleRequestAdapter($authProvider);
    }

    // Crea le istanze dei client necesssari
    $capAuthClient = new CapAutorizzazioneSoggettoSistemaClient($capAutRequestAdapter);
    $ingestionClient = new IngestionClient($ingestionRequestAdapter);
    $batchClient = new BatchClient($batchRequestAdapter);
    
    echo "   -> Client inizializzati.\n\n";

    // --- 3. CREAZIONE CONTESTO DI SICUREZZA ---
    echo "3. Creazione del contesto di sicurezza...\n";

    $authRequestDto = new AutenticazionePostRequestDTO();
    $authRequestDto->setSistemaUUID($config['sistemaUUID']);
    $authRequestDto->setEnteAderenteUUID($config['enteAderenteUUID']);
    $authRequestDto->setTenancyUUID($config['tenancyUUID']);
    $authRequestDto->setLabelDescrittivaUtente($config['labelDescrittivaUtente']);
    $authRequestDto->setIdUtente($config['idUtente']);
    $authRequestDto->setCodiceRuolo($config['codiceRuolo']);
    
    // Chiama l'endpoint specifico per il contesto
    $capAuthClient->api()->v1()->cap()->autorizzazionesoggettosistema()->predisponeAutenticazione()->post($authRequestDto)->wait();
    
    echo "   -> OK: Contesto di sicurezza creato.\n\n";

    // --- 4. UPLOAD DEL FILE ZIP ---
    echo "4. Esecuzione dell'upload del file '$zipPath'...\n";
    
    // Apre il file zip in lettura
    $fileResource = fopen($zipPath, 'r');
    if ($fileResource === false) {
        throw new Exception("Impossibile aprire il file '$zipPath' per la lettura.");
    }
    
    // Crea un fileStram a partire dal file aperto
    $fileStream = Utils::streamFor($fileResource);
    
    // Crea un MultiPartBody e ci aggiunge il fileStream
    $multipartBody = new MultiPartBody();
    $reflection = new \ReflectionClass($multipartBody);
    $boundaryProp = $reflection->getProperty('boundary');
    $boundaryProp->setAccessible(true); // Rendiamo la proprietà scrivibile dall'esterno
    // Generiamo un boundary VALIDO (una stringa di testo) e sovrascriviamo quello corrotto
    $boundaryProp->setValue($multipartBody, "----KiotaBoundary" . bin2hex(random_bytes(16)));
    $multipartBody->setRequestAdapter($ingestionRequestAdapter);
    $multipartBody->addOrReplacePart('file', 'application/zip', $fileStream);
    
    // Invoca uploadhttps e aspetta che risponda
    $uploadResponse = $ingestionClient->api()->v1()->ingestion()->uploadhttps()->post($multipartBody)->wait();

    // Estrai gli UUID dalla risposta. I nomi esatti dei metodi `get...()`
    // dipendono da come Kiota ha generato il modello di risposta.
    $uuidTask = $uploadResponse->getUuidTask();
    $idPacchetto = $uploadResponse->getIdPacchetto();

    echo "   -> OK: Upload completato.\n";
    printf("      - uuidTask: %s\n", $uuidTask ?? '(mancante)');
    printf("      - idPacchetto: %s\n", $idPacchetto ?? '(mancante)');

    if (!$uuidTask) {
        throw new Exception("'uuidTask' non presente nella risposta di upload. Impossibile monitorare.");
    }

    // --- 5. MONITORAGGIO ASINCRONO ---
    echo "5. Avvio del monitoraggio del task (timeout: {$config['poll_timeout_s']}s)...\n";
    $startTime = microtime(true);
    $finalStatus = null;
    $finalStatuses = ['COMPLETED', 'FAILED', 'ERROR', 'DONE', 'SUCCESS', 'FINITO'];

    while (true) {
        $elapsed = microtime(true) - $startTime;
        if ($elapsed >= $config['poll_timeout_s']) {
            throw new Exception("Timeout raggiunto durante il monitoraggio del task.");
        }

        // Naviga fino all'endpoint di monitoraggio e chiama get()
        $monitorResponse = $batchClient->api()->v1()->batch()->tasks()->uuids()->byUuid($uuidTask)->full()->get()->wait();
        
        // Estrai i dati dal DTO di risposta
        $payload = $monitorResponse->getPayload();
        $codiceFase = $payload ? $payload->getCodiceFase() : null;
        $finalStatus = $codiceFase;
        
        printf("   -> [%.0fs/%ds] Stato del task: %s\n", $elapsed, $config['poll_timeout_s'], $codiceFase ?? '(sconosciuto)');

        // Aggiorna idPacchetto se presente
        $informazioniExtra = $payload ? $payload->getInformazioniExtra() : null;
        if ($informazioniExtra && property_exists($informazioniExtra, 'idPacchetto')) {
            $idPacchetto = $informazioniExtra->idPacchetto;
        }

        if ($codiceFase && in_array(strtoupper($codiceFase), $finalStatuses, true)) {
            echo "   -> Stato finale raggiunto: $codiceFase.\n";
            break;
        }
        sleep($config['poll_every_s']);
    }

    // --- 6. RECUPERO DELL'ACK ---
    if (!$idPacchetto) {
        throw new Exception("'idPacchetto' non disponibile. Impossibile recuperare l'ACK.");
    }
    
    if (strtoupper($finalStatus) !== 'COMPLETED') {
        echo "ATTENZIONE: Lo stato finale del task non è 'COMPLETED' ($finalStatus). L'ACK potrebbe contenere un errore.\n";
    }
    
    echo "\n6. Recupero del report ACK per idPacchetto '$idPacchetto'...\n";
    
    $ackResponse = $ingestionClient->api()->v1()->ingestion()->ack()->byId($idPacchetto)->get()->wait();

    echo "   -> OK: ACK ricevuto con successo.\n\n";

    // Stampa il corpo della risposta grezza, che dovrebbe essere l'XML/JSON dell'ACK
    echo "--- REPORT ACK ---\n";
    // Dato che la risposta potrebbe non essere JSON, la leggiamo come stream.
    $ackBody = (string) $ackResponse;
    // Se è un JSON/XML ben formattato, potremmo fare un pretty-print, ma per ora lo stampiamo così com'è.
    echo $ackBody;
    echo "\n------------------\n";

    echo "\nFlusso completato con successo!\n";

} catch (ApiException $e) {
    echo "\n!! ERRORE API DURANTE IL FLUSSO !!\n";
    // Il middleware di debug stamperà già il corpo se attivo,
    // altrimenti lo facciamo qui.
    if (!$config['debug']) {
        echo "Messaggio: " . $e->getMessage() . "\n";
    }
    exit(1);
} catch (Exception $e) {
    echo "\n!! ERRORE CRITICO !!\n";
    echo "Messaggio: " . $e->getMessage() . "\n";
    exit(1);
}
