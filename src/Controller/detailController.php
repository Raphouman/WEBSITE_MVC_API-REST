<?php
// src/Controller/detailController.php
// Contrôleur pour la page des détail des pathologies

// Classe du controleur.

class DetailController extends TwigController{

    public function showDetail() {

        // 1. Récupération de l'id et des attributs du filtres depuis l'URL
        $id = $_GET['id'] ?? null;
        if ($id === null || !ctype_digit($id)) {
            header('Location: /');
            exit;
        }
        $type = $_GET['type'] ?? null;
        $carac = $_GET['carac'] ?? null;
        $meridien = $_GET['meridien'] ?? null;
        $keyword = $_GET['keyword'] ?? null;


        // 2. Appel au Modèle 
        $model = new PathoModel();
        $pathoDesc = $model->getPathoDetails($id);
        if ($pathoDesc===false) { // gestion d'erreur d'id (expérience utilisateur)
            http_response_code(404);
            $this->render('layout/404.html.twig');
            exit;
        }
        $symptomes = $model->getSymptomes($id);
        $keywords = $model->getKeywords($id);

        $filters = PathoModel::buildFilters($type, $carac, $meridien, $keyword);
        $borderPatho = $model->getBorderPatho($id, $pathoDesc["type"], $filters);

        // 3. On prépare les données des détails de la pathologie.
        $patho_data = [
            'nom'      => $pathoDesc["desc"],
            'meridien' => $pathoDesc["nom"],
            'type'     => $pathoDesc["type"],
            'element'  => $pathoDesc["element"],
            'yin'      => $pathoDesc["yin"],
            'code'     => $pathoDesc['code'],
            'symptome' => $symptomes,
            'keyword'  => $keywords,
            'patho_suivante'  => $borderPatho["patho_suivante"],
            'patho_precedente'  => $borderPatho["patho_precedente"],
            'filtre_type' => $type,
            'filtre_carac' => $carac,
            'filtre_meridien' => $meridien,
        ];

        // 4. On demande à Twig de rendre le template avec ces données
        $this->render('home/detail.html.twig', ['patho_data' => $patho_data,]);
    }
}