<?php
// src/Controller/homeController.php
// Contrôleur pour la page d'accuei

class HomeController extends TwigController {

    public function showHome() {
        // 1. La session est déjà démarrée dans index.php (SPE), 
        // donc pas besoin de le refaire ici. C'est l'avantage du point d'entrée unique !

        // 2. Récupération des filtres depuis l'URL
        $meridien = $_GET['meridien'] ?? '';
        $type     = $_GET['type']    ?? '';
        $carac    = $_GET['carac']   ?? '';
        
        //isset permet de vérifier si un mot clé est présent dans l'URL, et si oui, on le prend en compte seulement si l'utilisateur est connecté (grâce à la vérification de $_SESSION['user_id']), sinon on le considère comme vide pour éviter les problèmes de sécurité liés à une injection SQL ou une fuite d'informations pour les utilisateurs non connectés.
        $keyword   = isset($_SESSION['user']) ? ($_GET['keyword'] ?? '') : '';  //si jamais quand on ets pas connecté, on copie colle l'URL d'une page qui a un mot clé !

        // La logique de mapping type+carac → codes SQL est centralisée dans PathoModel
        $filters = PathoModel::buildFilters($type, $carac, $meridien, $keyword);
        
        // Paramètres pour la pagination :
        $page = (int) ($_GET['page']  ?? 1);    //récupérée depuis l'URL, envoyé quand on clique sur les liens de pagination
        $limit = (int) ($_GET['limit'] ?? 10);
        $limit = in_array($limit, [5, 10, 20, 50]) ? $limit : 10;
        $offset = ($page - 1) * $limit;

        // 3. Appel au Modèle (L'autoloader trouvera PathoModel tout seul)

        $model = new PathoModel();

        $pathologies = $model->searchPathologies($filters, $limit, $offset);

        $totalResults = $model->countPathologies($filters);
        $totalPages = (int) ceil($totalResults / $limit);
        $allMeridens = $model->getMeridiensSorted();

        // 4. Préparation des données pour Twig

        // Données spécifiques à la page d'accueil (filtres, pagination, liste)
        $home_data = [
            // Paramètres de filtre :
            'keyword'      => $keyword,
            'type'         => $type,
            'carac'        => $carac,
            'meridien'     => $meridien,
            // Paramètres de pagination :
            'page'         => $page,
            'total_pages'   => $totalPages,
            'limit'        => $limit,
            'total_results' => $totalResults,
            // Contenu :
            'pathologies'           => $pathologies,
            'meridiens_principaux'  => $allMeridens["meridiens_principaux"],
            'meridiens_merveilleux' => $allMeridens["meridiens_merveilleux"],
            'type_options'          => PathoModel::getTypeOptions(),
            'carac_options'         => PathoModel::getCaracOptions(),
            'carac_by_type'         => PathoModel::getCaracByType(),
        ];
        // pas besoin de render à base.html.twig, on peut directement render home.html.twig qui hérite de base.html.twig
        $this->render('home/home.html.twig', ['home_data' => $home_data,]);
    }
}