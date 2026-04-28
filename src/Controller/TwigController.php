<?php

class TwigController {
    protected $loader;
    protected $twig;
    public function __construct() {
        $chemin = __DIR__ . '/../View';
        $this->loader = new \Twig\Loader\FilesystemLoader($chemin);
        $this->twig = new \Twig\Environment($this->loader, [
            'cache' => false, // Pratique pour le dev
            'debug' => true
        ]);
    }
    protected function render($template, $donnees = []) {
        $user_data = [
            'isLogged'   => AuthService::isLogged(),        // a remplacer par une verif de JWT ??
            'session_id' => $_SESSION['identifiant'] ?? null,
            'session_JWT' => $_SESSION['jwt'] ?? null,  
        ];
        $merged = array_merge($donnees, ['user_data' => $user_data]);   // grace a ce merge, les datas de session seront disponibles dans tous les templates Twig via la variable user_data (pas besoin de les passer à chaque controller)
        echo $this->twig->render($template, $merged);
    }
}