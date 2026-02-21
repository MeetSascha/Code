# Projekt-Dokumentation: lindlar-anno-dazumal.de

**Stand:** Februar 2026 — zuletzt aktualisiert: Slug-Lookup & SearchView implementiert  
**Zielgruppe:** Senior Full-Stack Entwickler  
**Hosting:** All-Inkl (Shared Hosting) — PHP 8.x, MariaDB, Apache, FTP  
**Verfügbar:** PHP, MariaDB, Apache  
**Nicht verfügbar:** Node.js (Server-seitig), Docker, Python

---

## 1. Architektur-Übersicht

```
┌─────────────────────────────────────────────────────┐
│                  Browser (Client)                   │
│         Vue 3 SPA (statisch ausgeliefert)           │
└────────────────────┬────────────────────────────────┘
                     │ fetch() / REST
┌────────────────────▼────────────────────────────────┐
│              Apache (All-Inkl)                      │
│  /public_html/          → Vue SPA (dist/)           │
│  /public_html/api/      → PHP REST-API              │
│  /public_html/uploads/  → Bilddateien               │
└────────────────────┬────────────────────────────────┘
                     │ PDO
┌────────────────────▼────────────────────────────────┐
│                   MariaDB                           │
└─────────────────────────────────────────────────────┘
```

**Designentscheidungen:**
- Kein Node.js-Server → PHP als REST-API-Schicht
- Vue 3 SPA wird lokal gebaut (`npm run build`) und als statische Dateien per FTP deployed
- Stateless JWT-Auth (kein Session-Handling serverseitig)
- Kein externes JWT-Framework → HMAC-SHA256 nativ in PHP implementiert
- Kein ImageMagick nötig → Thumbnails via PHP GD
- Tailwind CSS v4 mit `@tailwindcss/vite` Plugin (kein PostCSS, kein `tailwind.config.js`)

---

## 2. Tech-Stack

| Schicht | Technologie | Version |
|---|---|---|
| Datenbank | MariaDB | via All-Inkl |
| Backend/API | PHP | 8.x |
| Frontend Framework | Vue 3 (Composition API) | latest |
| State Management | Pinia | latest |
| Routing | Vue Router 4 | latest |
| CSS Framework | Tailwind CSS | v4 via `@tailwindcss/vite` |
| Build-Tool | Vite | v7 |
| HTTP-Client | native `fetch()` | — |

---

## 3. Datenbankschema

### 3.1 Tabellen-Übersicht

```
locations         ← Orte (GPS-Koordinaten, Name, Slug)
persons           ← Historische Persönlichkeiten
artifacts         ← Kernentität: Fotos, Postkarten, Dokumente, Karten
artifact_persons  ← n:m Verknüpfung Artefakt ↔ Person
artifact_locations← n:m Verknüpfung Artefakt ↔ weiterer Ort
relations         ← Generische Graphrelationen (erweiterbar)
users             ← Admin-Benutzer (bcrypt-Passwörter)
```

### 3.2 Vollständiges SQL-Schema (`schema.sql`)

```sql
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `locations` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255)    NOT NULL,
  `slug`        VARCHAR(255)    NOT NULL UNIQUE,
  `description` TEXT,
  `lat`         DECIMAL(10, 7)  DEFAULT NULL,
  `lng`         DECIMAL(10, 7)  DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `persons` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `first_name`   VARCHAR(100)  NOT NULL,
  `last_name`    VARCHAR(100)  NOT NULL,
  `birth_year`   SMALLINT      DEFAULT NULL,
  `death_year`   SMALLINT      DEFAULT NULL,
  `biography`    TEXT,
  `portrait_url` VARCHAR(500)  DEFAULT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `artifacts` (
  `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(255)   NOT NULL,
  `slug`          VARCHAR(255)   NOT NULL UNIQUE,
  `description`   TEXT,
  `year`          SMALLINT       DEFAULT NULL,
  `decade`        SMALLINT       DEFAULT NULL,
  `artifact_type` ENUM('photo','document','postcard','map','other') NOT NULL DEFAULT 'photo',
  `image_url`     VARCHAR(500)   DEFAULT NULL,
  `thumb_url`     VARCHAR(500)   DEFAULT NULL,
  `source`        VARCHAR(255)   DEFAULT NULL,
  `is_published`  TINYINT(1)     NOT NULL DEFAULT 0,
  `location_id`   INT UNSIGNED   DEFAULT NULL,
  `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug`      (`slug`),
  KEY `idx_published` (`is_published`),
  KEY `idx_year`      (`year`),
  KEY `idx_location`  (`location_id`),
  CONSTRAINT `fk_artifact_location`
    FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `artifact_persons` (
  `artifact_id` INT UNSIGNED NOT NULL,
  `person_id`   INT UNSIGNED NOT NULL,
  `role`        VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`artifact_id`, `person_id`),
  CONSTRAINT `fk_ap_artifact` FOREIGN KEY (`artifact_id`) REFERENCES `artifacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ap_person`   FOREIGN KEY (`person_id`)   REFERENCES `persons`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `artifact_locations` (
  `artifact_id` INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  `note`        VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`artifact_id`, `location_id`),
  CONSTRAINT `fk_al_artifact` FOREIGN KEY (`artifact_id`) REFERENCES `artifacts`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_al_location` FOREIGN KEY (`location_id`) REFERENCES `locations`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `relations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_a_type` ENUM('artifact','person','location') NOT NULL,
  `entity_a_id`   INT UNSIGNED NOT NULL,
  `relation_type` VARCHAR(100) NOT NULL,
  `entity_b_type` ENUM('artifact','person','location') NOT NULL,
  `entity_b_id`   INT UNSIGNED NOT NULL,
  `note`          TEXT,
  PRIMARY KEY (`id`),
  KEY `idx_a` (`entity_a_type`, `entity_a_id`),
  KEY `idx_b` (`entity_b_type`, `entity_b_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100)  NOT NULL UNIQUE,
  `email`      VARCHAR(255)  NOT NULL UNIQUE,
  `password`   VARCHAR(255)  NOT NULL,
  `role`       ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP     DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
```

---

## 4. Backend: PHP REST-API

### 4.1 Verzeichnisstruktur (Server)

```
public_html/
├── .htaccess                    ← Vue Router History-Mode
├── index.html                   ← Vue SPA Entry Point
├── assets/                      ← Vite Build Output (JS/CSS)
├── uploads/
│   └── artifacts/
│       └── thumbs/
└── api/
    ├── config.php               ← DB-Verbindung, Konstanten
    ├── helpers.php              ← json_response(), JWT-Funktionen
    ├── get_artifact.php             ← GET /api/get_artifact.php?id=
    ├── get_artifact_by_slug.php     ← GET /api/get_artifact_by_slug.php?slug=
    ├── get_artifacts.php            ← GET /api/get_artifacts.php?[filter]
    ├── auth/
    │   └── login.php            ← POST /api/auth/login.php
    └── admin/
        ├── _auth_middleware.php ← JWT-Validierung, wird includiert
        ├── save_artifact.php    ← POST (Create + Update)
        ├── delete_artifact.php  ← POST (Soft/Hard Delete)
        └── upload_image.php     ← POST (Multipart File Upload)
```

### 4.2 `api/config.php`

```php
<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('DB_HOST',    'localhost');
define('DB_NAME',    'dein_datenbankname');
define('DB_USER',    'dein_dbuser');
define('DB_PASS',    'dein_passwort');
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
```

### 4.3 `api/helpers.php`

Enthält: `json_response()`, `abort()`, JWT-Hilfsfunktionen (`jwt_encode()`, `jwt_decode()`, `get_bearer_token()`), Base64URL-Kodierung.

**JWT-Implementierung:** Stateless HMAC-SHA256, keine externe Library. Secret in `JWT_SECRET`-Konstante, TTL 8 Stunden (`JWT_TTL = 28800`).

```php
<?php
define('JWT_SECRET', 'AENDERN_langer_zufaelliger_string_min_32_zeichen');
define('JWT_TTL',    60 * 60 * 8);

function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}

function jwt_encode(array $payload): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64url_decode($payload), true);
    if (!$data || $data['exp'] < time()) return null;
    return $data;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function get_bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return $m[1];
    return null;
}
```

### 4.4 API-Endpunkte

#### GET `/api/get_artifact.php?id={int}`

Gibt ein einzelnes Artefakt zurück inkl. primärem Ort (JOIN), verknüpften Personen und weiteren Orten (je separate Query). Nur `is_published = 1` wird zurückgegeben.

**Response-Struktur:**
```json
{
  "id": 42,
  "title": "Marktplatz Lindlar 1923",
  "slug": "marktplatz-lindlar-1923",
  "description": "...",
  "year": 1923,
  "decade": 1920,
  "artifact_type": "photo",
  "image_url": "/uploads/artifacts/marktplatz-1923.jpg",
  "thumb_url": "/uploads/artifacts/thumbs/marktplatz-1923.jpg",
  "source": "Sammlung Familie Müller",
  "created_at": "2024-11-01 10:00:00",
  "primary_location": { "id": 5, "name": "Marktplatz Lindlar", "lat": 51.0167, "lng": 7.3667 },
  "persons": [{ "id": 12, "first_name": "Heinrich", "last_name": "Berger", "birth_year": 1891, "death_year": 1958, "portrait_url": null, "role": "Abgebildet" }],
  "locations": [{ "id": 5, "name": "Marktplatz Lindlar", "slug": "marktplatz-lindlar", "lat": 51.0167, "lng": 7.3667, "note": null }]
}
```

#### GET `/api/get_artifact_by_slug.php?slug={string}`

Identisch zu `get_artifact.php`, aber Lookup per Slug statt ID. Slug wird serverseitig auf `/^[a-z0-9\-]+$/` validiert. Gibt HTTP 404 zurück wenn kein publiziertes Artefakt mit diesem Slug existiert.

**`ArtifactView.vue` nutzt diesen Endpunkt** — `route.params.slug` wird direkt übergeben. Der Store reagiert auf Slug-Wechsel via `watch(() => route.params.slug, load)`, sodass Browser-Navigation ohne Seiten-Reload funktioniert.

#### GET `/api/get_artifacts.php`

Listenendpunkt mit Filtern und Pagination.

| Parameter | Typ | Beschreibung |
|---|---|---|
| `page` | int | Seite (default: 1) |
| `per_page` | int | max. 50 (default: 20) |
| `q` | string | LIKE-Suche auf Titel + Beschreibung |
| `type` | string | `photo`, `document`, `postcard`, `map`, `other` |
| `year` | int | Exaktes Jahr |
| `decade` | int | z.B. `1920` |
| `location_id` | int | Primärer Ort ODER in `artifact_locations` |
| `person_id` | int | Verknüpfte Person (EXISTS-Subquery) |
| `sort` | string | `year_asc`, `year_desc`, `title_asc` |

**Response-Struktur:**
```json
{
  "data": [ /* Array von Artefakt-Objekten (ohne persons/locations) */ ],
  "meta": { "total": 148, "per_page": 20, "current_page": 1, "last_page": 8, "has_more": true },
  "filters": { /* Aktive Filter werden zurückgespiegelt */ }
}
```

#### POST `/api/auth/login.php`

Body: `{ "username": "...", "password": "..." }`  
Response: `{ "token": "...", "user": { ... }, "expires_in": 28800 }`  
Timing-sicherer Vergleich via `password_verify()` auch bei nicht existierenden Usern (Dummy-Hash).

#### POST `/api/admin/save_artifact.php` *(Auth required)*

Create (kein `id` im Body) oder Update (mit `id`). Slug wird beim Erstellen automatisch generiert und auf Eindeutigkeit geprüft. Verknüpfte Personen und Orte werden per DELETE + INSERT synchronisiert (Replace-Strategie).

#### POST `/api/admin/delete_artifact.php` *(Auth required)*

Soft-Delete: setzt `is_published = 0`. Hard-Delete: löscht Datensatz (nur `role = 'admin'`), Cascade löscht `artifact_persons` und `artifact_locations`.

#### POST `/api/admin/upload_image.php` *(Auth required)*

Multipart-Upload, MIME-Type-Prüfung via `mime_content_type()` (nicht dem Client-Header vertrauen). Erlaubt: JPEG, PNG, WebP. Max. 10 MB. Dateiname: `bin2hex(random_bytes(12))` + Extension. Thumbnail-Generierung via PHP GD (`imagecopyresampled`), 400×300px, JPEG/PNG/WebP entsprechend Originalformat.

### 4.5 Auth-Middleware (`api/admin/_auth_middleware.php`)

Wird per `require_once` am Anfang jedes Admin-Endpunkts eingebunden. Bricht mit HTTP 401 ab bei fehlendem oder ungültigem Token. OPTIONS-Preflight wird direkt beantwortet (CORS). Stellt `$CURRENT_USER` als globale Variable bereit.

### 4.6 Admin-User anlegen (Einmalig)

```php
// setup_admin.php — nach Ausführung SOFORT löschen!
require_once 'api/config.php';
$db   = get_db();
$hash = password_hash('DeinSicheresPasswort!', PASSWORD_BCRYPT);
$db->prepare("INSERT INTO users (username, email, password, role) VALUES ('admin', 'email@domain.de', :hash, 'admin')")
   ->execute([':hash' => $hash]);
echo "Admin angelegt. Diese Datei jetzt löschen!\n";
```

---

## 5. Frontend: Vue 3 SPA

### 5.1 Lokale Entwicklungsumgebung

**Voraussetzungen:** Node.js (nur lokal, nicht auf dem Server).

```bash
npm create vue@latest lindlar-anno-dazumal
# Vue Router: Yes | Pinia: Yes | TypeScript: No | JSX: No
cd lindlar-anno-dazumal
npm install
npm install tailwindcss @tailwindcss/vite
npm run dev   # → http://localhost:5173
```

### 5.2 Konfiguration

**`vite.config.js`:**
```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [tailwindcss(), vue()],
  resolve: { alias: { '@': '/src' } },
})
```

**`src/assets/main.css`:**
```css
@import "tailwindcss";

.font-serif { font-family: 'Playfair Display', Georgia, serif; }
.line-clamp-1 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
.line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
```

> **Tailwind v4 Hinweis:** Kein `tailwind.config.js` und kein `postcss.config.js` nötig. Content-Scanning erfolgt automatisch. Dynamisch zusammengesetzte Klassen (Template-Literale) werden nicht erkannt — alle Klassen müssen statisch im Source stehen.

### 5.3 Verzeichnisstruktur (Frontend)

```
src/
├── main.js
├── App.vue
├── api/
│   └── client.js                 ← fetch-basierter API-Client
├── router/
│   └── index.js                  ← Vue Router 4, History-Mode
├── stores/
│   ├── auth.js                   ← Pinia Store: JWT, Login/Logout
│   └── artifacts.js              ← Pinia Store: Liste, Einzelansicht, Filter
├── views/
│   ├── public/
│   │   ├── HomeView.vue          ← Galerie-Startseite
│   │   ├── ArtifactView.vue      ← Einzelansicht
│   │   ├── TimelineView.vue      ← Platzhalter (ausbaubar)
│   │   └── SearchView.vue        ← Volltextsuche mit Filtern, Deep-Linking
│   └── admin/
│       ├── LoginView.vue         ← Login-Formular
│       ├── DashboardView.vue     ← Artefakt-Tabelle + Delete-Dialog
│       └── ArtifactEditView.vue  ← Create/Edit-Formular + Bild-Upload
└── components/
    ├── layout/
    │   ├── AppHeader.vue         ← Öffentlicher Header + Nav + Mobile-Menü
    │   ├── AppFooter.vue         ← Einfacher Footer
    │   └── AdminSidebar.vue      ← Admin-Layout mit Sidebar
    ├── artifacts/
    │   ├── ArtifactCard.vue      ← Kachel mit Thumbnail, Typ-Badge
    │   ├── ArtifactGrid.vue      ← Responsives Grid (2/3/4 Spalten)
    │   └── ArtifactFilter.vue    ← Filterleiste (Suche, Typ, Jahrzehnt, Sort)
    └── ui/
        ├── LoadingSpinner.vue    ← Zentrierter Spinner
        └── ErrorMessage.vue      ← Fehleranzeige mit Retry-Button
```

### 5.4 `src/api/client.js`

Zentrale Fetch-Abstraktion. Liest JWT automatisch aus `localStorage`. Setzt `Content-Type: application/json` außer bei `FormData`. Wirft `ApiError` (mit `.status`) bei HTTP-Fehler.

```js
const BASE_URL = import.meta.env.VITE_API_URL || '/api'

class ApiError extends Error {
  constructor(message, status) { super(message); this.status = status }
}

async function request(path, options = {}) {
  const token = localStorage.getItem('jwt')
  const headers = { ...options.headers }
  if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json'
  if (token) headers['Authorization'] = `Bearer ${token}`
  const res = await fetch(`${BASE_URL}${path}`, { ...options, headers })
  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new ApiError(data.error || `HTTP ${res.status}`, res.status)
  }
  return res.json()
}

export const api = {
  login:          (username, password) => request('/auth/login.php', { method: 'POST', body: JSON.stringify({ username, password }) }),
  getArtifacts:   (params = {})        => request(`/get_artifacts.php?${new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([, v]) => v != null && v !== '')))}`),
  getArtifact:    (id)                 => request(`/get_artifact.php?id=${id}`),
  getArtifactBySlug: (slug)            => request(`/get_artifact_by_slug.php?slug=${encodeURIComponent(slug)}`),
  saveArtifact:   (data)               => request('/admin/save_artifact.php',   { method: 'POST', body: JSON.stringify(data) }),
  deleteArtifact: (id, hard = false)   => request('/admin/delete_artifact.php', { method: 'POST', body: JSON.stringify({ id, hard_delete: hard }) }),
  uploadImage:    (file)               => { const f = new FormData(); f.append('image', file); return request('/admin/upload_image.php', { method: 'POST', body: f }) },
}

export { ApiError }
```

### 5.5 Pinia Stores

**`stores/auth.js`:** Verwaltet `user` und `token` (beide in `localStorage` persistiert). `isLoggedIn` und `isAdmin` als computed. `checkAuth()` dekodiert den JWT-Payload clientseitig (Base64) und prüft `exp`-Claim. Wird in jedem Navigation Guard aufgerufen.

**`stores/artifacts.js`:** Verwaltet `items` (Liste), `current` (Einzelansicht), `meta` (Pagination), `filters` (aktive Filterparameter). `setFilter()` setzt immer `page` auf 1 zurück. `fetchArtifacts()`, `fetchArtifact()` und `fetchArtifactBySlug()` wrappen API-Calls mit `loading`/`error`-State.

### 5.6 Router (`src/router/index.js`)

Vue Router 4, `createWebHistory`. Alle Views werden lazy-geladen (`() => import(...)`).

**Route-Struktur:**

| Path | Name | Auth | Layout |
|---|---|---|---|
| `/` | `home` | — | AppHeader |
| `/artefakt/:slug` | `artifact` | — | AppHeader |
| `/zeitstrahl` | `timeline` | — | AppHeader |
| `/suche` | `search` | — | AppHeader |
| `/admin/login` | `login` | guestOnly | — |
| `/admin` | `dashboard` | requiresAuth | AdminSidebar |
| `/admin/artefakt/neu` | `artifact-create` | requiresAuth | AdminSidebar |
| `/admin/artefakt/:id/bearbeiten` | `artifact-edit` | requiresAuth | AdminSidebar |

**Navigation Guard:** Setzt Seitentitel, ruft `auth.checkAuth()` auf, leitet bei `requiresAuth` ohne Token zu `/admin/login?redirect=...` weiter.

### 5.7 Admin-Panel

**`LoginView.vue`:** Einfaches Formular, ruft `auth.login()` auf, zeigt Fehler inline. Redirect nach Login zu `/admin` oder `?redirect=`-Parameter.

**`DashboardView.vue`:** Tabellarische Übersicht aller Artefakte mit Thumbnail, Typ, Jahr, Publikationsstatus. Inline-Suche mit 350ms Debounce. Pagination. Delete-Bestätigungsdialog (Modal). Soft-Delete (Depublizieren).

**`ArtifactEditView.vue`:** Kombiniertes Create/Edit-Formular. Beim Bearbeiten wird `store.fetchArtifact(route.params.id)` aufgerufen und das Formular befüllt. Jahrzehnt wird automatisch aus dem Jahr abgeleitet (`Math.floor(year / 10) * 10`). Bild-Upload per Klick oder Drag-and-Drop mit lokalem `URL.createObjectURL()`-Preview vor dem Upload. Personen-Verknüpfung als dynamische Liste (ID + Rolle). Nach erfolgreichem Create: Redirect zur Edit-Seite mit der neuen ID.

> **⚠️ Bekannter Bug in `handleSave()`:** `saving.value` wird am Funktionsanfang zweimal gesetzt (`= false`, dann `= true`). Die erste Zeile ist redundant und kann entfernt werden — funktional korrekt, da `= true` unmittelbar folgt, aber irreführend.

**`LoginView.vue`:** Nach erfolgreichem Login navigiert `auth.login()` intern via `router.push('/admin')`. Zusätzlich liest `LoginView` den `?redirect=`-Query-Parameter aus und ruft ebenfalls `router.push(redirect)` auf. Beide Navigationen können kollidieren — der `auth.store` sollte keinen eigenen Router-Push ausführen, stattdessen sollte der gesamte Redirect-Flow in `LoginView` liegen.

---

## 6. Deploy

### 6.1 Umgebungsvariablen

```bash
# .env.production
VITE_API_URL=https://lindlar-anno-dazumal.de/api
```

### 6.2 Build & Upload

```bash
npm run build
# Erzeugt /dist/
```

Per FTP auf All-Inkl hochladen:

| Lokal | Server |
|---|---|
| `dist/*` | `public_html/` |
| `api/` | `public_html/api/` |
| `schema.sql` | Via phpMyAdmin einspielen |

### 6.3 Apache `.htaccess` (Vue Router History-Mode)

```apache
# public_html/.htaccess
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

### 6.4 Optionale saubere API-URLs

```apache
# public_html/api/.htaccess
RewriteEngine On
RewriteRule ^artifacts$                    get_artifacts.php           [L,QSA]
RewriteRule ^artifacts/(\d+)$             get_artifact.php?id=$1      [L,QSA]
RewriteRule ^artifacts/([a-z0-9\-]+)$    get_artifact_by_slug.php?slug=$1 [L,QSA]
RewriteRule ^persons/(\d+)$              get_person.php?id=$1         [L,QSA]
RewriteRule ^locations/(\d+)$            get_location.php?id=$1       [L,QSA]
```

---

## 7. Bekannte Offene Punkte / TODOs

| Priorität | Thema | Beschreibung |
|---|---|---|
| ✅ Erledigt | Slug-Lookup | `get_artifact_by_slug.php` implementiert, `ArtifactView.vue` nutzt `route.params.slug` |
| ✅ Erledigt | `SearchView.vue` | Volltextsuche mit Filtern, Debounce, Deep-Linking via URL-Query implementiert |
| Hoch | CORS | `Access-Control-Allow-Origin: *` in `helpers.php` für Produktion einschränken |
| Hoch | JWT_SECRET | Muss auf einen langen, zufälligen Wert gesetzt und sicher verwahrt werden |
| Mittel | `TimelineView.vue` | Nur Platzhalter — Jahrzehnt-gruppierte Ansicht noch nicht implementiert |
| Mittel | Admin unpublished | Dashboard zeigt nur `is_published=1` — eigener Admin-Listenendpunkt nötig |
| Mittel | Personen-Picker | Personen werden aktuell per ID eingetragen — Autocomplete/Suche sinnvoll |
| Niedrig | Volltext-Index | LIKE-Suche für große Datenmengen durch `FULLTEXT INDEX` ersetzen |
| Niedrig | `setup_admin.php` | Nach erstem Aufruf sofort vom Server löschen |
| Niedrig | Login-Redirect | `auth.store` und `LoginView` führen beide `router.push()` aus — Redirect-Logik sollte nur in `LoginView` liegen |
| Niedrig | `handleSave()` Bug | Redundantes `saving.value = false` am Funktionsanfang in `ArtifactEditView.vue` entfernen |

---

## 8. Sicherheitshinweise

- `display_errors = 0` in `config.php` — PHP-Fehler werden nie an den Client ausgegeben
- MIME-Type-Prüfung via `mime_content_type()` serverseitig (nicht dem `Content-Type`-Header vertrauen)
- Upload-Dateinamen sind kryptografisch zufällig (`random_bytes(12)`) — keine Nutzer-Eingaben im Dateinamen
- `password_verify()` läuft immer, auch bei nicht existierendem User (Timing-Angriff-Schutz)
- JWT-Signaturvergleich via `hash_equals()` (Timing-sicher)
- Hard-Delete nur für `role = 'admin'`
- `setup_admin.php` nach einmaliger Ausführung sofort löschen
