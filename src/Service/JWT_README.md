# Authentification JWT sur l'API REST

Ce guide explique comment brancher une authentification par jeton **JWT** (JSON Web Token)
sur l'API du projet, en partant de `JwtUtils.php` déjà présent dans `src/Service/`.



**JWT (3 parties : header, payload, signature)** est un format de jeton sécurisé et autoportant,
qui peut être vérifié par le serveur sans requête en base de données à chaque appel.
- header : indique l'algorithme de signature (ex: HS256) et le type (JWT)
- payload : contient les données (ex: identifiant de l'utilisateur) et les timestamps d'émission/expiration
- signature : hash du header + payload avec une clé secrète connue du serveur, pour garantir l'intégrité du token

---

## Sommaire

1. [Comprendre le flux](#1-comprendre-le-flux)
2. [Problème de namespace dans JwtUtils.php](#2-problème-de-namespace-dans-jwt-utilsphp)
3. [Exposer un endpoint de connexion API](#3-exposer-un-endpoint-de-connexion-api-post-apiauthtoken)
4. [Option B — Jetons cachés dans le HTML de login](#4-option-b--jetons-cachés-dans-le-html-de-login)
5. [Protéger les endpoints API](#5-protéger-les-endpoints-api)
6. [Côté JavaScript — stocker et envoyer le token](#6-côté-javascript--stocker-et-envoyer-le-token)
7. [Tester avec Bruno / Insomnia / Postman](#7-tester-avec-bruno--insomnia--postman)

---

## 1. Comprendre le flux

```
Client (navigateur / Insomnia)          ==> DEMANDE DE TOKEN
        │
        │  POST /api/auth/token  { identifiant, password }
        ▼
    ApiController::getToken()          ==> Vérification + GENERATION DU JWT
        │  AuthService::login() → vérifie BDD
        │  JwtUtils::newAccessToken()
        ▼
    { "access_token": "eyJ...", "expires": "..." }  ==> TRANSMISSION DU TOKEN
        │
        │  GET /api/pathologies
        │  Authorization: Bearer eyJ...
        ▼
    ApiController::getPathologies()                 ==> VERIFICATION DU JWT + RÉPONSE
        │  JwtUtils::getAccessTokenFromRequest()
        │  JwtUtils::checkAccessToken()  → STATUS_VALID / EXPIRED / INVALID
        ▼
    [ liste des pathologies en JSON ]
```

---

## 2. Problème de namespace dans JwtUtils.php

`JwtUtils.php` utilise `namespace App\Models\Utils;`.  
L'autoloader du projet (`src/index.php`) ne gère **pas** les namespaces PSR-4 :
il cherche les fichiers par nom de classe simple (`JwtUtils.php`), pas par chemin de namespace.

**Solution** : retirer la déclaration de namespace et les `use` inutiles en tête du fichier.

```php
// AVANT (src/Service/JwtUtils.php)
namespace App\Models\Utils;
use DateTime;
use DateInterval;
use RuntimeException;

class JwtUtils { ... }

// APRÈS
class JwtUtils { ... }
// DateTime, DateInterval, RuntimeException sont des classes PHP globales,
// elles fonctionnent sans use dès qu'on n'est plus dans un namespace.
```

Sans ce correctif, PHP lèvera une `Fatal error: Class "JwtUtils" not found`
dès que `ApiController` appellera `JwtUtils::newAccessToken()`.

---

## 3. Exposer un endpoint de connexion API : `POST /api/auth/token`

### 3a. Ajouter la route dans `src/Service/router.php`

```php
case '/api/auth/token':
    (new ApiController())->getToken();
    break;
```

Placez ce `case` **avant** le `case '/api/pathologies'` existant.

### 3b. Ajouter la méthode `getToken()` dans `src/Controller/ApiController.php`

```php
/**
 * POST /api/auth/token
 *
 * Corps attendu (application/x-www-form-urlencoded ou JSON) :
 *   identifiant=xxx&password=yyy
 *
 * Réponse 200 :
 *   { "access_token": "eyJ...", "issued": "...", "expires": "..." }
 *
 * Réponse 401 :
 *   { "error": "Identifiant ou mot de passe incorrect" }
 */
public function getToken(): void
{
    header('Content-Type: application/json; charset=utf-8');

    // Accepte aussi un corps JSON (pratique pour Bruno/Postman)
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $identifiant = trim($_POST['identifiant'] ?? $body['identifiant'] ?? '');
    $password    = $_POST['password']         ?? $body['password']    ?? '';

    if (empty($identifiant) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Champs identifiant et password obligatoires']);
        return;
    }

    if (!AuthService::login($identifiant, $password)) {
        http_response_code(401);
        echo json_encode(['error' => 'Identifiant ou mot de passe incorrect']);
        return;
    }

    // Authentification réussie → génération du JWT
    $tokenData = JwtUtils::newAccessToken([
        'identifiant' => $identifiant,
    ]);

    http_response_code(200);
    echo json_encode($tokenData);
}
```

> **Note** : `AuthService::login()` démarre aussi une session PHP.
> Ce n'est pas un problème, mais si vous voulez un endpoint purement stateless,
> vous pouvez créer une méthode `AuthService::verify()` qui vérifie la BDD
> sans toucher à `$_SESSION`.










---

## 4. Option B — Jetons cachés dans le HTML de login

Si vous ne souhaitez pas créer l'endpoint dédié, vous pouvez émettre le JWT
directement dans le HTML lors du traitement du formulaire de connexion.

Dans `src/Controller/AuthController.php`, juste après la redirection réussie :

```php
// AVANT
elseif (AuthService::login($identifiant, $password)) {
    header("Location: /");
    exit;
}

// APRÈS (on injecte aussi le JWT dans la session, le JS le lira depuis le DOM)
elseif (AuthService::login($identifiant, $password)) {
    $tokenData = JwtUtils::newAccessToken(['identifiant' => $identifiant]); 
    //tokenData contient : access_token, issued, expires
    $_SESSION['jwt'] = $tokenData['access_token']; // disponible côté serveur
    header("Location: /");
    exit;
}
```

Puis dans `src/View/layout/base.html.twig`, injectez-le dans un attribut `data-`
invisible (jamais dans un commentaire HTML, qui est visible dans le source) :

```twig
{# Dans le <body>, après le header #}
{% if session_JWT is defined and session_JWT %}
  <meta name="api-token" content="{{ session_JWT }}"> 
{% endif %}
```

Et dans le contrôleur qui passe les données à base.html.twig (ex: `$details_data`) :

```php
$details_data = [
    // ... existant ...
    'session_JWT' => $_SESSION['jwt'] ?? null,
];
```

Le JavaScript lira ensuite :
```javascript
const token = document.querySelector('meta[name="api-token"]')?.content ?? null;
```

> **Inconvénient** : le token est visible dans le source HTML.
> C'est acceptable pour un projet pédagogique, mais en production on préfère l'endpoint dédié (option A) ou un cookie `httpOnly`.

---

## 5. Protéger les endpoints API

Dans `src/Controller/ApiController.php`, ajoutez une méthode privée de vérification
et appelez-la en tête de chaque méthode à protéger.

### Méthode helper (à ajouter dans ApiController)

```php
/**
 * Vérifie le JWT de la requête. Envoie une erreur 401 et stoppe l'exécution
 * si le token est absent, invalide ou expiré.
 */
private function requireAuth(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        $token  = JwtUtils::getAccessTokenFromRequest(); // lève RuntimeException si absent/mal formé
        $status = JwtUtils::checkAccessToken($token);
    } catch (RuntimeException $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token manquant ou mal formé', 'detail' => $e->getMessage()]);
        exit;
    }

    if ($status === JwtUtils::STATUS_EXPIRED) {
        http_response_code(401);
        echo json_encode(['error' => 'Token expiré, veuillez vous reconnecter']);
        exit;
    }

    if ($status !== JwtUtils::STATUS_VALID) {
        http_response_code(401);
        echo json_encode(['error' => 'Token invalide']);
        exit;
    }
    // Si on arrive ici, le token est valide → la méthode appelante continue.
}
```

### Appel dans `getPathologies()`

```php
public function getPathologies(): void
{
    $this->requireAuth(); // ← ajouter cette ligne en premier

    // ... reste du code existant inchangé ...
    $meridien = $_GET['meridien'] ?? null;
    // ...
}
```

---

## 6. Côté JavaScript — stocker et envoyer le token

### Récupérer et stocker le token (Option A — endpoint dédié)

```javascript
// Appel au moment du login (si vous gérez le login en JS, sinon voir Option B)
async function fetchToken(identifiant, password) {
  const res = await fetch('/api/auth/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ identifiant, password }),
  });

  if (!res.ok) throw new Error('Identifiants incorrects');

  const data = await res.json();
  localStorage.setItem('api_token', data.access_token); // stockage local
  return data.access_token;
}
```



### Récupérer le token (Option B — balise meta dans le HTML)

```javascript
const token = document.querySelector('meta[name="api-token"]')?.content ?? null;
if (token) localStorage.setItem('api_token', token);
```

### Envoyer le token dans chaque appel API (à adapter dans `home.html.twig`)

Dans la fonction `loadNextPage()` du scroll infini (`src/View/home/home.html.twig`),
modifiez le `fetch` pour y ajouter l'en-tête `Authorization` :

```javascript
// AVANT
const res = await fetch('/api/pathologies?' + p.toString());

// APRÈS
const token = localStorage.getItem('api_token');
const res = await fetch('/api/pathologies?' + p.toString(), {
  headers: token ? { 'Authorization': 'Bearer ' + token } : {},
});
```

> `localStorage` est vide si l'utilisateur n'est pas connecté →
> l'en-tête n'est pas envoyé → le serveur répond 401 → le catch affiche l'erreur.

---

## 7. Tester avec Bruno / Insomnia / Postman

### Étape 1 — Obtenir un token

```
POST http://localhost:50180/api/auth/token
Content-Type: application/x-www-form-urlencoded

identifiant=votre_identifiant&password=votre_mdp
```

Réponse attendue :
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ...",
  "issued": "2025-01-01T10:00:00+00:00",
  "expires": "2025-01-01T12:00:00+00:00"
}
```

### Étape 2 — Appeler l'API protégée

```
GET http://localhost:50180/api/pathologies?page=1&limit=10
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ...
```

### Étape 3 — Tester les cas d'erreur

| Scénario | En-tête envoyé | Réponse attendue |
|---|---|---|
| Pas de token | *(rien)* | `401 { "error": "Token manquant..." }` |
| Token trafiqué | `Bearer xxxxx` | `401 { "error": "Token invalide" }` |
| Token expiré | `Bearer <token > 2h>` | `401 { "error": "Token expiré..." }` |
| Token valide | `Bearer <token frais>` | `200 { "data": [...] }` |

> Pour simuler un token expiré rapidement : modifiez temporairement
> `DURATION = 'PT5S'` dans `JwtUtils.php` (5 secondes), obtenez un token,
> attendez, puis testez.

---

## Récapitulatif des fichiers modifiés

| Fichier | Modification |
|---|---|
| `src/Service/JwtUtils.php` | Déplacé depuis `src/` + namespace retiré |
| `src/Service/router.php` | Ajout du `case '/api/auth/token'` |
| `src/Controller/ApiController.php` | Ajout de `getToken()` + `requireAuth()` + appel dans `getPathologies()` |
| `src/Controller/AuthController.php` | *(Option B)* Génération du JWT en session après login |
| `src/View/layout/base.html.twig` | *(Option B)* Balise `<meta name="api-token">` |
| `src/View/home/home.html.twig` | Ajout de l'en-tête `Authorization` dans `loadNextPage()` |
