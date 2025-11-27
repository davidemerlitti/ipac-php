<?php

namespace Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Microsoft\Kiota\Http\GuzzleRequestAdapter;
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use IPaC\AccessTokenProvider;
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

    $tokenProvider = new AccessTokenProvider($clientId, $clientSecret, $tokenEndpoint);
    $authProvider = new BaseBearerTokenAuthenticationProvider($tokenProvider);

    // Crea un middleware a livello di risposta per poter visualizzare il body
    // in caso di errore 40x
    $handlerStack = HandlerStack::create();

    $handlerStack->push(Middleware::mapResponse(function (ResponseInterface $response) {
      if ($response->getStatusCode() >= 400) {
        echo "\n---------------- DEBUG ERROR BODY ----------------\n";
        echo "Status Code: " . $response->getStatusCode() . "\n";

        $bodyStream = $response->getBody();
        $contents = (string) $bodyStream; // Questo legge tutto lo stream

        echo "Body: " . $contents . "\n";
        echo "--------------------------------------------------\n";

        if ($bodyStream->isSeekable()) {
          $bodyStream->rewind();
        }
      }
      return $response;
    }));

    $customGuzzleClient = new GuzzleHttpClient(['handler' => $handlerStack]);
    
    self::$staticCapClient = new CapClient(new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient));
    $this->capClient = self::$staticCapClient;

    self::$staticRisorsaDigitaleClient = new RisorsaDigitaleClient(new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient));
    $this->risorsaDigitaleClient = self::$staticRisorsaDigitaleClient;

    self::$staticOggettoDigitaleClient = new OggettoDigitaleClient(new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient));
    $this->oggettoDigitaleClient = self::$staticOggettoDigitaleClient;

    self::$staticGestioneBitstreamClient = new GestioneBitstreamClient(new GuzzleRequestAdapter($authProvider, null, null, $customGuzzleClient));
    $this->gestioneBitstreamClient = self::$staticGestioneBitstreamClient;

    // --- Creazione Contesto di Sicurezza ---
    echo "\nCreazione del Contesto di Sicurezza...";

    $sistemaUUID = getenv('sistemaUUID');
    $enteAderenteUUID = getenv('enteAderenteUUID');
    $tenancyUUID = getenv('tenancyUUID');
    $labelDescrittivaUtente = getenv('labelDescrittivaUtente');
    $idUtente = getenv('idUtente');
    $codiceRuolo = getenv('codiceRuolo');

    try {
      $autenticazioneRequest = new AutenticazionePostRequestDTO();

      $autenticazioneRequest->setSistemaUUID($sistemaUUID);
      $autenticazioneRequest->setEnteAderenteUUID($enteAderenteUUID);
      $autenticazioneRequest->setTenancyUUID($tenancyUUID);
      $autenticazioneRequest->setLabelDescrittivaUtente($labelDescrittivaUtente);
      $autenticazioneRequest->setIdUtente($idUtente);
      $autenticazioneRequest->setCodiceRuolo($codiceRuolo);

      self::$staticCapClient->api()->v1()->cap()->autorizzazionesoggettosistema()->predisponeAutenticazione()->post($autenticazioneRequest)->wait();

      echo " OK";
    } catch (\Exception $e) {
      $errorMessage = $e->getMessage();
      $this->fail("Impossibile impostare il contesto di sicurezza: " . $errorMessage);
    }
  }
}