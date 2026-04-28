// SCRIPT JS POUR LA GESTION DYNAMIQUE DES FILTRES
// API REST : on ne propose que les caractéristiques valides pour chaque type de pathologie sélectionnées

(function () {
    /**
    * Caractéristiques autorisées par type de pathologie.
    * Clé vide ('') = tous les types → toutes les carac affichées.
    */
  
    const form     = document.querySelector('.search-filter-bar');  
    const selType  = document.getElementById('filtre-type');
    const selCarac = document.getElementById('filtre-carac');
    const selMer   = document.getElementById('filtre-meridien'); // optional mais : on peut aussi soumettre le formulaire au changement de méridien, ce qui permet de conserver la logique "filtre les carac selon le type, puis soumet dès que tu changes un filtre" même pour le filtre méridien (ex: si on choisit un méridien spécifique, on peut vouloir n'afficher que les pathologies de ce méridien qui sont "Plein", etc.)

    if (!form || !selType || !selCarac) return;
    
    const caracMap = JSON.parse(selCarac.dataset.caracByType);
    
    /**
    * Masque/désactive les options de carac qui ne correspondent pas au type,
    * et remet le select à "Toutes" si la valeur courante n'est plus valide.
    */
    function updateCarac(typeVal) {
      const allowed = caracMap.hasOwnProperty(typeVal) ? caracMap[typeVal] : caracMap[''];  // carac autorisées pour le type sélectionné (ou toutes si type inconnu)
      const currentVal = selCarac.value;  // la valeur actuellement sélectionnée dans le select de carac
      let currentStillValid = (currentVal === '');  // la valeur actuelle est-elle toujours valide ? (si c'est "Toutes", elle est toujours valide)

      Array.from(selCarac.options).forEach(function (opt) {
        if (opt.value === '') return; // "— Toutes —" toujours visible
        const ok = allowed.includes(opt.value); // cette option est-elle autorisée pour le type sélectionné ?
        opt.hidden   = !ok; // masquer les options non autorisées
        opt.disabled = !ok; // désactiver les options non autorisées pour éviter qu'elles soient sélectionnables au clavier
        if (opt.value === currentVal && ok) currentStillValid = true;
      });
  
      if (!currentStillValid) selCarac.value = '';
  
      // Désactiver le select entier si aucune carac disponible (j, mv)
      selCarac.disabled = (allowed.length === 0);
    }
    
    // ── Initialisation au chargement (filtre déjà actif depuis l'URL) ──
    updateCarac(selType.value); //appel de la fonction d'updateCarac au chargement de la page pour que les carac soient déjà filtrées selon le type sélectionné dans l'URL (ex: si on arrive sur /?type=m, seules les carac "interne/externe" seront affichées dès le départ)
  
    // ── Changement de type : mise à jour des carac puis soumission ──
    selType.addEventListener('change', function () {
      updateCarac(selType.value);
      form.submit();
    });
  
    // ── Changement de carac ou méridien : soumission directe ──
    [selCarac, selMer].forEach(function (sel) {
      if (sel) sel.addEventListener('change', function () { form.submit(); });
    });
  })();