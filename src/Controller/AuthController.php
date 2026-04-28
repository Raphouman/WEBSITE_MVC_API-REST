<?php
// src/Controller/AuthController.php
// Contrôleur d'authentification : login, register, logout ==> gère les requêtes liées à l'authentification et délègue la logique métier à AuthService

// Controler : Recup les données GET/POST, (validation serveur), appel du service, envoie les données à Twig (View) pour affichage

// TODO : 
// Les controllers héritent de TwigController et peuvent utiliser la méthode render pour afficher les vues avec les données nécessaires
// Plus de require 



class AuthController extends TwigController {

    // LOGIN
    public function login() {   // grace a Twig ==> plus de function static
        $login_data = [
            'error_bool' => false,
            'error' => null,
            'identifiant' => '',    // pour pré-remplir le champ identifiant en cas d'erreur de mot de passe, afin d'améliorer l'expérience utilisateur en évitant de devoir ressaisir l'identifiant
            'remember_me' => false,
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {        //formulaire soumis

            $identifiant = trim($_POST['identifiant'] ?? ''); //recup du formulaire dans login.html.twig    // trim pour supprimer les espaces avant/après l'identifiant, ce qui peut éviter des erreurs de connexion dues à des espaces involontaires.
            $password = $_POST['password'] ?? '';

            // checkbox "remember me" 
            $rememberMe = isset($_POST['remember_me']);
            // pour pré-remplir le champ identifiant et cocher la case "remember me" en cas d'erreur de mot de passe, afin d'améliorer l'expérience utilisateur en évitant de devoir ressaisir ces informations
            $login_data['identifiant'] = $identifiant;
            $login_data['remember_me'] = $rememberMe;

            // validation serveur (obligatoire)
            if (empty($identifiant) || empty($password)) {  
                http_response_code(400);
                $error = "Tous les champs sont obligatoires";
            }

            //Si OK ==> delegation au SERVICE d'authentification (métier)
            elseif (AuthService::login($identifiant, $password)) {
                $tokenData = JwtUtils::newAccessToken(['identifiant' => $identifiant]); //tokenData contient : access_token, issued, expires
                $_SESSION['jwt'] = $tokenData['access_token']; // disponible côté serveur

                header("Location: /"); // redirection accueil
                exit;
                
            } else {
                http_response_code(401);
                $error = "Identifiant ou mot de passe incorrect";   //HTML : echo ==> PHP : $error & require
            }

            if ($error !== null) {
                $login_data['error_bool'] = true;
                $login_data['error'] = $error;
            }
        }
        $this->render('home/login.html.twig', $login_data); // Twig : un seul point de rendu pour GET et erreurs POST
        //dans login.html.twig, logindata.error_bool et login_data.error seront utilisés pour afficher le message d'erreur si nécessaire, et login_data.identifiant et login_data.remember_me pour pré-remplir le formulaire en cas d'erreur de connexion.
    }

    // REGISTER
    public function register() {
        $register_data = [
            'error_bool' => false,
            'error' => null,
            'identifiant' => '',
        ];
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $identifiant = trim($_POST['identifiant'] ?? '');   // trim pour supprimer les espaces avant/après l'identifiant, ce qui peut éviter des erreurs d'inscription dues à des espaces involontaires.
            $password = $_POST['password'] ?? '';

            $register_data['identifiant'] = $identifiant;

            if (empty($identifiant) || empty($password)) {
                $error = "Tous les champs sont obligatoires";
            }

            elseif (AuthService::register($identifiant, $password)) {
                // echo "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                header("Location: /login");
                exit;
            } else {
                $error = "Identifiant déjà utilisé";
            }

            if ($error !== null) {
                $register_data['error_bool'] = true;
                $register_data['error'] = $error;
            }
        }
        $this->render('home/register.html.twig', $register_data);   // si pas de formulaire soumis alors on affiche la page
    }

    // LOGOUT
    public function logout() {
        AuthService::logout();  // délégation au service d'authentification pour la logique de déconnexion (ex: destruction de session)
        header("Location: /");  // redirection accueil après déconnexion, header() pour refaire une nouvelle requete GET sur la page d'accueil (pour éviter que l'utilisateur puisse revenir à la page précédente avec le bouton "précédent" du navigateur et voir des données qui devraient être protégées après la déconnexion)
        exit;
    }

    // DELETE ACCOUNT
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {    // pour éviter que l'utilisateur puisse supprimer son compte en accédant simplement à une URL (ex: /delete) via une requête GET, ce qui pourrait être dangereux et entraîner des suppressions accidentelles de comptes. En limitant cette action aux requêtes POST, on s'assure que la suppression du compte est intentionnelle et provient d'une interaction utilisateur spécifique (ex: clic sur un bouton de suppression dans un formulaire), ce qui améliore la sécurité de l'application.
            header("Location: /");
            exit;
        }

        if (!AuthService::isLogged()) {
            header("Location: /login");
            exit;
        }

        AuthService::deleteUser();  // supprime le compte en bdd et détruit la session
        header("Location: /");
        exit;
    }
}