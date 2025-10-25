<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\IPaCAccessTokenProvider; // <-- Assicurati che il namespace sia corretto

class AuthenticationTest extends TestCase
{
    /**
     * @var IPaCAccessTokenProvider
     */
    private $tokenProvider;

    /**
     * Questo metodo viene eseguito prima di ogni test in questa classe.
     */
    protected function setUp(): void
    {
        // Leggiamo le credenziali dal file phpunit.xml.dist
        $tokenEndpoint = getenv('IPAC_TOKEN_ENDPOINT');
        $clientId = getenv('IPAC_CLIENT_ID');
        $clientSecret = getenv('IPAC_CLIENT_SECRET');

        // Se le credenziali non sono configurate, salta i test di integrazione
        if (!$tokenEndpoint || !$clientId || !$clientSecret) {
            $this->markTestSkipped('Le credenziali per i test di integrazione non sono state configurate nel file phpunit.xml.dist');
        }

        // Inizializza il provider con i dati reali
        $this->tokenProvider = new IPaCAccessTokenProvider($clientId, $clientSecret, $tokenEndpoint);
    }

    /**
     * Test Fase 1: Verifica che sia possibile ottenere un token di accesso valido.
     * Questo test effettuerà una VERA chiamata HTTP all'endpoint di autenticazione.
     */
    public function testCanRetrieveValidAccessToken()
    {
        // Esegui la chiamata per ottenere la "promise"
        $promise = $this->tokenProvider->getAuthorizationTokenAsync('https://irrilevante-per-questo-test.com');
        
        // Attendi che la promise sia risolta per ottenere il token
        $accessToken = $promise->wait();

        // ASSERT: Verifichiamo che il risultato sia quello che ci aspettiamo
        $this->assertIsString($accessToken, "Il token di accesso dovrebbe essere una stringa.");
        $this->assertNotEmpty($accessToken, "Il token di accesso non può essere vuoto.");
        
        // Puoi anche aggiungere un output per vederlo durante l'esecuzione del test
        echo "\nToken ottenuto con successo: " . substr($accessToken, 0, 15) . "...\n";
    }
}

