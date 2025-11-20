<?php

namespace Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use App\IPaCAccessTokenProvider;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;

// Importa i client API
use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Client as CapClient;
use IPaC\GPA\RisorsaDigitale\Client\Client as RisorsaDigitaleClient;
use IPaC\GPA\OggettoDigitale\Client\Client as OggettoDigitaleClient;
use IPaC\GPA\GestioneBitstream\Client\Client as GestioneBitstreamClient;

// Importa il DTO CORRETTO per il contesto di sicurezza
use IPaC\CAP\AutorizzazioneSoggettoSistema\Client\Models\AutenticazionePostRequestDTO;

abstract class AuthenticatedTest extends TestCase
{
    protected GuzzleRequestAdapter $requestAdapter;
    
    // Usiamo proprietà statiche per "mettere in cache" i client già inizializzati
    private static ?CapClient $staticCapClient = null;
    private static ?RisorsaDigitaleClient $staticRisorsaDigitaleClient = null;
    private static ?OggettoDigitaleClient $staticOggettoDigitaleClient = null;
    private static ?GestioneBitstreamClient $staticGestioneBitstreamClient = null;

    // Usiamo proprietà di istanza che i test useranno
    protected CapClient $capClient;
    protected RisorsaDigitaleClient $risorsaDigitaleClient;
    protected OggettoDigitaleClient $oggettoDigitaleClient;
    protected GestioneBitstreamClient $gestioneBitstreamClient;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Se i client sono già inizializzati, non fare nulla.
        if (self::$staticCapClient !== null) {
            $this->capClient = self::$staticCapClient;
            $this->risorsaDigitaleClient = self::$staticRisorsaDigitaleClient;
            $this->oggettoDigitaleClient = self::$staticOggettoDigitaleClient;
            $this->gestioneBitstreamClient = self::$staticGestioneBitstreamClient;
            return;
        }
        
        echo "\n--- ESEGUO SETUP GLOBALE (UNA TANTUM) ---\n";
        
        $tokenEndpoint = getenv('IPAC_TOKEN_ENDPOINT');
        $clientId = getenv('IPAC_CLIENT_ID');
        $clientSecret = getenv('IPAC_CLIENT_SECRET');

        if (!$tokenEndpoint || !$clientId || !$clientSecret) {
            $this->markTestSkipped('Le credenziali per i test di integrazione non sono configurate.');
        }

        // --- FASE 1: Autenticazione ---
        $tokenProvider = new IPaCAccessTokenProvider($clientId, $clientSecret, $tokenEndpoint);
        $authProvider = new BaseBearerTokenAuthenticationProvider($tokenProvider);
        
        self::$staticCapClient = new CapClient(new GuzzleRequestAdapter($authProvider, null, null, new Client()));
        $this->capClient = self::$staticCapClient;
        
        self::$staticRisorsaDigitaleClient = new RisorsaDigitaleClient(new GuzzleRequestAdapter($authProvider, null, null, new Client()));
        $this->risorsaDigitaleClient = self::$staticRisorsaDigitaleClient;
        
        self::$staticOggettoDigitaleClient = new OggettoDigitaleClient(new GuzzleRequestAdapter($authProvider, null, null, new Client()));
        $this->oggettoDigitaleClient = self::$staticOggettoDigitaleClient;
        
        self::$staticGestioneBitstreamClient = new GestioneBitstreamClient(new GuzzleRequestAdapter($authProvider, null, null, new Client()));
        $this->gestioneBitstreamClient = self::$staticGestioneBitstreamClient;
        
        // --- FASE 2: Creazione Contesto di Sicurezza ---
        echo "\nStep 1.5: Impostazione del Contesto di Sicurezza...";
        
        $securityContextPayload = [
            "sistemaUUID" => "24BC937BB382B28FE0630204FE0AA7DF",
            "enteAderenteUUID" => "438F25ED07B8A720E0630204FE0AD634",
            "tenancyUUID" => "438F25ED07BAA720E0630204FE0AD634",
            "labelDescrittivaUtente" => "Davide Merlitti (Test)",
            "idUtente" => "1",
            "codiceRuolo" => "ADMICCD"
        ];
        
        try {
            // Usa il DTO corretto
            $securityDto = new AutenticazionePostRequestDTO();
            
            // Popola il DTO usando i metodi set corretti
            $securityDto->setSistemaUUID($securityContextPayload['sistemaUUID']);
            $securityDto->setEnteAderenteUUID($securityContextPayload['enteAderenteUUID']);
            $securityDto->setTenancyUUID($securityContextPayload['tenancyUUID']);
            $securityDto->setLabelDescrittivaUtente($securityContextPayload['labelDescrittivaUtente']);
            $securityDto->setIdUtente($securityContextPayload['idUtente']);
            $securityDto->setCodiceRuolo($securityContextPayload['codiceRuolo']);
            
            // Esegui la chiamata POST all'endpoint corretto
            self::$staticCapClient->api()->v1()->cap()->autorizzazionesoggettosistema()->predisponeAutenticazione()->post($securityDto)->wait();
            
            echo " OK";

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if ($e->getPrevious() instanceof \GuzzleHttp\Exception\RequestException && $e->getPrevious()->hasResponse()) {
                $errorMessage = $e->getPrevious()->getResponse()->getBody()->getContents();
            }
            $this->fail("Impossibile impostare il contesto di sicurezza: " . $errorMessage);
        }
    }
}