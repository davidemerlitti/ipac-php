<?php
/**
 * Examples/gpa-collezioni-get-all.php
 *
 * Esempio di utilizzo di un client API generato per elencare le collezioni
 * dall'API GPA (Gestione del Patrimonio).
 *
 * Passaggi:
 * 1. Configura le credenziali (Client ID, Client Secret) e gli endpoint.
 * 2. Istanzia IPaCAccessTokenProvider per gestire l'autenticazione OAuth2.
 * 3. Crea un RequestAdapter di Kiota, che userà il token provider per autenticare ogni chiamata.
 * 4. Istanzia il client specifico per l'API delle Collezioni.
 * 5. Effettua la chiamata per ottenere la lista di tutte le collezioni.
 * 6. Gestisce e stampa eventuali eccezioni.
 */
namespace App\Examples;

require __DIR__.'/../../vendor/autoload.php';

use IPaC\GPA\Collezioni\Client;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use IPaC\AccessTokenProvider;
use Dotenv\Dotenv;

// Caricamento variabili d'ambiente
if (file_exists(__DIR__.'/../../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

$clientId = $_ENV['IPAC_CLIENT_ID'] ?? null;
$clientSecret = $_ENV['IPAC_CLIENT_SECRET'] ?? null;
$tokenEndpoint = $_ENV['IPAC_TOKEN_ENDPOINT'] ?? null;

if ($clientId === null || $clientSecret === null || $clientId === null) {
    die("Errore: Client ID e Client Secret non configurati. Imposta le variabili d'ambiente IPAC_CLIENT_ID e IPAC_CLIENT_SECRET o modifica lo script.\n");
}

try {
  // 1. Setup del provider di token
  $tokenProvider = new AccessTokenProvider($clientId, $clientSecret, $tokenEndpoint);
    
  // 2. Setup dell'adapter Kiota con autenticazione Bearer
  $authProvider = new BaseBearerTokenAuthenticationProvider($tokenProvider);
  $requestAdapter = new GuzzleRequestAdapter($authProvider);
    
  // 3. Istanziazione del client API
  $client = new Client($requestAdapter);

  // 4. Esecuzione della chiamata per ottenere la lista delle collezioni
  echo "Recupero dell'elenco delle collezioni...\n";
  // NOTA: La chiamata è stata modificata rimuovendo ->byUuidCollezione($uuidCollezione)
  // per ottenere la lista completa delle risorse.
  $response = $client->api()->v1()->gpa()->collezioni()->get()->wait();

  // 5. Stampa del risultato
  echo "Elenco collezioni recuperato con successo:\n";
  print_r($response);
  
} catch(\Exception $e) {
  echo "Si è verificato un errore: " . get_class($e) . "\n";
  echo "Messaggio: " . $e->getMessage() . "\n";
  // Se l'errore è dettagliato (es. una ApiException), potresti voler stampare più informazioni
  if (method_exists($e, 'getResponse')) {
      print_r($e->getResponse());
  }
}
