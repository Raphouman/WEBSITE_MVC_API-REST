<?php           

session_start();    // fonction native de PHP qui démarre une session (permet de stocker des données côté serveur pour chaque utilisateur, comme les informations de connexion)
// elle est évidemment INDEMPOTENTE, c'est à dire qu'on peut l'appeler plusieurs fois sans provoquer d'erreur (si la session est déjà démarrée, elle ne fera rien)

// moteur qui va permettre à PHP de trouver nos classes sans faire des require partout
spl_autoload_register(function ($classname) {
 
    $baseDir = __DIR__;

    $normalizedClassname = ltrim(str_replace('\\', '/', $classname), '/');  // on normalise le nom de la classe en remplaçant les backslashes par des slashes et en supprimant les éventuels slashes au début du nom de la classe (pour éviter les problèmes de chemins relatifs)

    $candidateFiles = [
        $baseDir . '/' . $normalizedClassname . '.php',
        $baseDir . '/Controller/' . $classname . '.php',
        $baseDir . '/Model/' . $classname . '.php',
        $baseDir . '/Service/' . $classname . '.php',
    ];

    foreach ($candidateFiles as $candidateFile) {
        if (is_file($candidateFile)) {
            require_once $candidateFile;
            return;
        }
    }

    $searchDirectories = [
        $baseDir,
        $baseDir . '/Controller',
        $baseDir . '/Model',
        $baseDir . '/Service',
    ];

    foreach ($searchDirectories as $directory) {
        foreach (glob($directory . '/*.php') as $candidateFile) {
            if (strcasecmp(pathinfo($candidateFile, PATHINFO_FILENAME), $classname) === 0) {
                require_once $candidateFile;
                return;
            }
        }
    }
});

$router = new Router(); //ca trouve la classe Router grâce à l'autoloader (spl_autoload_register) qui va chercher le fichier Router.php 
$router->handleRequest();   