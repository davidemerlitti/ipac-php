<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\ClientException;

// Importa i DTO necessari dai namespace corretti
use IPaC\GPA\RisorsaDigitale\Client\Models\CreaRisorsaDigitaleRequestDTO;
use IPaC\GPA\OggettoDigitale\Client\Models\CreaOggettoDigitaleRequestDTO;


class OggettoDigitaleLifecycleTest extends AuthenticatedTest
{
    /**
     * Test per il ciclo di vita di un Oggetto Digitale (senza bitstream).
     * 1. Crea una Risorsa Digitale padre.
     * 2. Crea un Oggetto Digitale al suo interno.
     * 3. Lo elimina.
     * 4. Verifica che non esista più.
     */
    public function testCreateDeleteAndVerifyAbsence(): void
    {
        $risorsaId = null;
        $oggettoId = null;

        try {
            // --- PREREQUISITO: Crea una Risorsa Digitale padre ---
            echo "\nPrerequisito: Creazione Risorsa Digitale...";
            $risorsaBody = new CreaRisorsaDigitaleRequestDTO();
            $risorsaBody->setTitolo('Test Contenitore per Oggetto ' . uniqid());
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

            // --- 1. CREA UN OGGETTO DIGITALE X ---
            echo "\nStep 1: Creazione Oggetto Digitale...";
            $oggettoBody = new CreaOggettoDigitaleRequestDTO();
            $oggettoBody->setTitolo('Test Oggetto Semplice ' . uniqid());
            $oggettoBody->setTipologiaMedia('immagine');
            
            // Crea l'oggetto nel contesto della risorsa
            $nuovoOggetto = $this->oggettoDigitaleClient->api()->v1()->gpa()->risorseDigitali()->byChiaveRisorsaDigitaleISPC($risorsaId)->oggettiDigitali()->post($oggettoBody)->wait();
            $oggettoId = $nuovoOggetto->getChiaveOggettoDigitaleISPC();

            $this->assertNotEmpty($oggettoId, "La creazione dell'Oggetto Digitale dovrebbe restituire un ID.");
            echo " OK (ID Oggetto: $oggettoId)";

            // --- 2. ELIMINA L'OGGETTO DIGITALE X ---
            echo "\nStep 2: Eliminazione Oggetto Digitale...";
            $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettiDigitali()->byChiaveOggettoDigitaleISPC($oggettoId)->delete()->wait();
            echo " OK";

            // --- 3. ASSERISCE CHE L'OGGETTO X NON ESISTE PIÙ ---
            echo "\nStep 3: Verifica non esistenza Oggetto Digitale...";
            $this->expectException(ClientException::class);
            $this->expectExceptionCode(404); // Ci aspettiamo un "Not Found"

            // Questa chiamata deve fallire con un errore 404
            $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettiDigitali()->byChiaveOggettoDigitaleISPC($oggettoId)->get()->wait();

        } finally {
            // BLOCCO DI PULIZIA: Elimina la risorsa digitale, che a sua volta
            // dovrebbe eliminare tutti gli oggetti digitali contenuti.
            if ($risorsaId) {
                try {
                    $this->risorsaDigitaleClient->api()->v1()->gpa()->risorseDigitali()->byChiaveRisorsaDigitaleISPCId($risorsaId)->delete(function($config){
                        $config->queryParameters->forceFiglia = true;
                    })->wait();
                    echo "\nPulizia: Risorsa $risorsaId e i suoi contenuti sono stati eliminati.";
                } catch (\Exception $e) {
                    echo "\nPulizia FALLITA per Risorsa $risorsaId: " . $e->getMessage();
                }
            }
        }
    }
}