
## Project Overview

**TIDAL - La Dream Tim** is a CPE Lyon school project implementing an acupuncture knowledge base web application. It teaches PHP MVC architecture through iterative development.

- **Team:** El-Idrissi Walid, Mazuel Loris, Picard Raphaël
- **Language:** PHP 8.2 (no Composer), PostgreSQL 16, Twig 3 templating
- **Stack:** Apache 2.4 + PDO + Docker Compose

---

## Running the Application

```bash
# Full reset and rebuild (required after schema changes)
docker compose down -v
docker compose up --build

# Start without rebuild (for code-only changes — hot-reload via volume mount)
docker compose up
```

- App: `http://localhost:50180`
- pgAdmin: `http://localhost:50181` (admin credentials: `admin@acudb.com` / `admin`)
- DB credentials (app user): `acu` / `acu` on database `acudb`

> Source files under `src/` are mounted directly into the PHP container — PHP changes take effect immediately without rebuilding.

---

## Directory Structure

```
projet-tidal-la_dream_tim/
├── CLAUDE.md                      # This file
├── README.md                      # Project documentation (French)
├── docker-compose.yaml            # Service orchestration
├── .env                           # Docker environment variables
├── conf/
│   ├── php/
│   │   ├── Dockerfile             # PHP 8.2-apache + pdo_pgsql + mod_rewrite
│   │   ├── custom-php.ini         # PHP runtime config
│   │   └── site.conf              # Apache VirtualHost config
│   ├── postgres/
│   │   ├── Dockerfile             # postgres:16 with init script runner
│   │   └── sql/
│   │       ├── 1-acudb-tables.sql # Full schema + seed data (~2500 lines)
│   │       └── 2-app-user.sh      # Creates restricted `acu` user
│   └── pgadmin/
│       └── servers.json           # Pre-configured pgAdmin server entry
├── ressources/
│   └── acuBD.png                  # Database entity-relationship diagram
└── src/                           # Web root (mounted into Docker container)
    ├── index.php                  # Single Point of Entry — all requests start here
    ├── .htaccess                  # Rewrites all URIs to index.php
    ├── request.sql                # Reference SQL queries (not executed by app)
    ├── Controller/
    │   ├── TwigController.php     # Base controller: initializes Twig, provides render()
    │   ├── AuthController.php     # login / register / logout pages
    │   ├── homeController.php     # Homepage with filtering and pagination
    │   └── detailController.php   # Pathology detail page
    ├── Model/
    │   ├── PathoModel.php         # Pathology search, count, detail queries
    │   └── User.php               # User data class (id, identifiant, password)
    ├── Service/
    │   ├── Database.php           # PDO connection factory (reads env vars)
    │   ├── AuthService.php        # login / register / logout / isLogged logic
    │   └── router.php             # URL router: maps paths to controllers
    ├── View/
    │   ├── layout/
    │   │   └── base.html.twig     # Base template (header, nav, footer)
    │   └── home/
    │       ├── home.html.twig     # Pathology listing with filters
    │       ├── login.html.twig    # Login form
    │       ├── register.html.twig # Registration form
    │       └── detail.html.twig   # Pathology detail view
    ├── css/
    │   ├── style.css
    │   ├── sympto.css
    │   └── Template.css
    └── Twig/                      # Twig library (vendored, no Composer)
```

---

## Architecture: MVC with Single Point of Entry

All HTTP requests are rewritten to `src/index.php` via `.htaccess`. The flow is:

```
Browser → Apache → .htaccess → index.php → router.php → Controller → Model/Service → View (Twig)
```

### Routing (`src/Service/router.php`)

| URI | Controller | Method |
|-----|-----------|--------|
| `/` or `/home` | homeController | `show()` |
| `/login` | AuthController | `login()` |
| `/register` | AuthController | `register()` |
| `/logout` | AuthController | `logout()` |
| `/detail` | detailController | `show()` |

### Autoloading (`src/index.php`)

No Composer. A custom `spl_autoload_register` searches for classes in:
- `Controller/`
- `Model/`
- `Service/`

Class names must match filenames exactly (e.g., `AuthController` → `Controller/AuthController.php`).

---

## Database

### Connection (`src/Service/Database.php`)

Credentials come from environment variables set in Docker:
```
POSTGRES_PHP_USER, POSTGRES_PHP_PASSWORD, POSTGRES_DB_NAME
```

`Database::getConnection()` returns a configured PDO instance with:
- Error mode: `ERRMODE_EXCEPTION`
- Fetch mode: `FETCH_ASSOC`

### Key Tables

- `public.users` — `id`, `identifiant`, `password` (bcrypt)
- `public.patho` — pathology records
- `public.keySympt` — keyword-symptom many-to-many relation
- Additional acupuncture tables: keywords, symptome, meridien, etc.

### Schema Changes

Edit `conf/postgres/sql/1-acudb-tables.sql`, then:
```bash
docker compose down -v && docker compose up --build
```
The `-v` flag drops the named volume so init scripts re-run.

---

## Filtering Logic (`src/Controller/homeController.php`)

The homepage supports complex multi-criteria filtering for pathologies:

- **`type`** — mapped to internal meridian codes (`me`, `mi`, `lp`, `lv`, `j`, etc.) combined with characteristic codes (`e`, `i`, `p`, `v`, `c`, `f` for external/internal/full/void/hot/cold)
- **`meridien`** — specific meridian name

Filters are passed as query parameters and reconstructed on each request. Pagination uses `page` and `limit` parameters.

---

## Authentication (`src/Service/AuthService.php`)

Static methods only:
- `login($identifiant, $password)` — validates credentials, sets `$_SESSION['user']`, regenerates session ID
- `register($identifiant, $password)` — creates user with `password_hash()` (bcrypt)
- `logout()` — destroys session and clears cookie
- `isLogged()` — returns `bool` based on `$_SESSION['user']`

Security practices in place:
- All DB queries use PDO prepared statements
- Passwords stored with bcrypt
- Session ID regenerated on login
- `htmlspecialchars()` applied before rendering user input



---
--- 
(10 avril)
# GROSSE MISE EN COMMUN 

## Pagination

Gérée côté serveur par `LIMIT / OFFSET` dans `PathoModel::searchPathologies()`.

Paramètres acceptés :
- `page` — numéro de page (défaut : 1, min : 1)
- `limit` — résultats par page, valeurs autorisées : `5`, `10`, `20`, `50` (défaut : 10)

`HomeController` et `ApiController` utilisent tous deux `PathoModel::buildFilters()` pour centraliser la logique de mapping `type + carac → codes SQL internes`.

La nav de pagination (`#pagination-nav`) est rendue par Twig au chargement initial, puis mise à jour dynamiquement par le JS du scroll infini via `renderPagination()`.

---

## API REST

### Endpoint

```
GET /api/pathologies
```

Défini dans `src/Service/router.php`, géré par `ApiController::getPathologies()`.

```
GET /api/pathologies/{id}
```
Défini dans `src/Service/router.php` avec un routage dynamique (`preg_match` sur `/^\/api\/pathologies\/(\d+)$/`), géré par `ApiController::getPathologyByID()`.

**Réponse JSON :**

```json
{
  "nom":       "…",
  "meridien":  "…",
  "type":      "…",
  "element":   "…",
  "yin":       "…",
  "code":      "…",
  "symptome":  [...]
}
```

Retourne un code HTTP 404 avec JSON d'erreur si l'ID ne correspond à aucune pathologie.

**Paramètres query string :**

| Paramètre  | Type     | Description |
|------------|----------|-------------|
| `type`     | string   | Type de pathologie (`me`, `mi`, `lp`, `lv`, `j`, `tf`, `m`, `l`…) |
| `carac`    | string   | Caractéristique (`e`, `i`, `p`, `v`, `c`, `f`) |
| `meridien` | string   | Méridien (ex : `P`, `GI`, `R`…) |
| `page`     | int      | Numéro de page (défaut : 1) |
| `limit`    | int      | Taille de page — valeurs autorisées : `5`, `10`, `20`, `50` (défaut : 10) |

**Réponse JSON :**

```json
{
  "data":         [...],
  "page":         1,
  "totalPages":   5,
  "totalResults": 42,
  "limit":        10
}
```

---

### Infinite scroll (AJAX)

Implémenté en JS vanilla dans `home.html.twig` (IIFE, aucune dépendance externe).

**Mécanisme :**
- `IntersectionObserver` surveille `#scroll-sentinel` (div de 1 px placée après la pagination)
- Dès que la sentinelle entre dans le viewport (threshold : 10 %), un debounce de 500 ms déclenche `loadNextPage()`
- `loadNextPage()` appelle `GET /api/pathologies?page=N&...` et insère les nouvelles lignes via `buildRow()`
- Un verrou booléen `loading` évite les requêtes concurrentes
- La pagination est redessinée après chaque chargement (`renderPagination()`)

**Sécurité XSS :**
- Les données JSON de l'API sont échappées via `escapeHtml()` avant insertion dans le DOM
- Les filtres actifs sont lus depuis les `data-*` de la sentinelle (injectés par Twig avec `|e('html_attr')`)

**État initial** (injecté par Twig dans `data-*` de la sentinelle) :

```html
<div id="scroll-sentinel"
     data-page="1"
     data-total-pages="5"
     data-limit="10"
     data-type="m"
     data-carac="e"
     data-meridien="P">
</div>
```

---

### Filtrage automatique (pas de bouton "Filtrer")

---
---
(15 avril)

## Affichage dynamique des détails d'une pathologie

Deux accès aux détails cohabitent pour illustrer les deux approches côté rendu : templating serveur et consommation d'API REST en JavaScript.

### Page détail classique (templating serveur)

Route : `GET /detail?id={id}&type=…&carac=…&meridien=…` → `DetailController::showDetail()`

- Vérification de l'ID avec `ctype_digit` : redirection vers `/` si invalide
- Gestion 404 dédiée (`layout/404.html.twig`) si l'ID ne correspond à aucune pathologie
- Transmission des filtres actifs dans l'URL pour conserver le contexte de navigation
- Affichage des détails, des symptômes et (pour les connectés) des mots-clés associés

### Modale "Aperçu rapide" (consommation de l'API REST en JS)

Consomme l'endpoint `GET /api/pathologies/{id}` (voir section API REST).

- Côté client, `src/js/modale-apercu.js` écoute les clics sur les boutons `[data-id]` via délégation d'événements sur le `<tbody>` (compatible avec les lignes ajoutées par le scroll infini)
- Remplissage dynamique des champs de la modale (`textContent`) et construction de la liste des symptômes (`appendChild`)
- Fermeture : bouton ×, clic sur l'overlay, touche `Échap`


## Toggle mot de passe

Implémenté dans `src/js/toggle-password.js`.

- Bouton œil ajouté sur les champs `password` des formulaires `login.html.twig` et `register.html.twig`
- Bascule entre `type="password"` et `type="text"` au clic
- Style dans `src/css/style.css`

---

## Suppression de compte

Route : `POST /delete-account` → `AuthController::deleteAccount()`

- Bouton visible dans la navbar (`base.html.twig`) uniquement si l'utilisateur est connecté
- `AuthService::deleteAccount()` supprime la ligne dans `public.users`, détruit la session
- Permissions SQL ajustées dans `conf/postgres/sql/2-app-user.sh` (`DELETE` accordé à l'utilisateur `acu`)
- Redirect vers `/login` après suppression

---

## JWT

Authentification par JSON Web Token sur l'API REST. Voir `src/Service/JWT_README.md` pour le détail complet.

### Fichiers impliqués

| Fichier | Rôle |
|---|---|
| `src/Service/JwtUtils.php` | Génération / vérification des tokens (namespace retiré pour compatibilité avec l'autoloader) |
| `src/Controller/ApiController.php` | `getToken()` (endpoint à faire !!! `POST /api/auth/token`) + `requireAuth()` + protection de `getPathologies()` |
| `src/Controller/AuthController.php` | Option B : génération du JWT en session après login réussi |
| `src/View/layout/base.html.twig` | Option B : injection du token dans `<meta name="api-token">` |
| `src/js/jwt.js` | Lecture de la balise meta et stockage dans `localStorage` |
| `src/js/scroll-infini.js` et `src/js/modale-apercu.js` | Ajout de l'en-tête `Authorization: Bearer …` dans chaque appel `fetch` |

### Flux (Option B — token caché dans le HTML)

```
Login réussi (PHP)
  → JwtUtils::newAccessToken() → token stocké dans $_SESSION['jwt']
  → base.html.twig injecte <meta name="api-token" content="eyJ...">
  → jwt.js lit la meta et stocke le token dans localStorage
  → scroll-infini.js envoie Authorization: Bearer … sur GET /api/pathologies
  → ApiController::requireAuth() valide le token avant de répondre
```

### Routing mis à jour

| URI | Méthode | Controller | Action |
|-----|---------|-----------|--------|
| `/api/auth/token` | POST | ApiController | `getToken()` |
| `/api/pathologies` | GET | ApiController | `getPathologies()` (requiert JWT valide) |
| `/delete-account` | POST | AuthController | `deleteAccount()` |


---
## Recherche par mot-clé

### `PathoModel.php`
- `buildFilters()` — ajouter le paramètre `?string $keyword`
- `searchPathologies()` — intégrer la logique de recherche par mot-clé (jointure avec `keySympt` + `symptome`) ; voir la requête SQL dans `request.sql`
- `countPathologies()` — même adaptation pour la pagination

### `homeController.php`
- `show()` — récupérer le paramètre mot-clé depuis `$_GET` et le transmettre à la vue via `home_data`

### `_filtres.html.twig`
- Ajouter un champ de recherche par mot-clé (input `name="keyword"`) dans le formulaire de filtres

---
(post 15 avril)

### @TODO 


- **Reprendre le CDC (Cahier des Charges) et vérifier que tout est bien respecté (acessibilité)** ==> Ex : sans souris ? ==> Walid

- Pour respecter API REST (**stateless**) ??? :
Remplacer la vérif de isLogged (ex : barre de recherche) par une vérif de JWT (Option B) ==> Raph
==> Demander au prof  JWT avec Authorization: Bearer ???

- Dynamiser les values de la barre de recherche dans home.html.twig ? ==> Loris (j'ai toujours pas compris ça)

- Faire le readme de : 
Affichage dynamique de la page des détails. (POP UP ==> API REST) ==> Loulou (Fait)

- Relire l'autoloader de index.php et le comprendre parfaitement ==> Loris
- **Déplacer ici les commentaires de CLAUDE.md qui vous concernent et vous semblent pertinents**

