# TIDAL — Base de connaissances en acupuncture

## Project Overview

Application web de consultation d'une base de données en acupuncture, réalisée dans le cadre du projet TIDAL à CPE Lyon. L'objectif pédagogique est la mise en pratique de l'architecture MVC en PHP pur, avec une API REST et une interface dynamique (Twig).

| | |
|---|---|
| **Équipe** | El-Idrissi Walid · Mazuel Loris · Picard Raphaël  ==> Participation équitable |
| **Langage** | PHP 8.2 (sans Composer), PostgreSQL 16, Twig 3 |
| **Stack** | Apache 2.4 · PDO · Docker Compose |
| **Date**  | Avril 2026 |

---

## Running the Application

```bash
# Premier lancement ou après modification du schéma SQL
docker compose down -v
docker compose up --build

# Relance simple (changements PHP uniquement — hot-reload via volume mount)
docker compose up
```

| Service | URL | Identifiants |
|---|---|---|
| Application | `http://localhost:50180` | — |
| pgAdmin | `http://localhost:50181` | `admin@acudb.com` / `admin` |
| Base de données | `acudb` (port interne) | `acu` / `acu` |

> Les fichiers sous `src/` sont montés directement dans le conteneur PHP : toute modification PHP est visible immédiatement sans rebuild.

---

## Architecture MVC — Point d'entrée unique

Toutes les requêtes HTTP sont réécrites vers `src/index.php` via `.htaccess`.

```
Navigateur → Apache → .htaccess → index.php → router.php → Controller → Model/Service → View (Twig)
```

**Autoloader maison** (`src/index.php`) — sans Composer : un `spl_autoload_register` résout les classes depuis `Controller/`, `Model/` et `Service/` en faisant correspondre nom de classe et nom de fichier.

**Routeur** (`src/Service/router.php`) — correspondance URI → contrôleur, avec routage dynamique par `preg_match` pour les routes paramétrées (`/api/pathologies/{id}`).

---

## Fonctionnalités clés à tester

### 1. Listing & filtrage des pathologies (`/`)

- Filtres combinables : **type** (méridien + caractéristique) et **méridien** et **keyword**
- Soumission automatique du formulaire au changement de filtre (pas de bouton "Filtrer")
- Pagination serveur (`LIMIT / OFFSET`) avec sélecteur de taille de page (5 / 10 / 20 / 50)

### 2. Scroll infini (AJAX)

- `IntersectionObserver` sur un `#scroll-sentinel` de 1 px en bas de liste
- Dès déclenchement : appel `GET /api/pathologies?page=N&…` avec debounce 500 ms
- Insertion des lignes via `buildRow()`, mise à jour de la pagination côté client
- Verrou booléen `loading` pour éviter les requêtes concurrentes
- XSS : données échappées via `escapeHtml()` avant injection dans le DOM

### 3. API REST (`/api/pathologies`)

Endpoint JSON consommé par le scroll infini et la modale :

```
GET /api/pathologies          → liste paginée (paramètres : type, carac, meridien, page, limit)
GET /api/pathologies/{id}     → détail d'une pathologie (404 JSON si ID inconnu)
POST /api/auth/token          → obtention d'un JWT
```

L'API est **protégée par JWT** — chaque appel `fetch` envoie un `Authorization: Bearer …`.

### 4. JWT (token injecté dans le HTML)
// Pour un endpoint protégé, voir Service/JWT_README.md

```
Login réussi (PHP)
  → JwtUtils::newAccessToken() → stocké dans $_SESSION['jwt']
  → base.html.twig injecte <meta name="api-token" content="eyJ...">
  → jwt.js lit la meta et place le token dans localStorage
  → scroll-infini.js / modale-apercu.js ajoutent Authorization: Bearer … sur chaque fetch
  → ApiController::requireAuth() valide le token avant de répondre
```

### 5. Modale "Aperçu rapide"

- Clic sur une ligne du tableau → appel `GET /api/pathologies/{id}` → remplissage dynamique de la modale
- Délégation d'événements sur le `<tbody>` : compatible avec les lignes ajoutées par le scroll infini
- Fermeture : bouton ×, clic sur l'overlay, touche `Échap`

### 6. Page de détail classique (templating serveur)

Route : `GET /detail?id={id}&type=…&carac=…&meridien=…`

- Validation de l'ID avec `ctype_digit`, redirection `/` si invalide
- Page 404 dédiée si l'ID n'existe pas
- Filtres actifs conservés dans l'URL pour le retour en arrière
- Mots-clés associés affichés **uniquement si l'utilisateur est connecté**

### 7. Authentification & sécurité

- Création d'une nouvelle table `users` dans la bdd et des permissions d'accès (pour supression de compte par exemple), grâce à `conf/postgres/sql/*`
- Inscription / connexion / déconnexion (`/register`, `/login`, `/logout`)
- **Suppression de compte** (`POST /delete-account`) — accessible depuis la navbar si connecté
- Mots de passe hashés en **bcrypt** (`password_hash`)
- Session ID régénérée à la connexion
- Toutes les requêtes SQL utilisent des **prepared statements PDO**
- `htmlspecialchars()` sur les entrées utilisateur avant rendu Twig pour éviter les attaques XSS

---

## Structure des sources

```
src/
├── index.php                  # Point d'entrée unique + autoloader
├── .htaccess                  # Réécriture vers index.php
├── Controller/
│   ├── TwigController.php     # Contrôleur de base (init Twig, render())
│   ├── AuthController.php     # Login / Register / Logout / DeleteAccount
│   ├── homeController.php     # Page d'accueil, filtres, pagination
│   ├── detailController.php   # Page de détail (rendu serveur)
│   └── ApiController.php      # API REST JSON + gestion JWT
├── Model/
│   └── PathoModel.php         # Requêtes SQL (search, count, detail, buildFilters)
├── Service/
│   ├── Database.php           # Factory PDO (lit les variables d'environnement Docker)
│   ├── AuthService.php        # login / register / logout / isLogged / deleteAccount
│   ├── JwtUtils.php           # Génération et vérification des tokens JWT
│   └── router.php             # Routeur URI → contrôleur
├── View/
│   ├── layout/base.html.twig  # Template de base (header, nav, footer, injection meta JWT)
│   └── home/                  # Vues : listing, login, register, détail
└── js/
    ├── scroll-infini.js       # Scroll infini (IntersectionObserver + fetch API)
    ├── modale-apercu.js       # Modale aperçu rapide (fetch API REST)
    ├── jwt.js                 # Lecture meta → localStorage
    └── toggle-password.js     # Bascule affichage mot de passe
```

## Ce qui a été fait après l'oral de restitution
- Fix du bug de la recherche par mot clé (qui consistait à utiliser un compte connecté, tester la recherche par mot clé, copier l'url de la page, se déconnecter et le coller dans un nouvel onglet pour voir que la recherche par mot clé fonctionnait même sans être connecté)

Dans `homeController.php` :
```php
// Ancien code (bug : $keywords est défini même si l'utilisateur n'est pas connecté)
$keyword   = $_GET['keyword'] ?? '';
// Nouveau code (fix du bug : $keywords est défini uniquement si l'utilisateur est connecté)
$keyword   = isset($_SESSION['user']) ? ($_GET['keyword'] ?? '') : '';