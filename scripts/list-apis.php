<?php
/**
 * list-apis.php
 *
 * Questo script si connette all'endpoint principale delle API I.PaC per recuperare
 * l'elenco di tutte le definizioni OpenAPI disponibili. Per ogni definizione trovata,
 * scarica e analizza il file JSON per estrarre e stampare informazioni chiave come
 * il titolo ufficiale dell'API, la versione OpenAPI e l'URL completo della specifica.
 *
 * È uno strumento utile per esplorare e verificare rapidamente quali API sono esposte
 * e dove trovare le loro definizioni dettagliate.
 *
 * Dipendenze:
 * - cebe/php-openapi: per leggere e interpretare i file OpenAPI.
 *
 * Esecuzione:
 * php list-apis.php
 */
namespace App\Utils;

include "../vendor/autoload.php";

use cebe\openapi\Reader;

$swaggerSpecBaseUrl = 'https://ispc-preprod.prod.os01.ocp.cineca.it/swagger/spec/';

$apiSpecs = [];

// Opzioni di contesto per consentire a file_get_contents di funzionare con HTTPS senza verifica SSL
$contextOptions = [
  "ssl" => [
    "verify_peer" => false,
    "verify_peer_name" => false,
  ],
  'http' => [
    'method' => 'GET',
    'header' => "Accept: application/json\r\n"
  ]
];  

$streamContext = stream_context_create($contextOptions);
 
$json = file_get_contents($swaggerSpecBaseUrl, false, $streamContext);
if ($json) {
  $spec = json_decode($json);
  $basePath = $spec->basePath;
  foreach($spec->apis as $api) {
    $apiSpecs[] = [
      'description' => $api->description,
      'url' => "$basePath/" . str_replace('{format}', 'json', $api->path)
    ];
  }
}

foreach ($apiSpecs as $apiSpec) {
  $url = $apiSpec['url'];
  $description = $apiSpec['description'];
  try {
    $api = Reader::readFromJsonFile($url);
    // Accedi ai dati della descrizione API
    echo "IPaC API '{$api->info->title}'\n";  // titolo dell'API    
    echo "versione OpenAPI $api->openapi\n";  // versione OpenAPI, es. 3.0.1
    echo "descrizione '$description'\n";
    echo "url '$url'\n";
    echo "--\n";
  } catch (\Exception $e) {
    echo "Errore durante la lettura di '$url': " . $e->getMessage() . "\n--\n";
  }  
}
