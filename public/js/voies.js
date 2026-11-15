/**
 * Liste des voies (accueil et page d'un secteur) :
 *  - filtres : n'affiche (et n'envoie) que la plage de difficulté de l'échelle choisie ;
 *  - icônes : taille réglée au curseur ;
 *  - liste : miniatures affichées ou non.
 * Taille et miniatures sont retenues par le navigateur (préférences de ce visiteur).
 */
(() => {
    const lire = (cle) => { try { return localStorage.getItem(cle); } catch (e) { return null; } };
    const ecrire = (cle, valeur) => { try { localStorage.setItem(cle, valeur); } catch (e) { /* stockage indisponible */ } };

    // Panneau « Partager » (accueil) : se ferme en touchant ailleurs ou avec Échap
    const partage = document.querySelector('details.partage');
    if (partage) {
        document.addEventListener('click', (e) => {
            if (partage.open && !partage.contains(e.target)) partage.open = false;
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && partage.open) {
                partage.open = false;
                partage.querySelector('summary').focus();
            }
        });
    }

    const choixSysteme = document.querySelector('[data-filtre-systeme]');
    if (choixSysteme) {
        const majPlages = () => document.querySelectorAll('.plage').forEach(f => {
            const actif = f.dataset.systeme === choixSysteme.value;
            f.hidden = !actif;
            f.querySelectorAll('select').forEach(s => { s.disabled = !actif; });
        });
        choixSysteme.addEventListener('change', majPlages);
        majPlages();
    }

    const curseur = document.getElementById('taille-icones');
    const grille = document.getElementById('grille-voies');
    if (curseur && grille) {
        const appliquer = () => grille.style.setProperty('--taille-icone', curseur.value + 'px');
        const memorisee = parseInt(lire('voies.tailleIcones'), 10);
        if (memorisee >= +curseur.min && memorisee <= +curseur.max) curseur.value = memorisee;
        appliquer();
        curseur.addEventListener('input', appliquer);
        curseur.addEventListener('change', () => ecrire('voies.tailleIcones', curseur.value));
    }

    const caseMiniatures = document.getElementById('afficher-miniatures');
    const table = document.getElementById('table-voies');
    if (caseMiniatures && table) {
        caseMiniatures.checked = lire('voies.miniatures') === '1';
        const appliquer = () => table.classList.toggle('avec-miniatures', caseMiniatures.checked);
        appliquer();
        caseMiniatures.addEventListener('change', () => {
            appliquer();
            ecrire('voies.miniatures', caseMiniatures.checked ? '1' : '0');
        });
    }
})();
