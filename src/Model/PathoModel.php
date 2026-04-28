<?php
// Model/PathoModel.php
// On inclut le service de base de données pour accéder à la connexion
require_once __DIR__ . '/../Service/Database.php';



class PathoModel {

    /** Tableau de correspondance type+carac → codes internes SQL */
    private static array $mapping = [
        // Combinaison type + carac
        'j'    => ['j'],
        'l2p'  => ['l2p'],
        'l2v'  => ['l2v'],
        'lp'   => ['lp'],
        'lv'   => ['lv'],
        'me'   => ['me'],
        'mi'   => ['mi'],
        'mva'  => ['mva'],
        'mvi'  => ['mvi'],
        'mvp'  => ['mvp'],
        'tfc'  => ['tfc'],
        'tff'  => ['tff'],
        'tfp'  => ['tfp'],
        'tfpc' => ['tfpc'],
        'tfpci' => ['tfpci'],
        'tfpcm' => ['tfpcm'],
        'tfpcs' => ['tfpcs'],
        'tfv' => ['tfv'],
        'tfv+' => ['tfv+'],
        'tfv-' => ['tfv-'],
        'tfvf' => ['tfvf'],
        'tfvfi' => ['tfvfi'],
        'tfvfm' => ['tfvfm'],
        'tfvfs' => ['tfvfs'],

        // Type seul
        'm'   => ['me', 'mi'],
        'l'   => ['lp', 'lv', 'l2p', 'l2v'],
        'tf'  => ['tfc', 'tff', 'tfpc', 'tfpci', 'tfpcm', 'tfpcs', 'tfv', 'tfv+', 'tfv-', 'tfvf', 'tfvfi', 'tfvfm', 'tfvfs'],  // @TODO : tfpc ...
        'mv'  => ['mv', 'mva', 'mvi', 'mvp'],

        // Carac seule
        'e'   => ['me'],
        'i'   => ['mi'],
        'p'   => ['l2p', 'lp', 'tfp', 'tfpc', 'tfpci', 'tfpcm', 'tfpcs'],
        'v'   => ['l2v', 'lv', 'tfv', 'tfv+', 'tfv-', 'tfvf', 'tfvfi', 'tfvfm', 'tfvfs'],
        'c'   => ['tfc', 'tfpc', 'tfpci', 'tfpcm', 'tfpcs'],
        'f'   => ['tff', 'tfvf', 'tfvfi', 'tfvfm', 'tfvfs'],
    ];

    private static array $typeOptions =[
        ''   => '— Tous les types —',
        'm'  => 'Méridiens',
        'tf' => 'Organe / Viscère (Tsang/Fu)',
        'l'  => 'Voies Luo',
        'mv' => 'Merveilleux vaisseaux',
        'j'  => 'Tendino-musculaire (Jing Jin)',
    ];

    private static array $caracOptions =[
        ''   => '— Toutes —',
        'p'  => 'Plein',
        'v'  => 'Vide',
        'c'  => 'Chaud',
        'f'  => 'Froid',
        'i'  => 'Interne',
        'e'  => 'Externe',
    ];

    private static array $caracByType =[
        ''   => ['p','v','c','f','i','e'],  // tous types → toutes carac
        'm'  => ['i','e'],                  // Méridien → interne/externe seulement
        'tf' => ['p','v','c','f'],          // Trouble fonctionnel → plein/vide/chaud/froid
        'l'  => ['v', 'p'],                 // Voies Luo → vide/plein/chaud/froid
        'mv' => [],                         // Merveilleux vaisseaux → aucune carac (filtre désactivé) ...
        'j'  => [],                         // Jing Jin → aucune carac (filtre désactivé) ...
    ];

    // Getters :
    public static function getTypeOptions() {
        return self::$typeOptions;
    }

    public static function getCaracOptions() {
        return self::$caracOptions;
    }
    public static function getCaracByType() {
        return self::$caracByType;
    }

    /**
     * Construit le tableau $filters à partir des paramètres bruts de l'URL.
     * Appelé par HomeController et ApiController
     *
     * @param string      $type     Valeur du paramètre GET "type"
     * @param string      $carac    Valeur du paramètre GET "carac"
     * @param string|null $meridien Valeur du paramètre GET "meridien"
     * @return array{type: array|null, meridien: string|null}
     */
    public static function buildFilters(?string $type, ?string $carac, ?string $meridien, ?string $keyword): array
    {
        $cle        = $type . $carac;   //concaténation des 2 paramètres pour chercher une correspondance dans le mapping

        $typeValues = self::$mapping[$cle]         // SELF = accès aux propriétés statiques de la classe, $mapping est le tableau de correspondance défini plus haut
                   ?? self::$mapping[$type]     // Si pas de correspondance type+carac, on essaie avec le type seul
                   ?? self::$mapping[$carac]    // Si pas de correspondance type seul, on essaie avec la carac seule
                   ?? null;

        return [
            'type'     => $typeValues,
            'meridien' => $meridien,
            'keyword'  => $keyword,
        ];
    }

    /**
     * Récupère les détails d'une pathologie précise par son ID
     * @param int $id L'identifiant de la pathologie (idP)
     * @return array|false Les données de la pathologie ou false si non trouvé
     */
    public function getPathoById($id) {
        $sql = "SELECT * FROM public.patho WHERE idP = :id";
        $params = [':id' => $id];
        $fetchType = 'one';
        return Database::sqlRequest($sql, $params, $fetchType);
    }

    /**
     * Recherche des pathologies en combinant plusieurs filtres dynamiquement
     * @param array $filters Tableau associatif contenant les critères (type, meridien, q)
     * ex de $filters : ['type' => 'Douleur', 'meridien' => 'Poumon']
     * @param int $limit Nombre de pathologie à insérer dans la page
     * @param int $offset Suivi du décalage des pathologies 
     * @return array Tableau des pathologies filtrées (idp, mer, type, desc)   
     */
    public function searchPathologies($filters, $limit, $offset) {
        

        // 1. On utilise l'astuce du WHERE 1=1 pour faciliter la construction dynamique
        $sql = 'SELECT idp, mer, "type", "desc" FROM public.patho WHERE 1=1';
        $params = []; // exemple de $params : [':type' => 'Douleur', ':meridien' => 'Poumon']

        if (!empty($filters['type'])){
            // Pour ['me', 'mi'] on veut générer : IN (:type0, :type1)
            $placeholders = [];
            foreach ($filters['type'] as $i => $valeur) {//$filters["type"] est la combinaison de $type et $carac dans le filtre de la page home.
                $placeholders[] = ':type' . $i;        // ':type0', ':type1'
                $params[':type' . $i] = $valeur;       // ':type0' => 'me', ':type1' => 'mi'
            }
            // Ne pas oublier l'espace avant AND
            $sql .= ' AND "type" IN (' . implode(', ', $placeholders) . ')';//implode = transforme un tableau en chaîne de caractères en utilisant le séparateur spécifié (ex: ', ')
        }
        if (!empty($filters['meridien'])){
            $sql .= " AND mer = :meridien";
            $params[':meridien'] = $filters['meridien'];   
        }

        // RECHERCHE PAR MOT-CLÉ
        if (!empty($filters['keyword'])) {
            $sql .= ' AND idp IN (  -- permet de filtrer DISTINCT (évite les doublons si plusieurs symptômes associés à la même pathologie correspondent au mot-clé)
                SELECT sp.idp FROM symptpatho sp
                JOIN keysympt ks ON ks.ids = sp.ids
                JOIN keywords k ON k.idk = ks.idk
                WHERE k.name ILIKE :keyword
            )';
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;
        
        $sql .= ' ORDER BY "type" ASC, idp ASC LIMIT :limit OFFSET :offset';// Espace indispensable avant ORDER BY

        $fetchType = 'all';

        return Database::sqlRequest($sql, $params, $fetchType);
    }

    /**
     * Calcul du nombre de pathologie recherché.
     * @param array $filters Tableau associatif contenant les critères (type, meridien, q)
     * ex de $filters : ['type' => 'Douleur', 'meridien' => 'Poumon']
     * @return int Nombre total de pathologies correspondant aux filtres
     */
    public function countPathologies($filters) {
        $sql = 'SELECT COUNT(*) FROM public.patho WHERE 1=1';
        $params = [];
        // 2. On vérifie la variable $filters (et on ajoute un espace avant AND)
        // :type réfère à un paramètre nommé dans la requête préparée
        if (!empty($filters['type'])){
            // Pour ['me', 'mi'] on veut générer : IN (:type0, :type1)
            $placeholders = [];
            foreach ($filters['type'] as $i => $valeur) {
                $placeholders[] = ':type' . $i;        // ':type0', ':type1'
                $params[':type' . $i] = $valeur;       // ':type0' => 'me', ':type1' => 'mi'
            }
            $sql .= ' AND "type" IN (' . implode(', ', $placeholders) . ')';
        }
        if (!empty($filters['meridien'])){
            $sql .= " AND mer = :meridien";
            $params[':meridien'] = $filters['meridien'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= ' AND idp IN (      -- permet de filtrer DISTINCT (évite les doublons si plusieurs symptômes associés à la même pathologie correspondent au mot-clé)
                SELECT sp.idp FROM symptpatho sp
                JOIN keysympt ks ON ks.ids = sp.ids
                JOIN keywords k ON k.idk = ks.idk
                WHERE k.name ILIKE :keyword
            )';
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }


        $fetchType = 'column';

        return (int) Database::sqlRequest($sql, $params, $fetchType);
    }

    /**
     * Récupération des details d'une pathologie par son id
     * @param int $id identifiant de la pathologie
     * @return array|false Detail de la pathologie
     */
    public function getPathoDetails ($id) {
        $sql = 'SELECT p.type, p.desc, m.nom, m.element, m.yin, m.code 
            FROM patho p 
            JOIN meridien m ON p.mer = m.code
            WHERE p.idp=:id';
        $params = [':id' => $id];
        $fetchType = 'one';
        return Database::sqlRequest($sql, $params, $fetchType);
    }

    /**
     * Récupération des symptomes associés à une pathologie par son id
     * @param int $id identifiant de la pathologie
     * @return array Tableau des symptomes
     */
    public function getSymptomes($id) {
        $sql = 'SELECT s.desc FROM symptpatho sp
            JOIN symptome s ON s.ids=sp.ids
            WHERE sp.idp=:id';
        $params = [':id' => $id];
        $fetchType = 'all';
        return Database::sqlRequest($sql, $params, $fetchType);
    }

    /**
     * Récupération des mots-clés associés à une pathologie par son id
     * @param int $id identifiant de la pathologie
     * @return array Tableau des mots-clés
     */
    public function getKeywords($id) {
        $sql = 'SELECT kw.name 
            FROM keywords kw
            JOIN keySympt ks ON ks.idk=kw.idk
            JOIN symptome s ON s.ids=ks.ids
            JOIN symptPatho sp ON sp.ids=s.ids
            WHERE sp.idp=:id';
        $params = [':id' => $id];
        $fetchType = 'all';
        return Database::sqlRequest($sql, $params, $fetchType);
    }

    /**
     * Recherche des pathologie aux extrémités de la pathologie courante
     * @param int $id identifiant de la pathologie
     * @param int $patho_type type courant de la pathologie
     * @param array $filters Tableau associatif contenant les critères (type, meridien, q)
     * @return array Tableau des mots-clés
     */
    public function getBorderPatho($id, $patho_type, $filters) {
        $sql_suivant = 'SELECT idp, "desc" FROM public.patho WHERE 1=1';
        $params = [
            ':id_courant' => $id,
            ':type_courant' => $patho_type,
        ];
        // On recherche les pathologies avec le filtre appliqué.
        if (!empty($filters['type'])){
            $placeholders = [];
            foreach ($filters['type'] as $i => $valeur) {
                $placeholders[] = ':type' . $i;        
                $params[':type' . $i] = $valeur;       
            }
            $sql_suivant .= ' AND "type" IN (' . implode(', ', $placeholders) . ')';
        }
        if (!empty($filters['meridien'])){
            $sql_suivant .= " AND mer = :meridien";
            $params[':meridien'] = $filters['meridien'];   
        }
        $sql_precedent = $sql_suivant;
        // On applique la logique pour trouvé le suivant et le précédent avec le filtre appliqué
        $sql_suivant .= ' AND ("type" > :type_courant OR ("type" = :type_courant AND idp > :id_courant))';
        $sql_precedent .= ' AND ("type" < :type_courant OR ("type" = :type_courant AND idp < :id_courant))';

        $sql_suivant .= ' ORDER BY "type" ASC, idp ASC LIMIT 1';
        $sql_precedent .= ' ORDER BY "type" DESC, idp DESC LIMIT 1';

        $fetchType = 'one';

        return [
            'patho_suivante' => Database::sqlRequest($sql_suivant, $params, $fetchType),
            'patho_precedente' => Database::sqlRequest($sql_precedent, $params, $fetchType),
        ];
    }

    /**
     * Recherche des pathologie aux extrémités de la pathologie courante
     * @return array Tableau trié des méridiens principaux et merveilleux
     */
    public function getMeridiensSorted() {
        $sql_principaux = 'SELECT code, nom FROM public.meridien WHERE element IS NOT NULL';
        $sql_merveilleux = 'SELECT code, nom FROM public.meridien WHERE element IS NULL';

        return [
            'meridiens_principaux' => Database::sqlRequest($sql_principaux),
            'meridiens_merveilleux' => Database::sqlRequest($sql_merveilleux),
        ];
    }
}