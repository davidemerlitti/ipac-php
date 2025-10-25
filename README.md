# I.PaC PHP Client Suite

Suite di client PHP per le API della piattaforma [I.PaC](https://ispc-preprod.prod.os01.ocp.cineca.it/docs), con generazione automatica del codice tramite Kiota, esempi di utilizzo, strumenti di utilità e una suite di test di integrazione.

## Funzionalità

- **Generazione Automatica:** Uno script PHP per scaricare tutte le definizioni OpenAPI e generare client PHP fortemente tipizzati per ogni API.
- **Autenticazione Gestita:** Un provider di token per gestire il flusso di autenticazione OAuth2 (client credentials).
- **Test di Integrazione:** Una suite di test PHPUnit per verificare il corretto funzionamento delle chiamate API reali.
- **Esempi e Tool:** Script di esempio per mostrare come utilizzare i client e tool per esplorare le API.

## Prerequisiti

- PHP 8.2+
- [Composer](https://getcomposer.org/)
- [Kiota](https://github.com/microsoft/kiota/releases): scaricare l'eseguibile per il proprio sistema operativo e posizionarlo in una cartella `kiota/` nella root del progetto.

## Installazione e Setup

1.  **Clonare il repository:**
    ```bash
    git clone https://github.com/TUO_USERNAME/ipac-php.git
    cd ipac-php
    ```

2.  **Installare le dipendenze PHP:**
    ```bash
    composer install
    ```

3.  **Generare i client API:**
    Questo script è il cuore del progetto. Scarica le definizioni e genera tutto il codice sorgente dei client nella cartella `src/IPaC`, creando un sotto-namespace per ogni gruppo di API (es. `GPA`, `CAP`, ecc.).
    ```bash
    php generate-clients.php
    ```

## Utilizzo

### Esempi

La cartella `Examples/` contiene script pronti all'uso che mostrano come autenticarsi ed eseguire chiamate comuni.

**Esempio: Lettura di una Collezione**
Lo script `Examples/gpa-collezioni-byUuidCollezione.php` mostra come recuperare una specifica collezione tramite il suo UUID.

Per eseguirlo, imposta le tue credenziali come variabili d'ambiente e lancia lo script:
```bash
export IPAC_CLIENT_ID="il_tuo_client_id"
export IPAC_CLIENT_SECRET="il_tuo_client_secret"
php Examples/gpa-collezioni-byUuidCollezione.php```

### Strumenti di Utilità

-   **`list-apis.php`**: Uno script a riga di comando che contatta l'endpoint I.PaC, scarica l'elenco di tutte le API disponibili e ne stampa i dettagli principali (titolo, versione, URL della specifica). Utile per avere una panoramica aggiornata dell'offerta API.
    ```bash
    php list-apis.php
    ```

## Testing

Il progetto include una suite di test di integrazione per verificare le operazioni CRUD di base.

1.  **Configurare le credenziali:**
    Copia il file di configurazione di esempio e inserisci le tue credenziali reali.
    ```bash
    cp phpunit.xml.dist phpunit.xml
    ```
2.  **Modifica `phpunit.xml`** con il tuo `client_id` e `client_secret`.

3.  **Eseguire i test:**
    ```bash
    vendor/bin/phpunit --testsuite Integration
    ```