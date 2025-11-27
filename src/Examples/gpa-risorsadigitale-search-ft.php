<?php
/**
 * src/Examples/gpa-risorsadigitale-search-ft.php
 *
 * Esegue una ricerca fulltext
 */
namespace App\Examples;

require __DIR__.'/../../vendor/autoload.php';

use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Client as CapClient;
use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Models\AutenticazionePostRequestDTO;
use IPaC\GPA\Ricercaentitadigitali\Client\Client as RicercaClient;
use IPaC\GPA\Ricercaentitadigitali\Client\Models\GPADLRicercaFullTextEntitaDigitaleRequestDTO;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use Dotenv\Dotenv;
use IPaC\AccessTokenProvider;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;

// Caricamento variabili d'ambiente
if (file_exists(__DIR__.'/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
    $dotenv->load();
}

$clientId = $_ENV['IPAC_CLIENT_ID'] ?? '';
$clientSecret = $_ENV['IPAC_CLIENT_SECRET'] ?? '';
$tokenEndpoint = $_ENV['IPAC_TOKEN_ENDPOINT'] ?? '';
if ($clientId === '' || $clientSecret === '') {
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
    $capRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
    $ricercaRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
  } else {
    $capRequestAdapter = new GuzzleRequestAdapter($authProvider);
    $ricercaRequestAdapter = new GuzzleRequestAdapter($authProvider);
  }
  
  $capClient = new CapClient($capRequestAdapter);
  $ricercaClient = new RicercaClient($ricercaRequestAdapter);
    
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
  
  // Eseguiamo la chiamata per stabilire il contesto per la nostra sessione
  $capClient->api()->v1()->cap()->autorizzazionesoggettosistema()->predisponeAutenticazione()->post($autenticazioneRequest)->wait();
  echo "Contesto di Sicurezza impostato con successo.\n\n";

  $accessToken = $tokenProvider->getAuthorizationTokenAsync('')->wait();
      
  echo "FASE 2: Eseguo la ricerca sull'endpoint GPA...\n";
  $requestBody = new GPADLRicercaFullTextEntitaDigitaleRequestDTO();
  $requestBody->setRicercaTesto('prova');
  $requestBody->setFlagOwner(false);
  $requestBody->setDimensionePagina(10);
  $requestBody->setOffsetPagina(0);
      
  // Ora questa chiamata funzionerà, perché il server sa chi siamo e cosa possiamo fare.
  $responseStream = $ricercaClient->api()->v1()->gpa()->entitaDigitali()->ricerca()->ft()->post($requestBody)->wait();

  // --- FASE 3: Lettura del Risultato ---
  echo "Ricerca completata. Leggo il risultato dallo stream...\n";
  $jsonResponse = $responseStream->getContents();
  $data = json_decode($jsonResponse, true);

  echo "Elenco entità digitali recuperato con successo:\n";
  print_r($data);
  
} catch(\Exception $e) {
  echo "Si è verificato un errore:\n";
  echo $e->getMessage();  
}
