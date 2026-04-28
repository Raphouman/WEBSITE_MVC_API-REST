(function () {
    // --- Délégation d'événements sur le tbody ---
    // On écoute le parent (tbody) plutôt que chaque bouton,
    // car les lignes peuvent être ajoutées dynamiquement par le scroll infini.
    const tbody = document.querySelector('.patho-table-section tbody');
    tbody.addEventListener('click', function (e) {
        const bouton = e.target.closest('button[data-id]');
        if (!bouton) return;
        if (bouton.disabled) return;
        // ici, bouton.dataset.id contient l'ID de la pathologie
        loadModale(bouton.dataset.id);
    });
    // ---- Gestion de la fermeture ----
    const overlay = document.getElementById('modale-overlay');
    // cas du bouton croix
    const close_button = document.getElementById('modale-fermer');
    close_button.addEventListener('click', function (e) {
        overlay.setAttribute('hidden', '');
    });
    // cas du click sur l'overlay
    overlay.addEventListener('click', function (e) {
        if (e.target !== overlay) return ;
        overlay.setAttribute('hidden', '');
    });
    // cas de la touche esc
    document.addEventListener('keydown', function (e) {
        if (e.key!=='Escape') return;
        if (overlay.hasAttribute('hidden')) return;
        overlay.setAttribute('hidden', '');
    })

    
    /**
     * Construit un élément <li> pour un symptôme.
     * Utilise textContent (pas innerHTML) donc pas besoin d'échappement XSS.
     *
     * @param {string} symptome - Description du symptôme
     * @returns {HTMLLIElement} L'élément <li> prêt à être inséré dans la liste
     */
    function buildSymptomeLi(symptome) {
        const li = document.createElement('li');
        li.textContent = symptome;
        return li;
    }

    /**
     * Charge les détails d'une pathologie via l'API REST
     * et remplit la modale d'aperçu rapide.
     *
     * Étapes :
     *  1. Requête GET /api/pathologies/{id}
     *  2. Remplissage des champs de la modale avec les données JSON
     *  3. Construction dynamique de la liste des symptômes
     *  4. Mise à jour du lien vers la fiche complète
     *  5. Affichage de la modale (retrait de l'attribut hidden)
     *
     * @param {string} id - L'identifiant de la pathologie (issu de data-id)
     */
    async function loadModale(id) { //appelé lors du click sur un bouton "Aperçu rapide"
        try { // /api/pathologies? ==> ROUTEUR ==> ApiController::getPathologies() qui retourne du JSON
            const token = localStorage.getItem('api_token');
            const res = await fetch('/api/pathologies/' + id, {
                headers: token ? { 'Authorization': 'Bearer ' + token } : {},   // Bearer est un type de schéma d'authentification HTTP standard.
            }); //ici res contiendra la réponse JSON de l'API REST (liste de pathologies + info pagination)
            if (!res.ok) throw new Error('HTTP ' + res.status); // erreur HTTP → catch
    
            const json = await res.json();

            // Remplissage des champs :
            document.getElementById('modale-titre').textContent = json.nom;
            document.getElementById('modale-meridien').textContent = json.meridien;
            document.getElementById('modale-type').textContent = json.type;
            document.getElementById('modale-element').textContent = json.element;
            document.getElementById('modale-yin').textContent = json.yin;
            document.getElementById('modale-code').textContent = json.code; 

            // Construction de la liste des symptômes :
            const symptome_liste = document.getElementById('modale-symptomes-liste');
            symptome_liste.innerHTML = '';
            json.symptome.forEach(s => symptome_liste.appendChild(buildSymptomeLi(s.desc)));

            // Lien vers la fiche complète
            document.getElementById('modale-lien-detail').href = '/detail?id=' + id + '&type=&carac=&meridien=';

            // Affichage de l'overlay :
            overlay.removeAttribute('hidden');

        } catch (e) {
            // En cas d'erreur réseau ou HTTP, on log sans bloquer l'interface
            console.error('Erreur chargement du modale :', e);
        }
    }
})();