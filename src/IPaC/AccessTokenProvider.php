<?php
namespace IPaC;

use Microsoft\Kiota\Abstractions\Authentication\AccessTokenProvider as KiotaAccessTokenProvider;
use Microsoft\Kiota\Abstractions\Authentication\AllowedHostsValidator;
use GuzzleHttp\Client;
use Http\Promise\Promise;
use Http\Promise\FulfilledPromise;
use Http\Promise\RejectedPromise;

class AccessTokenProvider implements KiotaAccessTokenProvider {

    private string $clientId;
    private string $clientSecret;
    private string $tokenEndpoint;
    private ?string $cachedAccessToken = null;
    private ?int $expiresAt = null;
    private AllowedHostsValidator $allowedHostsValidator;
    private Client $guzzleClient;

    public function __construct(string $clientId, string $clientSecret, 
      string $tokenEndpoint, ?GuzzleHttp\ClientInterface $httpClient = null) { // Accetta un client opzionale) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tokenEndpoint = $tokenEndpoint;
        
        $parsedUrl = parse_url($tokenEndpoint);
        $this->allowedHostsValidator = new AllowedHostsValidator([$parsedUrl['host'] ?? '']);
        
        $this->guzzleClient = $httpClient ?? new Client([
            'verify' => false,
            'headers' => ['Accept' => 'application/json']
        ]);
    }

    public function getAuthorizationTokenAsync(string $url, array $additionalAuthenticationContext = []): Promise {
        if ($this->cachedAccessToken && $this->expiresAt && time() < $this->expiresAt) {
            return new FulfilledPromise($this->cachedAccessToken);
        }

        try {
            $response = $this->guzzleClient->post($this->tokenEndpoint, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'openid profile'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token'])) {
                $this->cachedAccessToken = $data['access_token'];
                $this->expiresAt = time() + ($data['expires_in'] ?? 3600) - 60;
                return new FulfilledPromise($this->cachedAccessToken);
            }

            throw new Exception("Token di accesso non trovato nella risposta del server.");

        } catch (Exception $e) {
            return new RejectedPromise($e);
        }
    }

    public function getAllowedHostsValidator(): AllowedHostsValidator {
        return $this->allowedHostsValidator;
    }
    
    public function getScopes(): array { return []; }
    public function getAccessToken(?array $scopes = []): ?string { throw new \BadMethodCallException('Questo metodo non deve essere chiamato direttamente in un contesto asincrono.'); }
}