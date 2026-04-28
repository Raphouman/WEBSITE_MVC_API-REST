/**
       * IIFE (Immediately Invoked Function Expression) : tout le code est encapsulé
       * dans une fonction anonyme auto-exécutée pour éviter de polluer le scope global.
       * Aucune variable interne n'est accessible depuis l'extérieur.
       * Si on nomme la fonction (ex: scroll_infini()), elle est accessible dans le scope global, ce qui peut causer des conflits si d'autres scripts utilisent le même nom.
       */
(function () {

    // --- Références DOM (Document Object Model) utilisées tout au long du script ---
    const tbody    = document.querySelector('.patho-table-section tbody'); // corps du tableau de pathologies, récupérée dans home.html.twig
    const sentinel = document.getElementById('scroll-sentinel');           // div déclencheur du scroll
    const loader   = document.getElementById('scroll-loader');             // indicateur de chargement ("Chargement…")
    const errorBox = document.getElementById('scroll-error');              // zone d'erreur (ex: 401 non connecté) dans _scroll-infini.html.twig

    // --- État interne du scroll infini ---
    // Initialisé depuis les data-attributes de la sentinelle (valeurs injectées par Twig).
    // parseInt(..., 10) force la base décimale pour éviter les surprises avec les zéros initiaux.
    let currentPage = parseInt(sentinel.dataset.page, 10);       // numéro de la dernière page chargée
    let totalPages  = parseInt(sentinel.dataset.totalPages, 10); // nombre total de pages disponibles
    let loading     = false; // verrou : empêche les requêtes simultanées (double-fetch)

    // --- Paramètres de filtre de base ---
    // Construits une seule fois depuis les data-attributes.
    // URLSearchParams sérialise automatiquement les valeurs (encodage URL).
    // Ces paramètres sont réutilisés dans chaque requête AJAX et dans la pagination.
    const baseParams = new URLSearchParams({
      type:     sentinel.dataset.type,  //récupérée dans scroll_infini.html.twig via home_data dans HomeController.php
      carac:    sentinel.dataset.carac,
      meridien: sentinel.dataset.meridien,
      limit:    sentinel.dataset.limit,
    });

    /**
     * Échappe les caractères HTML spéciaux d'une chaîne.
     * Utilisé avant toute insertion via innerHTML pour neutraliser
     * les données JSON provenant de l'API (XSS côté client).
     *
     * Principe : on assigne la valeur à textContent (qui n'interprète pas le HTML),
     * puis on lit innerHTML (qui retourne la version encodée).
     *
     * @param {string|null|undefined} str - La chaîne à échapper
     * @returns {string} La chaîne avec les entités HTML encodées
     */
    function escapeHtml(str) {
      const d = document.createElement('div');
      d.textContent = str ?? ''; // ?? '' : remplace null/undefined par une chaîne vide
      return d.innerHTML;
    }

    /**
     * Construit un élément <tr> DOM à partir d'un objet pathologie reçu en JSON. <tr> est plus sémantique que <div> pour une ligne de tableau, et permet une meilleure accessibilité.
     * Chaque cellule :
     *   - Col 1 : nom de la pathologie, lien cliquable vers /detail?id=...
     *   - Col 2 : badge coloré selon le type (classe CSS dynamique)
     *   - Col 3 : méridien associé
     *   - Col 4 : bouton "Détails" (second lien vers /detail)
     *
     * encodeURIComponent(patho.idP) : sécurise l'identifiant dans l'URL.
     * escapeHtml(...) : sécurise les textes affichés dans le HTML.
     *
     * @param {Object} patho - Objet pathologie issu de l'API REST
     * @returns {HTMLTableRowElement} La ligne <tr> prête à être insérée
     */
    function buildRow(patho) {  // appelée dans loadNextPage() pour chaque pathologie 
      // Colonne 1 :
      const td1 = document.createElement('td');
      const a = document.createElement('a');
      a.setAttribute('class', "row-link")
      const href = '/detail?id=' + encodeURIComponent(patho.idP) + 
          '&type=' + encodeURIComponent(baseParams.get('type')) + 
          '&carac=' + encodeURIComponent(baseParams.get('carac')) +
          '&meridien=' + encodeURIComponent(baseParams.get('meridien'));
      a.setAttribute('href', href);
      a.textContent = patho.desc;
      td1.appendChild(a);
      
      // Colonne 2 :
      const typeClass = (patho.type ?? '').toLowerCase().replace(/ /g, '-');
      const td2 = document.createElement('td');
      const span1 = document.createElement('span');
      span1.setAttribute('class', "badge badge-type-"+typeClass);
      span1.textContent = patho.type;
      td2.appendChild(span1);

      // Colonne 3 :
      const td3 = document.createElement('td');
      const span2 = document.createElement('span');
      span2.setAttribute('class', "meridien-yin");
      span2.textContent = patho.mer;
      td3.appendChild(span2);

      // Colonne 4 :
      const td4 = document.createElement('td');
      const button = document.createElement('button');
      button.setAttribute('data-id', patho.idp);
      button.setAttribute('class', "btn btn-ghost btn-sm");
      button.textContent = "Aperçu";
      td4.appendChild(button);

      // Final :
      const tr = document.createElement('tr');
      tr.appendChild(td1);
      tr.appendChild(td2);
      tr.appendChild(td3);
      tr.appendChild(td4);
      return tr;  
    }


    /**
     * Reconstruit le HTML de la pagination après chaque chargement par scroll.
     *
     * Logique d'affichage :
     * - Si toutes les pages sont chargées (current >= total) → on masque la nav.
     * - Page courante (i === current) → span non-cliquable (indicateur de position).
     * - Toutes les autres pages → lien href avec rechargement complet côté serveur.
     * - Bouton ‹ désactivé sur la page 1, sinon lien vers current - 1.
     * - Bouton › pointe vers la prochaine page non chargée.
     *
     * @param {number} current - Numéro de la dernière page chargée par scroll
     * @param {number} total   - Nombre total de pages
     */
    function renderPagination(current, total) { //appelé à la fin de loadNextPage() pour mettre à jour la pagination après chaque chargement
      const nav = document.getElementById('pagination-nav');
      if (!nav) return; // sécurité : la nav peut ne pas exister (totalPages <= 1)

      // Toutes les pages sont dans le DOM → pagination inutile, on la cache
      if (current >= total) {
        nav.style.display = 'none';
        return;
      }


      /**
       * Génère l'URL d'une page en réutilisant les filtres actifs (baseParams).
       * @param {number} n - Numéro de page cible
       * @returns {string} URL de la forme /?page=N&type=...&carac=...
       */
      function pageHref(n) {
        const p = new URLSearchParams(baseParams); // copie des paramètres de base
        p.set('page', n);
        return '/?' + p.toString();
      }

      let html = '';

      // Bouton ‹ : désactivé sur la page 1, sinon lien vers la page précédente
      if (current > 1) {
        html += `<a href="${pageHref(current - 1)}" aria-label="Page précédente">‹</a>`;
      } else {
        html += '<span aria-disabled="true">‹</span>';
      }

      // Génère un bouton clicable par page grace à pageHref(i) si non current
      for (let i = 1; i <= total; i++) {
        if (i === current) {
          // Page courante → indicateur de position non-cliquable
          html += `<span class="current" aria-current="page" aria-label="Page ${i}, page actuelle">${i}</span>`;
        } else {
          // Toutes les autres pages (précédentes ou suivantes) → lien cliquable
          html += `<a href="${pageHref(i)}" aria-label="Page ${i}">${i}</a>`;
        }
      }

      // Bouton › vers la prochaine page non encore chargée
      html += `<a href="${pageHref(current + 1)}">›</a>`;

      nav.innerHTML = html;
      nav.style.display = ''; // réaffiche la nav si elle avait été masquée
    }






    /**
     * Charge la page suivante via l'API REST et l'insère dans le tableau.
     *
     * Étapes :
     *  1. Vérifie que le chargement est possible (pas déjà en cours, pages restantes).
     *  2. Affiche le loader.
     *  3. Requête GET /api/pathologies?page=N&type=...&carac=...
     *  4. Supprime l'éventuelle ligne "Aucune pathologie" (ligne vide initiale).
     *  5. Ajoute les nouvelles lignes dans le tbody.
     *  6. Met à jour l'état interne (currentPage, totalPages).
     *  7. Redessine la pagination.
     *  8. Masque le loader (même en cas d'erreur, via finally).
     */
    async function loadNextPage() {
      // Verrou : évite les requêtes concurrentes si le scroll est rapide
      if (loading || currentPage >= totalPages) return;
      loading = true;   // verrou de chargement activé
      //loader.style.display = 'block'; // affiche "Chargement…" 

      // Prépare les paramètres de la requête : filtres + numéro de page suivante
      const p = new URLSearchParams(baseParams);
      p.set('page', currentPage + 1);

      try { // /api/pathologies? ==> ROUTEUR ==> ApiController::getPathologies() qui retourne du JSON
        const token = localStorage.getItem('api_token');
        const res = await fetch('/api/pathologies?' + p.toString(), { //ici res contiendra la réponse JSON de l'API REST (liste de pathologies + info pagination)
          headers: token ? { 'Authorization': 'Bearer ' + token } : {}, // Bearer est un type de schéma d'authentification HTTP standard.
        }); // pour le scroll infini on vérifie la présence du token dans localStorage et on l'envoie dans le header Authorization si disponible (selon si l'API REST nécessite une authentification ou pas)
        if (res.status === 401) {
          observer.disconnect(); // inutile de continuer à surveiller
          errorBox.textContent = 'Vous devez être connecté pour utiliser la fonctionnalité "scroll infini".';
          errorBox.style.display = 'block';
          return;
        }
        if (!res.ok) throw new Error('HTTP ' + res.status); // erreur HTTP → catch

        const json = await res.json();
        // Réponse attendue : { data: [...], page: N, totalPages: N }

        // Supprime la ligne "Aucune pathologie trouvée" si elle est toujours là
        // (identifiée par la présence d'un td avec colspan)
        const emptyRow = tbody.querySelector('td[colspan]');
        if (emptyRow) emptyRow.closest('tr').remove();

        // Insère chaque pathologie reçue comme nouvelle ligne dans le tableau
        json.data.forEach(patho => tbody.appendChild(buildRow(patho)));

        // Met à jour l'état interne avec les valeurs retournées par l'API
        // (l'API fait autorité sur la pagination, pas le client)
        currentPage = json.page;
        totalPages  = json.totalPages;

        // Redessine la pagination pour refléter les pages maintenant chargées
        renderPagination(currentPage, totalPages);

      } catch (e) {
        // En cas d'erreur réseau ou HTTP, on log sans bloquer l'interface
        console.error('Erreur chargement pathologies :', e);
      } finally {
        // Toujours exécuté : relâche le verrou et masque le loader
        loading = false;
        loader.style.display = 'none';
      }
    }

    // ---------------------------- SURVEILLANCE DE LA SENTINELLE AVEC INTERSECTION OBSERVER ----------------------------
    // --- INTERSECTION OBSERVER : déclencheur du scroll infini ---
    //
    // IntersectionObserver surveille la visibilité de la sentinelle dans le viewport.
    // Quand elle devient visible (l'utilisateur a scrollé jusqu'en bas), on déclenche
    // le chargement de la page suivante.
    //
    // Debounce (500 ms) : évite de lancer plusieurs requêtes si le scroll est saccadé
    // ou si la sentinelle entre/sort rapidement du viewport. On annule le timer
    // précédent à chaque changement d'état.
    //
    // threshold: 0.5 → le callback se déclenche dès que 50 % de la sentinelle est visible.
    // rootMargin: '0px' → pas de marge supplémentaire (déclenchement au bord exact du viewport).
    let debounceTimer = null;
    const observer = new IntersectionObserver(
      function (entries) {
        if (entries[0].isIntersecting) { 
          // La sentinelle est visible → planifie le chargement dans 500 ms
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(loadNextPage, 500);
          loader.style.display = 'block'; // affiche "Chargement…" immédiatement pour un feedback rapide
        } else {
          // La sentinelle a quitté le viewport → annule le chargement planifié
          clearTimeout(debounceTimer);
        }
      },
      { rootMargin: '0px', threshold: 0.5 }
    );

    // Lance la surveillance de la sentinelle
    observer.observe(sentinel);

  })(); 