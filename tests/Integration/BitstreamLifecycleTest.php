<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\ClientException;
use Microsoft\Kiota\Abstractions\MultiPartBody;

// Importa i DTO necessari dai loro namespace corretti
use IPaC\GPA\RisorsaDigitale\Client\Models\CreaRisorsaDigitaleRequestDTO;
use IPaC\GPA\OggettoDigitale\Client\Models\CreaOggettoDigitaleRequestDTO;

class BitstreamLifecycleTest extends AuthenticatedTest
{
    public function testFullLifecycleWithResourceObjectAndBitstream(): void
    {
        $risorsaId = null;
        $oggettoId = null;
        $tempFilePath = null;

        try {
            // --- Passo 0: Prepara file fittizio ---
            $tempFilePath = tempnam(sys_get_temp_dir(), 'test_upload_');
            file_put_contents($tempFilePath, 'Contenuto del bitstream di test. ' . date('c'));
            echo "\nStep 0: Creato file di test: $tempFilePath";

            // --- 1. CREA RISORSA DIGITALE ---
            echo "\nStep 1: Creazione Risorsa Digitale...";
            $risorsaBody = new CreaRisorsaDigitaleRequestDTO();
            $risorsaBody->setTitolo('Test Risorsa Completa ' . uniqid());
            $risorsaBody->setUuidLicenza('16246EE2DB0A28D9E0630204FE0AFA8C'); // CC BY (PREPROD)
            $risorsaBody->setAutore('ipac-php Test Suite di Integrazione');
            $risorsaBody->setDescrizione('Questa è una descrizione generata automaticamente dal test.');
            $risorsaBody->setNote('Nessuna nota particolare.');
            $risorsaBody->setIdConservatore('00000000000');
            $risorsaBody->setIdConservatoreAuthority('CF');
            
            try {
                $nuovaRisorsa = $this->risorsaDigitaleClient->api()->v1()->gpa()->risorseDigitali()->post($risorsaBody)->wait();
            } catch (\Microsoft\Kiota\Abstractions\ApiException $e) {
                // Catturiamo l'eccezione generica di Kiota
                echo "\n--- ERRORE APIEXCEPTION ---\n";
                echo "Status Code: " . $e->getCode() . "\n";
                echo "Messaggio: " . $e->getMessage() . "\n";

                // Proviamo a vedere se l'eccezione precedente (quella di Guzzle) è incapsulata
                $previous = $e->getPrevious();
                if ($previous instanceof \GuzzleHttp\Exception\RequestException && $previous->hasResponse()) {
                    $response = $previous->getResponse();
                    echo "--- Dettagli Guzzle ---\n";
                    echo "Status Code Guzzle: " . $response->getStatusCode() . "\n";
                    echo "Body:\n" . $response->getBody()->getContents() . "\n";
                    echo "-----------------------\n";
                }
                
                echo "-------------------------\n";
                
                $this->fail("La chiamata API è fallita. Controllare l'output 'ERRORE APIEXCEPTION'.");
            }
            
            $risorsaId = $nuovaRisorsa->getChiaveRisorsaDigitaleISPC();
            
            $this->assertNotEmpty($risorsaId, "La Risorsa Digitale deve avere un ID.");
            echo " OK (ID Risorsa: $risorsaId)";

            // --- 2. CREA OGGETTO DIGITALE (collegato alla risorsa) ---
            echo "\nStep 2: Creazione Oggetto Digitale...";
            $oggettoBody = new CreaOggettoDigitaleRequestDTO();
            $oggettoBody->setTitolo('Master per Test ' . uniqid()); // Usa setTitolo, non setNome
            $oggettoBody->setTipologiaMedia('documento'); // Usa setTipologiaMedia, non setTipo
            
            // Creiamo l'oggetto DENTRO il path della risorsa
            $nuovoOggetto = $this->oggettoDigitaleClient->api()->v1()->gpa()->risorseDigitali()->byChiaveRisorsaDigitaleISPC($risorsaId)->oggettiDigitali()->post($oggettoBody)->wait();
            $oggettoId = $nuovoOggetto->getChiaveOggettoDigitaleISPC();

            $this->assertNotEmpty($oggettoId, "L'Oggetto Digitale deve avere un ID.");
            echo " OK (ID Oggetto: $oggettoId)";
            
            // --- 3. UPLOAD BITSTREAM (nell'oggetto) ---
            echo "\nStep 3: Upload del Bitstream...";
            $fileStream = fopen($tempFilePath, 'r');
            if (!$fileStream) $this->fail("Impossibile aprire lo stream al file di test.");

            // Per gli upload multipart, si usa la classe MultiPartBody
            $multipartBody = new MultiPartBody();
            $multipartBody->addPart($fileStream, 'file', 'test.txt'); // nome parte, stream, nome file
            
            // La chiamata POST per l'upload
            $this->gestioneBitstreamClient
                 ->api()->v1()->gpa()->oggettiDigitali()->byUuidOggettoDigitale($oggettoId)
                 ->bitStreams()->post($multipartBody)->wait();
            
            // Non c'è ID di ritorno, verifichiamo solo che la chiamata non abbia dato eccezioni
            echo " OK (Chiamata di upload eseguita)";

            // --- VERIFICA E RECUPERO ID BITSTREAM ---
            echo "\nStep 3b: Verifica e recupero ID Bitstream...";
            $oggettoAggiornato = $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettiDigitali()->byChiaveOggettoDigitaleISPC($oggettoId)->get()->wait();
            $bitstreams = $oggettoAggiornato->getBitStreams();
            $this->assertIsArray($bitstreams, "Dovrebbe esserci un array di bitstream.");
            $this->assertCount(1, $bitstreams, "Dovrebbe esserci un solo bitstream.");
            $bitstreamId = $bitstreams[0]; // Recuperiamo l'ID dall'array
            echo " OK (Trovato ID Bitstream: $bitstreamId)";


            // ... qui andrebbero gli altri passi (finalizzazione, ecc.) ...
            // Per ora ci fermiamo qui per verificare il flusso principale.
            
        } finally {
            // --- BLOCCO DI PULIZIA ROBUSTO ---
            echo "\n--- Blocco di Pulizia Finale ---";
            if ($tempFilePath && file_exists($tempFilePath)) { unlink($tempFilePath); }
            
            // L'eliminazione dell'oggetto digitale dovrebbe eliminare anche i bitstream contenuti.
            // L'eliminazione della risorsa digitale dovrebbe eliminare anche gli oggetti contenuti.
            // Quindi cancelliamo solo l'entità di primo livello che siamo riusciti a creare.
            if ($risorsaId) {
                try {
                    $this->risorsaDigitaleClient->api()->v1()->gpa()->risorseDigitali()->byChiaveRisorsaDigitaleISPCId($risorsaId)->delete(function($config) {
                        // Forziamo la cancellazione anche se ci sono oggetti figli
                        $config->queryParameters->forceFiglia = true; 
                    })->wait();
                    echo "\nPulizia: Risorsa $risorsaId e contenuti eliminati.";
                } catch (\Exception $e) {
                     echo "\nPulizia FALLITA per Risorsa $risorsaId: " . $e->getMessage();
                }
            } elseif ($oggettoId) { // Se la risorsa non è stata creata ma l'oggetto sì
                 try {
                    $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettiDigitali()->byChiaveOggettoDigitaleISPC($oggettoId)->delete()->wait();
                    echo "\nPulizia: Oggetto $oggettoId eliminato.";
                } catch (\Exception $e) { /* Ignora */ }
            }
        }
    }
}