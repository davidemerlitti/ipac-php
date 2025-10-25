<?php

require '../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

echo "=================================================\n";
echo "  I.PaC API Client Generator via Kiota (v3.2)   \n";
echo "=================================================\n\n";

// =============================================================================
// --- Configurazione ---
// =============================================================================

$hostUrl = "https://ispc-preprod.prod.os01.ocp.cineca.it";
$specListUrl = "$hostUrl/swagger/spec/";

$kiotaPath = __DIR__ . '/kiota/kiota.exe';
$definitionsDir = __DIR__ . '/api-definitions';

$outputBaseDir = __DIR__ . '/src/IPaC';
$baseNamespace = 'IPaC';

$prefixToGroupName = [
    'gpa' => 'GPA', 
    'cap' => 'CAP', 
    'batch' => 'Batch',
    'ingestion' => 'Ingestion', 
    'publicapi' => 'PublicAPI'
];

// =============================================================================
// --- Logica di Esecuzione ---
// =============================================================================

try {
    if (!file_exists($kiotaPath)) throw new Exception("Kiota non trovato: '$kiotaPath'");
    if (!is_dir($definitionsDir)) {
        echo "-> Creazione dir: $definitionsDir\n";
        if (!mkdir($definitionsDir, 0755, true)) throw new Exception("Impossibile creare dir '$definitionsDir'.");
    }

    $client = new Client(['verify' => false, 'headers' => ['Accept' => 'application/json']]);

    echo "1. Scaricamento elenco API da: $specListUrl\n";
    $apiList = json_decode($client->get($specListUrl)->getBody()->getContents());
    if (empty($apiList->apis)) throw new Exception("Nessuna API trovata nell'elenco.");
    $totalApis = count($apiList->apis);
    echo "   Trovate $totalApis definizioni.\n\n";
    
    echo "2. Processamento e generazione...\n";
    foreach ($apiList->apis as $index => $apiInfo) {
        $specPath = str_replace('.{format}', '.json', $apiInfo->path);
        $fileName = basename($specPath);

        echo "-------------------------------------------------\n";
        echo "   Processando (" . ($index + 1) . "/$totalApis): $fileName (Desc: {$apiInfo->description})\n";
        
        try {
            // --- 2a: Deriva Gruppo e Nome API ---
            $description = $apiInfo->description;
            $groupName = null;
            $apiName = null;
            foreach ($prefixToGroupName as $prefix => $name) {
                if (str_starts_with(strtolower($description), $prefix)) {
                    $groupName = $name;
                    $apiName = ucfirst(substr($description, strlen($prefix)));
                    break;
                }
            }
            if (!$groupName) {
                echo "   -> ATTENZIONE: Nessun gruppo noto per '$description'. Salto.\n";
                continue;
            }
            
            // --- 2b: Scarica la definizione originale ---
            $specJsonContent = $client->get($hostUrl . $specPath)->getBody()->getContents();
            
            // --- 2c: MODIFICA IL JSON IN MEMORIA ---
            echo "   -> Modifica dell'URL del server nella definizione...\n";
            $specData = json_decode($specJsonContent, true);
            $clientBaseUrl = $hostUrl . dirname($specPath); // Calcola l'URL pubblico corretto

            if (isset($specData['servers']) && is_array($specData['servers'])) {
                foreach ($specData['servers'] as &$server) { // Usa un riferimento per modificare l'array originale
                    if (isset($server['url'])) {
                        $originalUrl = $server['url'];
                        $server['url'] = $clientBaseUrl;
                        echo "      -> Sostituito '$originalUrl' \n         con '$clientBaseUrl'\n";
                    }
                }
            } else {
                 echo "      -> Aggiunta blocco 'servers' mancante.\n";
                 $specData['servers'] = [['url' => $clientBaseUrl]];
            }
            
            // --- 2d: Salva il JSON modificato su disco ---
            $modifiedJsonContent = json_encode($specData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $localSpecPath = $definitionsDir . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($localSpecPath, $modifiedJsonContent);
            
            // --- 2e: Costruisci path e namespace ---
            $outputDir = $outputBaseDir . '/' . $groupName . '/' . $apiName . '/Client';
            $fullNamespace = $baseNamespace . '\\' . $groupName . '\\' . $apiName . '\\Client';
            echo "   -> Namespace: $fullNamespace\n";

            // --- 2f: Esegui Kiota (SENZA --base-url) ---
            $command = implode(' ', [
                escapeshellarg($kiotaPath), 'generate', '-l PHP',
                '-d ' . escapeshellarg($localSpecPath),
                '-c Client',
                '-n ' . escapeshellarg($fullNamespace),
                '-o ' . escapeshellarg($outputDir),
            ]);
            
            exec($command . ' 2>&1', $output, $resultCode);
            if ($resultCode === 0) {
                echo "   -> SUCCESSO: Client generato.\n";
            } else {
                echo "   -> ERRORE durante la generazione del client.\n";
                echo "      Output di Kiota:\n" . implode("\n", $output) . "\n";
            }
            
        } catch (RequestException $e) {
            echo "   -> ERRORE: Impossibile scaricare o processare. " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "\n\nERRORE FATALE: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=================================================\n";
echo "  Processo di generazione completato.         \n";
echo "=================================================\n";
exit(0);