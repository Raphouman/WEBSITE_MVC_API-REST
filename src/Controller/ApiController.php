<?php
// src/Controller/ApiController.php
// Contrôleur REST API – retourne du JSON (pas de rendu Twig)

class ApiController {

    /**
     * Vérifie le JWT de la requête. Envoie une erreur 401 et stoppe l'exécution (important : exit)
     * si le token est absent, invalide ou expiré.
     */
    private function requireAuth(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $token  = JwtUtils::getAccessTokenFromRequest(); // JwtUtils::getAccessTokenFromRequest() extrait le token JWT du header Authorization de la requête HTTP (ex: "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...") et vérifie sa structure de base (3 parties séparées par des points). Si le token est absent ou mal formé, elle lève une RuntimeException avec un message d'erreur approprié.
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

    /**
     * GET /api/pathologies
     *
     * Endpoint de recherche de pathologies avec filtres et pagination.
     *
     * Paramètres acceptés (query string) :
     *   - meridien : filtre par méridien (ex: "Poumon", "Rein") — optionnel
     *   - type     : catégorie de pathologie (ex: "me", "mi") — optionnel
     *   - carac    : caractéristique (ex: "e", "i", "v") — optionnel
     *   - page     : numéro de page (défaut : 1)
     *   - limit    : résultats par page, valeurs autorisées : 5, 10, 20, 50 (défaut : 10)
     *
     * Retourne une réponse JSON de la forme :
     * {
     *   "data"         : [...],  // tableau de pathologies correspondant aux filtres
     *   "page"         : 1,      // page courante
     *   "totalPages"   : 5,      // nombre total de pages
     *   "totalResults" : 42,     // nombre total de résultats sans pagination
     *   "limit"        : 10      // taille de page utilisée (nombre de pathologies par page)
     * }
     * void indique que cette méthode n'a pas de valeur de retour, elle envoie directement la réponse JSON au client et termine l'exécution avec exit.
     */
    public function getPathologies(): void  // appelée par fetch('/api/pathologies?' + p.toString()) dans scroll-infini.js qui attend une réponse JSON de la part de ce endpoint grâce au routage dans router.php
    {
        $this->requireAuth(); // ← ajouter cette ligne en premier

        // --- 1. Récupération des filtres depuis la query string (= paramètres de l'URL)  ---
        // null si absent (méridien est optionnel), '' si absent pour type et carac
        $meridien = $_GET['meridien'] ?? null;  
        $type     = $_GET['type']    ?? '';
        $carac    = $_GET['carac']   ?? '';
        $keyword   = $_GET['keyword'] ?? null; // nouveau paramètre de recherche par mot-clé, null si absent

        // Délègue à PathoModel::buildFilters() la traduction des paramètres
        // type+carac en codes SQL internes (ex: "me"+"e" → code colonne PostgreSQL)
        $filters = PathoModel::buildFilters($type, $carac, $meridien, $keyword);

        // --- 2. Calcul de la pagination ---
        // max(1, ...) évite une page 0 ou négative
        $page  = max(1, (int) ($_GET['page']  ?? 1));
        // Whitelist stricte : seules ces valeurs de limit sont acceptées
        $limit = (int) ($_GET['limit'] ?? 10);
        $limit = in_array($limit, [5, 10, 20, 50]) ? $limit : 10;   //verif si la valeur de limit est dans la whitelist, sinon défaut à 10
        // Nombre d'enregistrements à sauter pour atteindre la page demandée
        $offset = ($page - 1) * $limit; // ex: page 3, limit 10 → offset 20 (sauter les 20 premiers résultats pour afficher les résultats 21-30)

        // --- 3. Interrogation de la base de données ---
        $model        = new PathoModel();
        // Retourne uniquement la tranche de résultats correspondant à la page
        $pathologies  = $model->searchPathologies($filters, $limit, $offset);
        // Compte total sans LIMIT/OFFSET, nécessaire pour calculer totalPages
        $totalResults = $model->countPathologies($filters);
        $totalPages   = (int) ceil($totalResults / $limit);

        // --- 4. Envoi de la réponse JSON au client---
        // const res  = await fetch('/api/pathologies?' + p.toString()); dans home.html.twig qui attend une réponse JSON de la part de ce endpoint grâce au routage dans router.php
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'data'         => $pathologies,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'totalResults' => (int) $totalResults,
            'limit'        => $limit,
        ]);
    }
    
    public function getPathologyByID($id) { //appelée par fetch('/api/pathologie/' + id) dans modale-apercu.js qui attend une réponse JSON de la part de ce endpoint grâce au routage dans router.php
        $this->requireAuth(); // ← ajouter cette ligne en premier

        $model = new PathoModel();
        $pathoDesc = $model->getPathoDetails($id);
        if ($pathoDesc===false) { // gestion d'erreur d'id (expérience utilisateur)
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Erreur, id inconnu dans la base de donnée',
            ]);
            exit;
        }
        $symptomes = $model->getSymptomes($id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'nom'      => $pathoDesc["desc"],
            'meridien' => $pathoDesc["nom"],
            'type'     => $pathoDesc["type"],
            'element'  => $pathoDesc["element"],
            'yin'      => $pathoDesc["yin"],
            'code'     => $pathoDesc['code'],
            'symptome' => $symptomes,
        ]);
    }
}
