<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\ClientException;
use IPaC\GPA\OggettoDigitale\Client\Models\OggettoDigitalePostRequestBody;
// ADATTA: Importa i DTO per il bitstream e la finalizzazione
// use IPaC\GPA\GestioneBitstream\Client\Models\BitstreamPostRequestBody; 

class BitstreamLifecycleTest extends AuthenticatedTest
{
    public function testFullLifecycleWithBitstream(): void
    {
        $oggettoId = null;
        $bitstreamId = null;

        try {
            // --- 1. Crea Oggetto Digitale X ---
            echo "\nStep 1: Creazione Oggetto Digitale...";
            $objRequestBody = new OggettoDigitalePostRequestBody();
            $objRequestBody->setNome('Test con Bitstream ' . uniqid());
            $objRequestBody->setTipo('documento');
            $nuovoOggetto = $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->post($objRequestBody)->wait();
            $oggettoId = $nuovoOggetto->getId();
            $this->assertNotEmpty($oggettoId, "ID oggetto non può essere vuoto.");
            echo " OK (ID: $oggettoId)";
            
            // --- 2. Crea Bitstream Y dentro l'oggetto X ---
            echo "\nStep 2: Creazione Bitstream...";
            // ADATTA: La creazione di un bitstream solitamente richiede un multipart/form-data.
            // Kiota dovrebbe gestire la creazione della richiesta corretta.
            // Qui creiamo un "file" fittizio in memoria.
            $dummyFileContent = 'Questo è il contenuto del mio file di test.';
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $dummyFileContent);
            rewind($stream);

            // ADATTA: La chiamata esatta e il DTO dipendono dal client generato.
            // Potrebbe essere necessario passare lo stream e l'ID dell'oggetto.
            // $bitstreamRequestBody = new BitstreamPostRequestBody();
            // $bitstreamRequestBody->setFile($stream);
            // $nuovoBitstream = $this->gestioneBitstreamClient->...->post($bitstreamRequestBody)->wait();
            // $bitstreamId = $nuovoBitstream->getId();
            // $this->assertNotEmpty($bitstreamId, "ID bitstream non può essere vuoto.");
            // echo " OK (ID: $bitstreamId)";

            // --- 3. Finalizza l'Oggetto Digitale X ---
            echo "\nStep 3: Finalizzazione Oggetto Digitale...";
            // ADATTA: Chiama l'endpoint di finalizzazione.
            // $this->oggettoDigitaleClient->...->byOggettoDigitaleId($oggettoId)->finalize()->post()->wait();
            echo " OK";

            // --- 4 & 5. Tenta di eliminare l'oggetto e asserisce che fallisca ---
            echo "\nStep 4: Tentativo di eliminazione (atteso fallimento)...";
            try {
                $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->byOggettoDigitaleId($oggettoId)->delete()->wait();
                $this->fail("L'eliminazione doveva fallire perché l'oggetto contiene bitstream.");
            } catch (ClientException $e) {
                // ADATTA: 409 Conflict è un codice comune per questo scenario, ma potrebbe essere 400.
                $this->assertEquals(409, $e->getCode(), "Dovrebbe restituire un errore 'Conflict' (409).");
                echo " OK (Fallito come previsto con codice 409)";
            }
            
            // --- 6. Elimina il Bitstream Y ---
            echo "\nStep 6: Eliminazione Bitstream...";
            // ADATTA: Chiama l'endpoint di eliminazione del bitstream
            // $this->gestioneBitstreamClient->...->byBitstreamId($bitstreamId)->delete()->wait();
            echo " OK";

            // --- 7. Elimina l'Oggetto Digitale X (ora dovrebbe funzionare) ---
            echo "\nStep 7: Eliminazione finale Oggetto Digitale...";
            $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->byOggettoDigitaleId($oggettoId)->delete()->wait();
            echo " OK";

            // --- 8. Asserisce che l'oggetto non esista più ---
            echo "\nStep 8: Verifica finale non esistenza...";
            $this->expectException(ClientException::class);
            $this->expectExceptionCode(404);
            $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->byOggettoDigitaleId($oggettoId)->get()->wait();

        } finally {
            // Blocco di pulizia in ordine inverso
            echo "\n--- Blocco di Pulizia Finale ---";
            if ($bitstreamId) {
                try {
                    // $this->gestioneBitstreamClient->...->byBitstreamId($bitstreamId)->delete()->wait();
                } catch (\Exception $e) { /* Ignora */ }
            }
            if ($oggettoId) {
                try {
                    $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->byOggettoDigitaleId($oggettoId)->delete()->wait();
                } catch (\Exception $e) { /* Ignora */ }
            }
        }
    }
}
