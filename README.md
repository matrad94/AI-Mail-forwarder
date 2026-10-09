# Email Router PoC

Mikroserwis do inteligentnego routingu wiadomości e-mail. Klasyfikuje zgłoszenia za pomocą lokalnego modelu Ollama i przekazuje je do odpowiedniego działu.

## Najważniejsze funkcje i architektura

- **API:** Mikroserwis w PHP 8.3; `public/index.php` obsługuje routing HTTP bez frameworka i bazy danych.
- **Routing AI:** Agent Neuron AI korzysta z lokalnego modelu Ollama `qwen2.5:3b` i wywoływania narzędzi, aby wybrać dział i wysłać wiadomość.
- **Bezpieczeństwo:** `SecurityGuardAgent` przed routingiem sprawdza wiadomości pod kątem prób Prompt Injection. Walidację można wyłączyć przez `ENABLE_SECURITY_GUARD=false`; domyślnie jest włączona.
- **Pilne wiadomości:** Wykrycie słów oznaczających pilność dodaje prefiks `[URGENT]` do tematu e-maila.
- **Testowanie poczty:** Mailpit przechwytuje wiadomości lokalnie. Adres nadawcy jest ustawiany jako `Reply-To`; wiadomości nie są wysyłane do zewnętrznych odbiorców.
- **Reguły routingu:** Edytowalne reguły kategoryzacji znajdują się w `config/rules.txt`; Agent odczytuje plik przy każdym nowym żądaniu, więc zmiany reguł nie wymagają ponownego budowania obrazu.
- **Lokalna dokumentacja API:** Pliki Swagger UI znajdują się w `public/swagger/assets/`; interfejs działa bez pobierania JavaScriptu ani CSS z CDN.
- **Dozwoleni odbiorcy:** `it@example.com`, `help-desk@example.com`, `kadry@example.com`, `human-resources@example.com`; `other@example.com` jest adresem awaryjnym.

## Szybki start

**Wymagania:** Docker z Docker Compose, dostęp do internetu przy pierwszym pobraniu obrazów, zależności Composer i modelu oraz wolne miejsce na model językowy.

Uruchom w katalogu projektu:

```bash
docker compose up -d --build
```

Compose buduje API, uruchamia Ollamę i Mailpit, pobiera model `qwen2.5:3b`, a następnie uruchamia API po inicjalizacji modelu. Pierwsze uruchomienie może potrwać kilka minut.

| Usługa | Adres |
| --- | --- |
| API | `http://localhost:8000` |
| Dokumentacja Swagger / OpenAPI | [http://localhost:8000/api/v1/docs](http://localhost:8000/api/v1/docs) |
| Skrzynka Mailpit | [http://localhost:8025](http://localhost:8025) |
| Ollama | `http://localhost:11434` |

## Korzystanie z API

### `POST /api/v1/dispatch`

Wyślij żądanie JSON z adresem nadawcy i treścią wiadomości:

```json
{
  "email": "nadawca@example.com",
  "message": "Treść zgłoszenia"
}
```

- `email` — poprawny adres nadawcy; zostanie użyty jako `Reply-To` przekazywanej wiadomości.
- `message` — dowolna treść do sprawdzenia, sklasyfikowania i przekierowania.
- `200 OK` — żądanie zostało obsłużone; odpowiedź zawiera pole `message` ze statusem agenta.
- `400 Bad Request` — niepoprawny JSON lub pola, nieprawidłowy adres nadawcy albo próba Prompt Injection odrzucona przez włączony moduł bezpieczeństwa.

### Przykłady cURL

Przykłady używają adresu `nadawca@example.com`. W razie potrzeby zastąp go poprawnym adresem testowym.

#### Problem sprzętowy — IT

```bash
curl -X POST "http://localhost:8000/api/v1/dispatch" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "nadawca@example.com",
    "message": "Monitor przy moim stanowisku nie wyświetla obrazu, mimo że komputer jest włączony."
  }'
```

Oczekiwany odbiorca: `it@example.com`.

#### Zgłoszenie kadrowe

```bash
curl -X POST "http://localhost:8000/api/v1/dispatch" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "nadawca@example.com",
    "message": "Proszę o informację, jak złożyć wniosek urlopowy i ile dni urlopu mi pozostało."
  }'
```

Oczekiwany odbiorca: `kadry@example.com` lub `human-resources@example.com`.

#### Nierozpoznane zgłoszenie

```bash
curl -X POST "http://localhost:8000/api/v1/dispatch" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "nadawca@example.com",
    "message": "Czy ktoś wie, do kogo należy zostawiony w recepcji niebieski parasol?"
  }'
```

Oczekiwany odbiorca awaryjny: `other@example.com`.

Klasyfikacja modelu nie jest deterministyczna. Rzeczywistego odbiorcę i nagłówek `Reply-To` można sprawdzić w Mailpit.
