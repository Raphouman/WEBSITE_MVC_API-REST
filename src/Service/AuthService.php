<?php
// src/Service/AuthService.php
// Service d'authentification : login, register, logout, check login (state) ==> gère la session
// Parler à la bdd via Database.php
// SERVICE ==> aucun HTML ici (c'est le controller qui gère ça)

require_once __DIR__ . '/Database.php';

class AuthService {

    // LOGIN
    public static function login($identifiant, $password) {

        $sql = "SELECT * FROM users WHERE identifiant = :identifiant";
        $params[':identifiant']  = $identifiant;
        $fetchType = 'one';

        $user = Database::sqlRequest($sql, $params, $fetchType);     // récupération de l'utilisateur (sous forme de tableau associatif) correspondant à l'identifiant fourni, pour pouvoir ensuite vérifier le mot de passe hashé

        // vérifie utilisateur + mot de passe hashé
        if ($user && password_verify($password, $user['password'])) {   //si user existe et que le mot de passe fourni correspond au hash stocké en bdd (password_verify compare le mot de passe fourni avec le hash stocké)

            if (session_status() === PHP_SESSION_ACTIVE) {  //PHP_SESSION_ACTIVE est maj avec la fonction native PHP session_start(), ce qui permet de vérifier si une session est déjà active avant d'essayer de démarrer une nouvelle session. Cela évite les erreurs liées à l'appel de session_start() plusieurs fois dans le même script.
                session_regenerate_id(true);
            }

            // session ==> persistance login
            $_SESSION['user'] = $user['id'];        //user stockera l'id de l'utilisateur connecté, ce qui permettra d'identifier l'utilisateur lors des requêtes suivantes.
            $_SESSION['identifiant'] = $user['identifiant'];    //identifiant stocké en session pour pouvoir l'afficher dans la barre de navigation (ex: "Bienvenue, [identifiant]") ou pour d'autres fonctionnalités liées à l'identifiant de l'utilisateur.

            return true;
        }

        return false;
    }

    // REGISTER
    public static function register($identifiant, $password) {

        // hash du mot de passe ==> sécurité obligée
        $hash = password_hash($password, PASSWORD_DEFAULT); //utilise l'algo de hashage par défaut (actuellement bcrypt) pour hasher le mot de passe fourni avant de le stocker en bdd, afin d'améliorer la sécurité en cas de fuite de données. 
        // Le hashage est un processus unidirectionnel qui rend impossible de retrouver le mot de passe original à partir du hash, ce qui protège les utilisateurs même si la bdd est compromise.

        $sql = "INSERT INTO users (identifiant, password) VALUES (:identifiant, :password)";
        $params = [
            ':identifiant' => $identifiant,
            ':password' => $hash,
        ]; 

        return Database::sqlRequest($sql, $params, 'exec');
    }

    //  LOGOUT
    public static function logout() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {   // Si les sessions utilisent des cookies, alors on efface le cookie de session pour s'assurer que la session est complètement détruite côté client, en plus de la destruction côté serveur.
            $cookieParams = session_get_cookie_params();
            setcookie(
                session_name(),             // récupère le nom du cookie de session (généralement "PHPSESSID") pour s'assurer que le cookie correct est supprimé
                '',                         // valeur vide pour supprimer le cookie
                time() - 42000,             // met une date d'expiration dans le passé pour supprimer le cookie
                $cookieParams['path'],      // utilise les mêmes paramètres que le cookie de session original pour s'assurer que le cookie est correctement supprimé
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }

        session_destroy();  //session courante détruite ==> l'utilisateur est déconnecté
    }



    //  DELETE USER
    public static function deleteUser(): bool {
        if (!isset($_SESSION['user'])) {    // vérifie si l'utilisateur est connecté avant de tenter de supprimer le compte, pour éviter des suppressions accidentelles ou malveillantes de comptes sans authentification. Si l'utilisateur n'est pas connecté, la fonction retourne false pour indiquer que la suppression n'a pas été effectuée.
            return false;
        }

        $userId = $_SESSION['user']; // ne peut supprimer que son propre compte, car l'id de l'utilisateur connecté est récupéré à partir de la session, ce qui empêche un utilisateur de supprimer le compte d'un autre utilisateur en manipulant les paramètres de la requête ou en accédant à une URL spécifique.

        $sql = "DELETE FROM users WHERE id = :id";
        $params[':id'] = $userId;

        Database::sqlRequest($sql, $params, 'exec');
        self::logout(); // La session de l'utilisateur est detruite automatiquement.
        return true;
    }


    //  CHECK LOGIN
    public static function isLogged() { //appelée dans le homeController pour vérifier si l'utilisateur est connecté pour que home.html.twig puisse autoriser ou non l'accès à la barre de recherche par ex
        return isset($_SESSION['user']);    // vérifie si la session contient une clé 'user' (qui est définie lors du login), ce qui indique que l'utilisateur est connecté. Si la clé existe, la fonction retourne true, sinon false.
    }

    
}