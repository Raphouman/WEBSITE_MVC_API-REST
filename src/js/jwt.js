// Ce fichier est chargé dans le template de base (base.html.twig) et est exécuté à chaque chargement de page.
// Son rôle est de récupérer le token JWT stocké en session côté serveur (via TwigController) et de le stocker côté client (localStorage) pour qu'il soit disponible pour les appels à l'API REST (ex: dans modale-apercu.js).


(function () {
    const token = document.querySelector('meta[name="api-token"]')?.content ?? null;
    if (token) {
        localStorage.setItem('api_token', token);
    } else {
        localStorage.removeItem('api_token');
    }
})();
