<?php
/**
 * Examples/gpa-collezioni-byUuidCollezione.php
 *
 * Esempio di utilizzo di un client API generato per recuperare una specifica
 * collezione dall'API GPA (Gestione del Patrimonio).
 *
 * Passaggi:
 * 1. Configura le credenziali (Client ID, Client Secret) e gli endpoint.
 * 2. Istanzia il nostro IPaCAccessTokenProvider per gestire l'autenticazione OAuth2.
 * 3. Crea un RequestAdapter di Kiota, che userà il token provider per autenticare ogni chiamata.
 * 4. Istanzia il client specifico per l'API delle Collezioni.
 * 5. Effettua la chiamata per ottenere una collezione tramite il suo UUID.
 * 6. Gestisce e stampa eventuali eccezioni.
 */
namespace App\Examples;

use IPaC\GPA\Collezioni\Client;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use App\IPaCAccessTokenProvider;

require __DIR__.'/vendor/autoload.php';

$clientId = getenv('IPAC_CLIENT_ID') ?: 'YOUR_CLIENT_ID'; // Legge da variabili d'ambiente o usa un placeholder
$clientSecret = getenv('IPAC_CLIENT_SECRET') ?: 'YOUR_CLIENT_SECRET';
$apiBaseUrl = 'https://ispc-preprod.prod.os01.ocp.cineca.it';
$tokenEndpoint = 'https://identity.cloud.sbn.it/oauth2/token';
$uuidCollezione = ''; // Sostituisci con un UUID di una collezione esistente

if ($clientId === 'YOUR_CLIENT_ID' || $clientSecret === 'YOUR_CLIENT_SECRET') {
    die("Errore: Client ID e Client Secret non configurati. Imposta le variabili d'ambiente IPAC_CLIENT_ID e IPAC_CLIENT_SECRET o modifica lo script.\n");
}
if ($uuidCollezione === 'INSERISCI_UN_UUID_VALIDO_QUI') {
    die("Errore: Inserisci un UUID valido per la collezione da cercare.\n");
}

try {
  // 1. Setup del provider di token
  $tokenProvider = new IPaCAccessTokenProvider($clientId, $clientSecret, $tokenEndpoint);
    
  // 2. Setup dell'adapter Kiota con autenticazione Bearer
  $authProvider = new BaseBearerTokenAuthenticationProvider($tokenProvider);
  $requestAdapter = new GuzzleRequestAdapter($authProvider);
    
  // 3. Istanziazione del client API
  $client = new Client($requestAdapter);

  // 4. Esecuzione della chiamata
  echo "Recupero della collezione con UUID: $uuidCollezione...\n";
  $response = $client->api()->v1()->gpa()->collezioni()->byUuidCollezione($uuidCollezione)->get()->wait();

  // 5. Stampa del risultato
  print_r($response);
  
} catch(\Exception $e) {
  echo "Si è verificato un errore: " . get_class($e) . "\n";
  echo "Messaggio: " . $e->getMessage() . "\n";
}