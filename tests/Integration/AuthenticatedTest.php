<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use App\IPaCAccessTokenProvider; // Il tuo provider di token

// Importa i client API generati da Kiota
use IPaC\GPA\OggettoDigitale\Client as OggettoDigitaleClient;
use IPaC\GPA\GestioneBitstream\Client as GestioneBitstreamClient;
// ... importa altri client di cui potresti aver bisogno ...

/**
 * Classe base per tutti i test di integrazione che richiedono autenticazione.
 * Prepara automaticamente un RequestAdapter e i client API prima di ogni test.
 */
abstract class AuthenticatedTest extends TestCase
{
    protected GuzzleRequestAdapter $requestAdapter;
    
    // Proprietà per accedere facilmente ai client API nei test
    protected OggettoDigitaleClient $oggettoDigitaleClient;
    protected GestioneBitstreamClient $gestioneBitstreamClient;
    // ... aggiungi altre proprietà per altri client ...

    /**
     * Questo metodo viene eseguito da PHPUnit prima di ogni singolo metodo di test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Leggi le credenziali dal file phpunit.xml.dist
        $tokenEndpoint = getenv('IPAC_TOKEN_ENDPOINT');
        $clientId = getenv('IPAC_CLIENT_ID');
        $clientSecret = getenv('IPAC_CLIENT_SECRET');

        if (!$tokenEndpoint || !$clientId || !$clientSecret) {
            $this->markTestSkipped('Le credenziali per i test di integrazione non sono configurate.');
        }

        // 2. Prepara l'autenticazione
        $tokenProvider = new IPaCAccessTokenProvider($clientId, $clientSecret, $tokenEndpoint);
        $this->requestAdapter = new GuzzleRequestAdapter($tokenProvider);

        // 3. Inizializza i client API con l'adapter autenticato
        //    Ora ogni chiamata fatta da questi client includerà automaticamente il token Bearer.
        $this->oggettoDigitaleClient = new OggettoDigitaleClient($this->requestAdapter);
        $this->gestioneBitstreamClient = new GestioneBitstreamClient($this->requestAdapter);
        // ... istanzia altri client qui ...
    }
}

