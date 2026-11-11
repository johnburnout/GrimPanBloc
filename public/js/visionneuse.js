/**
 * Visionneuse de la photo d'une voie (page de consultation) : zoom, déplacement
 * et plein écran, sans outils de marquage.
 *
 * La figure rendue par voie/_photo.html.twig (classe « grande ») reste affichée
 * telle quelle sans JavaScript ; ce script la transforme en vue zoomable :
 * l'image et le SVG des prises passent dans une « scène » à la taille réelle de
 * la photo, placée avec translate() scale().
 *
 * Gestes :
 *  - deux doigts : zoom et déplacement ;
 *  - double toucher / double-clic : zoome sur ce point, ou revient à la photo entière ;
 *  - un doigt (ou la souris) : déplace l'image une fois zoomée ; à 1× (hors plein
 *    écran), un doigt fait défiler la page comme d'habitude ;
 *  - curseur vertical, +, −, « voir toute la photo », plein écran ;
 *  - Ctrl + molette (ou pincement du pavé tactile) ; en plein écran, la molette seule.
 */
(() => {
    const ZOOM_MAX = 8;          // le curseur va de 2^0 à 2^3
    const ZOOM_DOUBLE = 2.5;     // zoom d'un double toucher
    const SEUIL_GLISSE = 6;      // px d'écran avant qu'un toucher devienne un glissé
    const DELAI_DOUBLE = 350;    // ms entre les deux touchers d'un double toucher
    const DISTANCE_DOUBLE = 30;  // px d'écran entre les deux touchers

    const COMMANDES = `
        <div class="zoom" role="group" aria-label="Zoom">
            <button type="button" data-zoom="plus" aria-label="Zoomer">+</button>
            <input type="range" min="0" max="3" step="0.01" value="0" aria-label="Niveau de zoom">
            <button type="button" data-zoom="moins" aria-label="Dézoomer">−</button>
            <button type="button" data-zoom="ajuster" title="Voir toute la photo" aria-label="Voir toute la photo">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="12" rx="1.5"/></svg>
            </button>
            <button type="button" data-plein-ecran title="Plein écran" aria-label="Plein écran" aria-pressed="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/></svg>
            </button>
        </div>`;

    const borner = (v, min, max) => Math.max(min, Math.min(max, v));

    const initialiser = (figure) => {
        const img = figure.querySelector('img');
        const svgPrises = figure.querySelector('svg');
        if (!img || !svgPrises) return;
        // Dimensions de la photo : celles du viewBox, connues avant le chargement de l'image
        const [, , W, H] = svgPrises.getAttribute('viewBox').trim().split(/[\s,]+/).map(Number);
        if (!W || !H) return;

        // Épaisseurs d'origine des traits, ramenées à l'écran selon le zoom
        const traits = [...svgPrises.querySelectorAll('.prise')].map(c => ({
            cercle: c,
            largeur: parseFloat(c.getAttribute('stroke-width')),
            tirets: c.getAttribute('stroke-dasharray')?.trim().split(/[\s,]+/).map(Number) ?? null,
        }));

        // --- Construction de la vue ------------------------------------------------

        const vue = document.createElement('div');
        vue.className = 'vue';
        const scene = document.createElement('div');
        scene.className = 'scene';
        scene.style.width = `${W}px`;
        scene.style.height = `${H}px`;
        img.draggable = false;
        scene.append(img, svgPrises);
        vue.appendChild(scene);
        vue.insertAdjacentHTML('beforeend', COMMANDES);
        figure.appendChild(vue);
        figure.classList.add('visionneuse');

        const curseur = vue.querySelector('.zoom input');
        const boutonPleinEcran = vue.querySelector('[data-plein-ecran]');

        // --- Zoom et déplacement -----------------------------------------------------

        let echelleBase = 1, zoom = 1, tx = 0, ty = 0;
        let largeurVue = 0, hauteurVue = 0;
        const echelle = () => echelleBase * zoom;
        const pleinEcranActif = () => figure.classList.contains('plein-ecran');

        const contraindre = () => {
            const w = W * echelle(), h = H * echelle();
            tx = w <= largeurVue ? (largeurVue - w) / 2 : borner(tx, largeurVue - w, 0);
            ty = h <= hauteurVue ? (hauteurVue - h) / 2 : borner(ty, hauteurVue - h, 0);
        };

        const appliquer = () => {
            contraindre();
            scene.style.transform = `translate(${tx}px, ${ty}px) scale(${echelle()})`;
            curseur.value = Math.log2(zoom);
            vue.classList.toggle('zoomee', zoom > 1.01);
            traits.forEach(({ cercle, largeur, tirets }) => {
                cercle.setAttribute('stroke-width', largeur / zoom);
                if (tirets) cercle.setAttribute('stroke-dasharray', tirets.map(t => t / zoom).join(' '));
            });
        };

        const zoomerEn = (nouveau, cx = largeurVue / 2, cy = hauteurVue / 2) => {
            const ix = (cx - tx) / echelle(), iy = (cy - ty) / echelle();
            zoom = borner(nouveau, 1, ZOOM_MAX);
            tx = cx - ix * echelle();
            ty = cy - iy * echelle();
            appliquer();
        };

        /** Hauteur hors plein écran : celle de la photo, sans dépasser 80 % de l'écran. */
        const dimensionner = () => {
            if (pleinEcranActif()) {
                vue.style.height = '';
            } else {
                const ideale = vue.clientWidth * H / W;
                vue.style.height = `${Math.round(borner(ideale, Math.min(240, ideale), window.innerHeight * 0.8))}px`;
            }
        };

        /** Recalcule l'échelle d'ajustement en gardant le même centre et le même zoom. */
        const recadrer = () => {
            const l = vue.clientWidth, h = vue.clientHeight;
            if (!l || !h) return;
            const centre = largeurVue
                ? { x: (largeurVue / 2 - tx) / echelle(), y: (hauteurVue / 2 - ty) / echelle() }
                : { x: W / 2, y: H / 2 };
            largeurVue = l;
            hauteurVue = h;
            echelleBase = Math.min(l / W, h / H);
            tx = l / 2 - centre.x * echelle();
            ty = h / 2 - centre.y * echelle();
            appliquer();
        };

        dimensionner();
        new ResizeObserver(recadrer).observe(vue);
        window.addEventListener('resize', dimensionner);

        curseur.addEventListener('input', () => zoomerEn(2 ** parseFloat(curseur.value)));
        vue.querySelectorAll('[data-zoom]').forEach(b => b.addEventListener('click', () => {
            const action = b.dataset.zoom;
            if (action === 'plus') zoomerEn(zoom * 1.5);
            else if (action === 'moins') zoomerEn(zoom / 1.5);
            else { zoom = 1; appliquer(); }
        }));

        vue.addEventListener('wheel', (ev) => {
            if (!ev.ctrlKey && !pleinEcranActif()) return; // sinon la molette fait défiler la page
            ev.preventDefault();
            const r = vue.getBoundingClientRect();
            zoomerEn(zoom * Math.exp(-ev.deltaY * 0.01), ev.clientX - r.left, ev.clientY - r.top);
        }, { passive: false });

        // --- Plein écran -------------------------------------------------------------

        const elementPleinEcran = () => document.fullscreenElement || document.webkitFullscreenElement || null;

        const basculer = (actif) => {
            figure.classList.toggle('plein-ecran', actif);
            document.body.classList.toggle('plein-ecran-actif', actif);
            boutonPleinEcran.setAttribute('aria-pressed', actif ? 'true' : 'false');
            boutonPleinEcran.title = actif ? 'Quitter le plein écran' : 'Plein écran';
            dimensionner();
        };

        boutonPleinEcran.addEventListener('click', () => {
            if (pleinEcranActif()) {
                if (elementPleinEcran()) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                basculer(false);
                return;
            }
            const demander = figure.requestFullscreen || figure.webkitRequestFullscreen;
            basculer(true);
            if (demander) {
                try {
                    const promesse = demander.call(figure);
                    if (promesse && promesse.catch) promesse.catch(() => { /* repli CSS déjà actif */ });
                } catch (e) { /* repli CSS déjà actif */ }
            }
        });

        const finPleinEcranNatif = () => { if (!elementPleinEcran() && pleinEcranActif()) basculer(false); };
        document.addEventListener('fullscreenchange', finPleinEcranNatif);
        document.addEventListener('webkitfullscreenchange', finPleinEcranNatif);
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && pleinEcranActif() && !elementPleinEcran()) basculer(false);
        });

        // --- Gestes ----------------------------------------------------------------------

        const pointeurs = new Map(); // pointerId → {x, y} en coordonnées client
        let geste = null;
        let dernierToucher = null;

        const debutPincement = () => {
            const [a, b] = [...pointeurs.values()];
            const r = vue.getBoundingClientRect();
            const cx = (a.x + b.x) / 2 - r.left, cy = (a.y + b.y) / 2 - r.top;
            geste = {
                type: 'pincement',
                distance: Math.hypot(a.x - b.x, a.y - b.y) || 1,
                zoom,
                ix: (cx - tx) / echelle(),
                iy: (cy - ty) / echelle(),
            };
        };

        vue.addEventListener('pointerdown', (ev) => {
            if (ev.target.closest('.zoom')) return;
            if (ev.pointerType === 'mouse' && ev.button !== 0) return;
            ev.preventDefault();
            try { vue.setPointerCapture(ev.pointerId); } catch (e) { /* pointeur déjà relâché */ }
            pointeurs.set(ev.pointerId, { x: ev.clientX, y: ev.clientY });

            if (pointeurs.size === 2) {
                dernierToucher = null;
                debutPincement();
            } else if (pointeurs.size === 1) {
                geste = {
                    type: 'glisse', bouge: false, souris: ev.pointerType === 'mouse',
                    depart: { x: ev.clientX, y: ev.clientY }, tx, ty, defilement: window.scrollY,
                };
            }
        });

        vue.addEventListener('pointermove', (ev) => {
            if (!pointeurs.has(ev.pointerId)) return;
            pointeurs.set(ev.pointerId, { x: ev.clientX, y: ev.clientY });
            if (!geste) return;

            if (geste.type === 'pincement') {
                if (pointeurs.size < 2) return;
                const [a, b] = [...pointeurs.values()];
                const r = vue.getBoundingClientRect();
                const cx = (a.x + b.x) / 2 - r.left, cy = (a.y + b.y) / 2 - r.top;
                zoom = borner(geste.zoom * Math.hypot(a.x - b.x, a.y - b.y) / geste.distance, 1, ZOOM_MAX);
                tx = cx - geste.ix * echelle();
                ty = cy - geste.iy * echelle();
                appliquer();
                return;
            }

            const dx = ev.clientX - geste.depart.x, dy = ev.clientY - geste.depart.y;
            if (!geste.bouge && Math.hypot(dx, dy) < SEUIL_GLISSE) return;
            geste.bouge = true;
            if (zoom > 1.01 || pleinEcranActif()) {
                tx = geste.tx + dx;
                ty = geste.ty + dy;
                appliquer();
            } else if (!geste.souris) {
                // Photo entière à l'écran : un doigt fait défiler la page.
                window.scrollTo(window.scrollX, geste.defilement - dy);
            }
        });

        const finPointeur = (ev) => {
            if (!pointeurs.has(ev.pointerId)) return;
            pointeurs.delete(ev.pointerId);
            if (!geste) return;

            if (geste.type === 'pincement') {
                if (pointeurs.size === 1) {
                    // Il reste un doigt : il continue de déplacer l'image, sans saut.
                    const [p] = [...pointeurs.values()];
                    geste = { type: 'glisse', bouge: true, souris: false, depart: p, tx, ty, defilement: window.scrollY };
                } else if (pointeurs.size === 0) {
                    geste = null;
                }
                return;
            }
            if (pointeurs.size > 0) return;

            if (ev.type === 'pointerup' && !geste.bouge) {
                const maintenant = performance.now();
                const double = dernierToucher
                    && maintenant - dernierToucher.t < DELAI_DOUBLE
                    && Math.hypot(ev.clientX - dernierToucher.x, ev.clientY - dernierToucher.y) < DISTANCE_DOUBLE;
                if (double) {
                    const r = vue.getBoundingClientRect();
                    if (zoom > 1.01) { zoom = 1; appliquer(); }
                    else zoomerEn(ZOOM_DOUBLE, ev.clientX - r.left, ev.clientY - r.top);
                }
                dernierToucher = double ? null : { x: ev.clientX, y: ev.clientY, t: maintenant };
            }
            geste = null;
        };
        vue.addEventListener('pointerup', finPointeur);
        vue.addEventListener('pointercancel', finPointeur);
    };

    document.querySelectorAll('.photo-voie.grande').forEach(initialiser);
})();
