<?php
class Router {
    public function handleRequest() {
        // récupération URL propre (parse_url est une fonction native de PHP qui permet d'extraire différentes parties d'une URL, ici on extrait juste le chemin demandé par l'utilisateur)
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);    //PHP_URL_PATH est une constante qui indique à parse_url de ne retourner que le chemin de l'URL, sans les paramètres de requête ni le fragment

        // ROUTING

        // Vérification des url de type /api/pathologies/id : // Important de le faire avant le switch qui ne peut pas vérifier des caractère dynamique donc l'id
        $pattern = '/^\/api\/pathologies\/(\d+)$/';
        if (preg_match($pattern, $uri, $matches) === 1) { // On utilise la fonction preg_match pour vérifier un pattern dans la chaine de caractère de l'url
            (new ApiController())->getPathologyByID($matches[1]);
            exit;
        } 
        
        switch ($uri) {                 // @TODO: methode statique en dynamique (créaton instance controller et appel fontion avec ->)

            case '/login':
                // AuthController::login();    //AVANT : appel la methode login de la classe AuthController (le :: indique que c'est une méthode statique, on n'a pas besoin d'instancier la classe pour l'appeler)
                (new AuthController())->login();   //APRES : on crée une instance de AuthController et on appelle la méthode login sur cette instance (le -> indique que c'est une méthode d'instance, on doit créer un objet de la classe pour l'appeler)
                break;

            case '/register':
                (new AuthController())->register();
                break;

            case '/logout':
                (new AuthController())->logout();
                break;
            
            case '/delete':
                (new AuthController())->delete();
                break;

            case '/api/pathologies':    //appelé dans scroll-infini.js // si l'utilisateur demande l'URL /api/pathologies (ex: http://localhost:8080/api/pathologies), on appelle la méthode getPathologies() de ApiController, qui retourne du JSON (pas de rendu Twig)
                (new ApiController())->getPathologies();
                break;


            case '/detail':
                (new DetailController())->showDetail();
                break;

            case '/':       // si l'utilisateur demande la racine du site (ex: http://localhost:8080/), on affiche la page d'accueil
            case '/home':   // si l'utilisateur demande la page d'accueil (ex: http://localhost:8080/home), on affiche la page d'accueil
            default:        // pour toute autre URL non reconnue, on affiche la page d'accueil (on pourrait aussi afficher une page 404, mais ici on redirige vers l'accueil pour simplifier)    
                (new HomeController())->showHome();
                break;
        }
    }

}