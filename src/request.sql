/* On veut lister toutes les requêtes dont on aura besoin */
/*CE FICHIER N'EST PAS UTILE*/

/*-------------------------------------- CONNEXION ---------------------------------------- */
-- TABLE UTILISATEUR (création)
-- Créer si n'existe pas
-- GRANT : donne des permissions à un utilisateur de la base de données (ici "acu")
-- SERIAL (select on sequence): auto-increment pour la colonne id, ce qui permet d'avoir un identifiant unique pour chaque utilisateur sans avoir à le gérer manuellement
docker compose exec -T postgres psql -U postgres -d acudb -c "CREATE TABLE IF NOT EXISTS public.users (id SERIAL PRIMARY KEY, identifiant TEXT UNIQUE NOT NULL, password TEXT NOT NULL); GRANT SELECT, INSERT, UPDATE ON public.users TO acu; GRANT USAGE, SELECT ON SEQUENCE public.users_id_seq TO acu;"

-- REQUÊTE : récupérer un utilisateur
SELECT * FROM users WHERE identifiant = :identifiant;

-- REQUÊTE : créer un utilisateur
docker compose exec -T postgres psql -U postgres -d acudb -c "INSERT INTO public.users (identifiant, password) VALUES (:identifiant, :password);"


/*-------------------------------------- MOT CLE ---------------------------------------- */
-- REQUÊTE : récupérer les pathologies associées à un mot-clé
SELECT p.idp
FROM patho as p
WHERE p.idp IN ( -- permet de DISTINCT
    SELECT sp.idp FROM symptpatho as sp
    JOIN keysympt as ks ON ks.idks = sp.idks
    JOIN keywords as k ON k.idk = ks.idk
    WHERE k.name ILIKE :keyword
);

-- Pour vérifier dans la base de données :
SELECT 
    p.idp,
    k.name
FROM patho AS p
JOIN symptpatho AS sp ON sp.idp = p.idp
JOIN keysympt   AS ks ON ks.ids = sp.ids
JOIN keywords   AS k  ON k.idk  = ks.idk
WHERE k.name ILIKE '%douleur%';
