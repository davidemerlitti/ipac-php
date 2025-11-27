<?php
/**
 * Examples/gpa-collezioni-byUuidCollezione.php
 *
 * Esempio di utilizzo di un client API generato per recuperare una specifica
 * collezione dall'API GPA (Gestione del Patrimonio).
 *
 * Passaggi:
 * 1. Configura le credenziali (Client ID, Client Secret) e gli endpoint.
 * 2. Istanzia il IPaC\AccessTokenProvider per gestire l'autenticazione OAuth2.
 * 3. Crea un RequestAdapter di Kiota, che userà il token provider per autenticare ogni chiamata.
 * 4. Istanzia il client specifico per l'API delle Collezioni.
 * 5. Effettua la chiamata per ottenere una collezione tramite il suo UUID.
 * 6. Gestisce e stampa eventuali eccezioni.
 */
namespace App\Examples;

require __DIR__.'/../../vendor/autoload.php';

use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Client as CapClient;
use IPaC\GPA\Collezioni\Client\Client as ColClient;
use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Models\AutenticazionePostRequestDTO;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use IPaC\AccessTokenProvider;
use Dotenv\Dotenv;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface;

// Caricamento variabili d'ambiente
if (file_exists(__DIR__.'/../../.env')) {
  $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
  $dotenv->load();
}

$clientId = $_ENV['IPAC_CLIENT_ID'] ?? '';
$clientSecret = $_ENV['IPAC_CLIENT_SECRET'] ?? '';
$tokenEndpoint = $_ENV['IPAC_TOKEN_ENDPOINT'] ?? '';

if ($clientId === '' || $clientSecret === '') {
  die("Errore: Client ID e Client Secret non configurati. Controlla il file .env.\n");
}

$uuidCollezione = '00000000000000000000000000000000'; // Sostituisci con un UUID di una collezione esistente

if ($uuidCollezione === '') {
  die("Errore: Inserisci un UUID valido per la collezione da cercare.\n");
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
    $colRequestAdapter = new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient);
  } else {
    $capRequestAdapter = new GuzzleRequestAdapter($authProvider);
    $colRequestAdapter = new GuzzleRequestAdapter($authProvider);
  }
  
  $capClient = new CapClient($capRequestAdapter);
  
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
  
  $capClient->api()->v1()->cap()->autorizzazionesoggettosistema()->predisponeAutenticazione()->post($autenticazioneRequest)->wait();
  echo "Contesto di Sicurezza impostato con successo.\n\n";
  
  echo "FASE 2: Eseguo la richiesta della collezione con UUID '$uuidCollezione'...\n";
  
  $colClient = new ColClient($colRequestAdapter);

  echo "Recupero della collezione con UUID: $uuidCollezione...\n";
  $response = $colClient->api()->v1()->gpa()->collezioni()->byUuidCollezione($uuidCollezione)->get()->wait();
  echo "Collezione recuperata con successo\n";
  
  print_r($response);
  
} catch(\Exception $e) {
  echo "Si è verificato un errore: " . get_class($e) . "\n";
  echo "Messaggio: " . $e->getMessage() . "\n";
}