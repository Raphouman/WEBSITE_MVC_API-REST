<?php
// src/Service/Database.php
// Ce fichier sert à définir la classe Database qui gère la connexion à la base de données
// Utilisation de PDO pour la connexion à la base de données PostgreSQL getConnection() qui retourne une instance de PDO

// src/Service/Database.php

class Database {
    private static $pdo = null;
    public static function getConnection() {
        try {
            if (self::$pdo !== null) {
                return self::$pdo;
            }
            // 1. On récupère les identifiants injectés par Docker (via le .env et le docker-compose)
            // L'astuce " ?: 'valeur' " permet de mettre une valeur par défaut si getenv() échoue
            $host = getenv('DB_HOST') ?: 'postgres'; 
            $db   = getenv('DB_DB')   ?: 'tidal';   //pourquoi pas acudb ?
            $user = getenv('DB_USER'); 
            $pass = getenv('DB_PASSWORD');
            // les VE sont stockés dans docker-compose.yml et injectés dans le conteneur via le .env (ce qui permet de ne pas hardcoder les identifiants de connexion dans le code, ce qui est plus sécurisé et plus flexible pour différents environnements)

            // 2. On construit la chaîne de connexion (DSN) dynamiquement
            $dsn = "pgsql:host=$host;port=5432;dbname=$db";

            // 3. On lance la connexion avec les vrais identifiants
            $pdo = new PDO($dsn, $user, $pass);

            // 4. Options de sécurité et de formatage
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            self::$pdo = $pdo;  // on stocke avant de retourner
            return self::$pdo;

        } catch (PDOException $e) {
            die("Erreur BDD : " . $e->getMessage());
        }
    }
    public static function sqlRequest($sql, $params=[], $fetchType='all') {
        try {
            $pdo = Database::getConnection();
            $sth = $pdo->prepare($sql);
            $sth->execute($params);
            switch ($fetchType) {
                case 'all':
                    return $sth->fetchAll(PDO::FETCH_ASSOC);
                case 'one':
                    return $sth->fetch(PDO::FETCH_ASSOC);
                case 'column':
                    return $sth->fetchColumn();
                case 'exec':
                    return true;
                default:
                    error_log("Erreur dans sqlRequest, fetchType inconnu : " . $fetchType);
                    return false;
            }
        }
        catch (PDOException $e) {
            error_log("Erreur dans sqlRequest dans Database.php : " . $e->getMessage()); 
            switch ($fetchType) {
                case 'all':
                    return [];
                case 'one':
                    return false;
                case 'column':
                    return 0;
                case 'exec':
                    return false;
                default:
                    error_log("Erreur dans sqlRequest, fetchType inconnu : " . $fetchType);
                    return false;
            }
        }
    }
}

?>
