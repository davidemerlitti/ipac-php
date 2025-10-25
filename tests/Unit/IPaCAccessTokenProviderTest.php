<?php
namespace Tests\Unit;

// Classi di base per il testing
use PHPUnit\Framework\TestCase;

// Classi necessarie dalla tua applicazione e da Kiota/Guzzle
use App\IPaCAccessTokenProvider; // <-- AGGIORNA se il tuo namespace è diverso!
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use Http\Promise\FulfilledPromise;
use Http\Promise\RejectedPromise;

/**
 * Test unitari per la classe IPaCAccessTokenProvider.
 * Questi test simulano le risposte del server per verificare la logica della classe
 * senza effettuare vere chiamate di rete.
 */
class IPaCAccessTokenProviderTest extends TestCase
{
    private string $fakeTokenEndpoint = 'https://fake-auth-server.com/token';
    private string $fakeClientId = 'test_client_id';
    private string $fakeClientSecret = 'test_client_secret';
    private string $fakeApiUrl = 'https://api.ipac.preprod/v1/some-resource';

    /**
     * Test: Verifica la corretta ricezione di un token in caso di successo (200 OK).
     */
    public function testGetAuthorizationTokenAsyncSuccessfullyRetrievesToken(): void
    {
        // ARRANGE: Prepara una risposta HTTP fittizia di successo
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'mocked-access-token-123',
                'expires_in' => 3600
            ]))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockHttpClient = new Client(['handler' => $handlerStack]);

        // ACT: Esegui l'azione da testare
        $tokenProvider = new IPaCAccessTokenProvider(
            $this->fakeClientId,
            $this->fakeClientSecret,
            $this->fakeTokenEndpoint,
            $mockHttpClient // Inietta il client fittizio
        );
        $promise = $tokenProvider->getAuthorizationTokenAsync($this->fakeApiUrl);

        // ASSERT: Verifica i risultati
        $this->assertInstanceOf(FulfilledPromise::class, $promise, "Dovrebbe restituire una FulfilledPromise in caso di successo.");

        // Risolvi la promise per ottenere il valore e verificarlo
        $token = $promise->wait();
        $this->assertEquals('mocked-access-token-123', $token);
    }

    /**
     * Test: Verifica che venga restituita una RejectedPromise in caso di errore del server (es. 401).
     */
    public function testGetAuthorizationTokenAsyncReturnsRejectedPromiseOnFailure(): void
    {
        // ARRANGE: Prepara una risposta di errore 401 Unauthorized
        $mock = new MockHandler([
            // Guzzle lancia un'eccezione per risposte 4xx/5xx, quindi la simuliamo
            new ClientException(
                'Error Communicating with Server',
                new Request('POST', $this->fakeTokenEndpoint),
                new Response(401, [], json_encode(['error' => 'invalid_client']))
            )
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockHttpClient = new Client(['handler' => $handlerStack]);
        
        // ACT
        $tokenProvider = new IPaCAccessTokenProvider(
            $this->fakeClientId,
            $this->fakeClientSecret,
            $this->fakeTokenEndpoint,
            $mockHttpClient
        );
        $promise = $tokenProvider->getAuthorizationTokenAsync($this->fakeApiUrl);

        // ASSERT
        $this->assertInstanceOf(RejectedPromise::class, $promise, "Dovrebbe restituire una RejectedPromise in caso di fallimento.");
        
        // Verifica che risolvendo la promise si ottenga l'eccezione corretta
        $this->expectException(ClientException::class);
        $promise->wait();
    }
    
    /**
     * Test: Verifica che il token venga recuperato dalla cache alla seconda chiamata.
     */
    public function testGetAuthorizationTokenAsyncReturnsTokenFromCacheOnSecondCall(): void
    {
        // ARRANGE: Prepara un mock handler con UNA SOLA risposta.
        // Se venisse fatta una seconda chiamata HTTP, il test fallirebbe.
        $mock = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'cached-token-456', 'expires_in' => 3600]))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockHttpClient = new Client(['handler' => $handlerStack]);
        
        $tokenProvider = new IPaCAccessTokenProvider(
            $this->fakeClientId,
            $this->fakeClientSecret,
            $this->fakeTokenEndpoint,
            $mockHttpClient
        );

        // ACT 1: Prima chiamata per popolare la cache
        $promise1 = $tokenProvider->getAuthorizationTokenAsync($this->fakeApiUrl);
        $token1 = $promise1->wait();
        
        // ASSERT 1: Verifica che la prima chiamata abbia funzionato
        $this->assertEquals('cached-token-456', $token1);

        // ACT 2: Seconda chiamata, che dovrebbe usare la cache
        $promise2 = $tokenProvider->getAuthorizationTokenAsync($this->fakeApiUrl);
        $token2 = $promise2->wait();

        // ASSERT 2: Verifica che il token sia lo stesso e che la promise sia fulfilled
        $this->assertInstanceOf(FulfilledPromise::class, $promise2);
        $this->assertEquals('cached-token-456', $token2, "Il secondo token dovrebbe essere identico e provenire dalla cache.");
        
        // La prova finale è che il test si conclude senza errori.
        // Se il provider avesse tentato una seconda chiamata di rete, Guzzle
        // avrebbe lanciato un'eccezione perché la coda del MockHandler era vuota.
    }
}