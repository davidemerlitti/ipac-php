<?php
/**
 * src/Examples/gpa-risorsadigitale-create.php
 *
 * Crea una nuova risorsa digitale
 */
namespace App\Examples;

require __DIR__.'/../../vendor/autoload.php';

use IPaC\GPA\RisorsaDigitale\Client\Models\CreaRisorsaDigitaleRequestDTO;
use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Client as CapAutorizzazioneSoggettoSistemaClient;
use IPaC\CAP\AnagraficheRuoliServizi\Client\Client as CapAnagraficheRuoliServiziClient;
use IPaC\GPA\RisorsaDigitale\Client\Client as GpaClient;
use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Models\AutenticazionePostRequestDTO;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use Dotenv\Dotenv;
use IPaC\AccessTokenProvider;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;
use IPaC\Constants\Licenses\Preprod;

// Caricamento variabili d'ambiente
if (file_exists(__DIR__.'/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

$clientId = $_ENV['IPAC_CLIENT_ID'] ?? null;
$clientSecret = $_ENV['IPAC_CLIENT_SECRET'] ?? null;
$tokenEndpoint = $_ENV['IPAC_TOKEN_ENDPOINT'] ?? null;
if ($clientId === null || $clientSecret === null || $tokenEndpoint === null) {
    die("Errore: Client ID e Client Secret non configurati. Controlla il file .env.\n");
}

$debug = $_ENV['DEBUG'] ?? false;

try {
  $tokenProvider = new AccessTokenProvider($clientId, $clientSecret, $tokenEndpoint);
  $authProvider = new BaseBearerTokenAuthenticationProvider($tokenProvider);
  
  if ($debug) {
    $handlerStack = HandlerStack::create();
    
    $handlerStack->push(Middleware::mapResponse(function (ResponseInterface $response) {
        if ($response->getStatusCode() >= 400) {
            echo "\n---------------- DEBUG ERROR BODY ----------------\n";
            echo "Status Code: " . $response->getStatusCode() . "\n";

            $bodyStream = $response->getBody();
            $contents = (string) $bodyStream; // Questo legge tutto lo stream

            echo "Body: " . $contents . "\n";
            echo "--------------------------------------------------\n";

            if ($bodyStream->isSeekable()) {
                $bodyStream->rewind();
            }
        }
        return $response;
    }));

    $customGuzzleClient = new GuzzleHttpClient(['handler' => $handlerStack]);
    
    $capAutRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
    $capAnaRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
    $gpaRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
        
  } else {    
    $capAutRequestAdapter = new GuzzleRequestAdapter($authProvider);
    $capAnaRequestAdapter = new GuzzleRequestAdapter($authProvider);
    $gpaRequestAdapter = new GuzzleRequestAdapter($authProvider);
  }
  
  $capAutClient = new CapAutorizzazioneSoggettoSistemaClient($capAutRequestAdapter);
  $capAnaClient = new CapAnagraficheRuoliServiziClient($capAnaRequestAdapter);
  $gpaClient = new GpaClient($gpaRequestAdapter);

  echo "FASE 1: Impostazione del Contesto di Sicurezza tramite il servizio CAP...\n";
  
  $sistemaUUID = $_ENV['sistemaUUID'] ?? '';
  $enteAderenteUUID = $_ENV['enteAderenteUUID'] ?? '';
  $tenancyUUID = $_ENV['tenancyUUID'] ?? '';
  $labelDescrittivaUtente = $_ENV['labelDescrittivaUtente'] ?? '';
  $idUtente = $_ENV['idUtente'] ?? '';
  $codiceRuolo = $_ENV['codiceRuolo'] ?? '';
  
  $autenticazioneRequest = new AutenticazionePostRequestDTO();
  $autenticazioneRequest->setSistemaUUID($sistemaUUID);
  $autenticazioneRequest->setEnteAderenteUUID($enteAderenteUUID);
  $autenticazioneRequest->setTenancyUUID($tenancyUUID);
  $autenticazioneRequest->setLabelDescrittivaUtente($labelDescrittivaUtente);
  $autenticazioneRequest->setIdUtente($idUtente);
  $autenticazioneRequest->setCodiceRuolo($codiceRuolo);
  
  $capAutClient->api()->v1()->cap()->autorizzazionesoggettosistema()->predisponeAutenticazione()->post($autenticazioneRequest)->wait();
  echo "Contesto di Sicurezza impostato con successo.\n\n";

  $accessToken = $tokenProvider->getAuthorizationTokenAsync('')->wait();
  
  // Recupera l'elenco delle licenze disponibili per la tenancy in sessione
  $licenze = $capAnaClient->api()->v1()->cap()->gestionetenancy()->licenza()->readByTenancy()->get()->wait();
  
  echo "Licenze disponibili per la tenancy '$tenancyUUID'\n";
  if ($licenze) {
    print_r($licenze);
  } else {
    echo "Nessuna licenza disponibile\n";
  }
  
  // Controlla se la licenza da impostare CC-BY per la risorsa digitale è compresa
  // tra quelle disponibili per la tenancy
  if(array_filter($licenze, function($licenza) {
    $uuid1 = $licenza->getUuid();
    $uuid2 = $licenza->getLicenza()->getUuid();
    return ($licenza->getLicenza()->getUuid() == Preprod::CC_BY);
  })) {
    echo "FASE 2: Eseguo la creazione di una risorsa digitale sull'endpoint GPA...\n";

    $risorsaDigitaleRequest = new CreaRisorsaDigitaleRequestDTO();
    $risorsaDigitaleRequest->setTitolo('Test Contenitore per Oggetto ' . uniqid());
    $risorsaDigitaleRequest->setUuidLicenza(Preprod::CC_BY);
    $risorsaDigitaleRequest->setAutore('gpa-risorsadigitale-create.php');
    $risorsaDigitaleRequest->setDescrizione('Questa è una descrizione generata automaticamente dal test.');
    $risorsaDigitaleRequest->setNote('Nessuna nota particolare.');
    $risorsaDigitaleRequest->setIdConservatore('00000000000');
    $risorsaDigitaleRequest->setIdConservatoreAuthority('CF');
    $risorsaDigitaleRequest->setUuidTenancy($tenancyUUID);

    try {
        $nuovaRisorsaDigitale = $gpaClient->api()->v1()->gpa()->risorseDigitali()->post($risorsaDigitaleRequest)->wait();
        $uuid = $nuovaRisorsaDigitale->getUuid();  
        echo "Creata la risorsa digitale con UUID $uuid\n";
    } catch (\Exception $e) {
      echo "Si è verificato un errore durante la creazione della nuova risorsa digitale:\n";
      echo $e->getMessage();
    }
  } else {
    echo "Impossibile creare la risorsa digitale perché la licenza specificata non è tra quelle disponibili.\n";
  }
  
} catch(\Exception $e) {
  echo "Si è verificato un errore:\n";
  echo $e->getMessage();
}


