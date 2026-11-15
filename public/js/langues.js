/**
 * Menu des langues du pied de page (<details class="langues">) : se referme
 * quand on clique ailleurs ou qu'on appuie sur Échap. Sans ce script, il
 * s'ouvre et se ferme quand même par un clic sur la langue affichée.
 */
(() => {
    const menu = document.querySelector('details.langues');
    if (!menu) return;
    document.addEventListener('click', (e) => {
        if (menu.open && !menu.contains(e.target)) menu.open = false;
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
})();
