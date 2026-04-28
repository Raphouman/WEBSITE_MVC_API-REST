<?php
// src/Model/User.php
// Ce fichier servait à la base à définir la classe User qui représente un utilisateur du site. Attribut + Constructeur
// Ce ficher N'EST PAS UTILE !!!!

class User {
    public $id; //pour la session, on stocke l'id de l'utilisateur connecté, pas l'identifiant (pour éviter de stocker des données sensibles en session)
    public $identifiant;    
    public $password;

    public function __construct($id, $identifiant, $password) {
        $this->id = $id;
        $this->identifiant = $identifiant;
        $this->password = $password;
    }
}
?>