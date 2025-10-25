<?php

namespace Tests\Integration;

use GuzzleHttp\Exception\ClientException;
// Importa i DTO (Data Transfer Object) necessari generati da Kiota
use IPaC\GPA\OggettoDigitale\Client\Models\OggettoDigitalePostRequestBody;

class OggettoDigitaleLifecycleTest extends AuthenticatedTest
{
    public function testCreateDeleteAndVerifyAbsence(): void
    {
        $oggettoId = null;

        try {
            // --- 1. CREA UN OGGETTO DIGITALE X ---
            echo "\nStep 1: Creazione Oggetto Digitale...";
            $requestBody = new OggettoDigitalePostRequestBody();
            // ADATTA: Imposta le proprietà necessarie per la creazione.
            // I nomi dei metodi (es. setNome) dipendono da come Kiota li ha generati.
            $requestBody->setNome('Test Oggetto ' . uniqid());
            $requestBody->setTipo('immagine'); 
            
            $nuovoOggetto = $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->post($requestBody)->wait();
            $oggettoId = $nuovoOggetto->getId(); // ADATTA: a seconda della struttura della risposta

            $this->assertNotEmpty($oggettoId, "La creazione dovrebbe restituire un ID.");
            echo " OK (ID: $oggettoId)";

            // --- 2. ELIMINA L'OGGETTO DIGITALE X ---
            echo "\nStep 2: Eliminazione Oggetto Digitale...";
            $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->byOggettoDigitaleId($oggettoId)->delete()->wait();
            echo " OK";

            // --- 3. ASSERISCE CHE L'OGGETTO X NON ESISTE PIÙ ---
            echo "\nStep 3: Verifica non esistenza...";
            $this->expectException(ClientException::class);
            $this->expectExceptionCode(404); // Ci aspettiamo un "Not Found"

            // Questa chiamata deve fallire con un errore 404
            $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->byOggettoDigitaleId($oggettoId)->get()->wait();

        } finally {
            // BLOCCO DI PULIZIA: Assicuriamoci che l'oggetto venga eliminato
            // anche se uno degli assert nel mezzo del test fallisce.
            if ($oggettoId) {
                try {
                    $this->oggettoDigitaleClient->api()->v1()->gpa()->oggettoDigitale()->byOggettoDigitaleId($oggettoId)->delete()->wait();
                    echo "\nPulizia: Oggetto $oggettoId eliminato con successo.";
                } catch (\Exception $e) {
                    // Ignora l'errore (potrebbe essere già stato cancellato, causando un 404)
                    echo "\nPulizia: Oggetto $oggettoId non trovato o già eliminato.";
                }
            }
        }
    }
}