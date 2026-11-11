/**
 * Éditeur de voie : marquage des prises sur une photo.
 *
 * Les prises sont stockées en pourcentage : x et y par rapport à la largeur et à
 * la hauteur de l'image, le rayon par rapport à la largeur. Elles sont envoyées
 * au serveur en JSON dans le champ caché "voie[prises]".
 *
 * La photo est affichée dans une « vue » qui peut être zoomée et déplacée :
 * la « scène » (image + SVG des prises) garde la taille réelle de l'image et
 * reçoit une transformation translate() scale().
 *
 * Gestes :
 *  - double toucher / double-clic sur la photo : pose une prise du type choisi ;
 *  - un doigt sur une prise : la déplace ; simple toucher : lui donne le type choisi
 *    et en fait la cible du curseur « Taille » ;
 *  - un doigt (ou la souris) à côté des prises : déplace l'image ;
 *  - deux doigts : zoom et déplacement ; molette + Ctrl (ou pincement du pavé
 *    tactile) : zoom ; en plein écran, la molette seule zoome.
 */
(() => {
    const SVG = 'http://www.w3.org/2000/svg';
    const ZOOM_MAX = 8;               // le curseur va de 2^0 à 2^3
    const SEUIL_GLISSE = 6;           // px d'écran avant qu'un toucher devienne un glissé
    const DELAI_DOUBLE = 350;         // ms entre les deux touchers d'un double toucher
    const DISTANCE_DOUBLE = 30;       // px d'écran entre les deux touchers
    const TOLERANCE = 22;             // px d'écran autour d'une prise pour la saisir

    const editeur = document.getElementById('editeur');
    const vide = document.getElementById('editeur-vide');
    const vue = document.getElementById('vue');
    const scene = document.getElementById('scene');
    const barre = document.getElementById('barre-outils');
    const poignee = document.getElementById('poignee');
    const boutonReplier = document.getElementById('replier');
    const zoomCurseur = document.getElementById('zoom-curseur');
    const boutonPleinEcran = document.getElementById('plein-ecran');
    const champPrises = document.getElementById('voie_prises');
    const selectPhoto = document.getElementById('voie_photo');
    const inputFichier = document.getElementById('voie_nouvellePhoto');
    const blocUpload = document.getElementById('bloc-upload');
    const curseurTaille = document.getElementById('taille-prise');
    const compteur = document.getElementById('nb-prises');
    const boutonsOutils = document.querySelectorAll('[data-outil]');

    let prises = [];
    let historique = [];
    let outil = 'main';
    let W = 1000, H = 1000; // dimensions réelles de l'image
    let svg = null;
    let derniere = null; // dernière prise posée, touchée ou déplacée : le curseur de taille la redimensionne

    // Vue : échelle d'ajustement (photo entière visible), zoom relatif, translation en px
    let echelleBase = 1, zoom = 1, tx = 0, ty = 0;
    let largeurVue = 0, hauteurVue = 0;

    // --- Données -----------------------------------------------------------

    try {
        const init = JSON.parse(champPrises.value || '[]');
        if (Array.isArray(init)) prises = init;
    } catch (e) { /* champ vide ou invalide */ }

    const memoriser = () => {
        historique.push(JSON.stringify(prises));
        if (historique.length > 100) historique.shift();
    };

    const synchroniser = () => {
        champPrises.value = JSON.stringify(prises.map(p => ({
            x: +p.x.toFixed(2), y: +p.y.toFixed(2), r: +p.r.toFixed(2), type: p.type,
        })));
        compteur.textContent = prises.length;
        dessiner();
    };

    // --- Vue : zoom et déplacement ---------------------------------------------

    const echelle = () => echelleBase * zoom;
    const borner = (v, min, max) => Math.max(min, Math.min(max, v));

    /** Garde l'image dans la vue : centrée si elle est plus petite, sans bord vide sinon. */
    const contraindre = () => {
        const w = W * echelle(), h = H * echelle();
        tx = w <= largeurVue ? (largeurVue - w) / 2 : borner(tx, largeurVue - w, 0);
        ty = h <= hauteurVue ? (hauteurVue - h) / 2 : borner(ty, hauteurVue - h, 0);
    };

    const appliquer = () => {
        contraindre();
        scene.style.transform = `translate(${tx}px, ${ty}px) scale(${echelle()})`;
        zoomCurseur.value = Math.log2(zoom);
        majTraits();
    };

    /** Zoome en gardant fixe le point (cx, cy) de la vue. */
    const zoomerEn = (nouveau, cx = largeurVue / 2, cy = hauteurVue / 2) => {
        const ix = (cx - tx) / echelle(), iy = (cy - ty) / echelle();
        zoom = borner(nouveau, 1, ZOOM_MAX);
        tx = cx - ix * echelle();
        ty = cy - iy * echelle();
        appliquer();
    };

    const pleinEcranActif = () => editeur.classList.contains('plein-ecran');

    /** Hauteur de la vue hors plein écran : celle de la photo, sans dépasser 80 % de l'écran. */
    const dimensionner = () => {
        if (vue.hidden) return;
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
        placerBarre();
    };

    new ResizeObserver(recadrer).observe(vue);
    window.addEventListener('resize', dimensionner);

    zoomCurseur.addEventListener('input', () => zoomerEn(2 ** parseFloat(zoomCurseur.value)));
    document.querySelectorAll('[data-zoom]').forEach(b => b.addEventListener('click', () => {
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

    // --- Plein écran ---------------------------------------------------------
    // API Fullscreen quand elle existe ; sinon (iPhone) la vue occupe la fenêtre en CSS.

    const elementPleinEcran = () => document.fullscreenElement || document.webkitFullscreenElement || null;

    const basculerClasse = (actif) => {
        editeur.classList.toggle('plein-ecran', actif);
        document.body.classList.toggle('plein-ecran-actif', actif);
        boutonPleinEcran.setAttribute('aria-pressed', actif ? 'true' : 'false');
        boutonPleinEcran.title = actif ? 'Quitter le plein écran' : 'Plein écran';
        dimensionner();
    };

    boutonPleinEcran.addEventListener('click', () => {
        if (pleinEcranActif()) {
            if (elementPleinEcran()) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            basculerClasse(false);
            return;
        }
        const demander = editeur.requestFullscreen || editeur.webkitRequestFullscreen;
        basculerClasse(true);
        if (demander) {
            try {
                const promesse = demander.call(editeur);
                if (promesse && promesse.catch) promesse.catch(() => { /* repli CSS déjà actif */ });
            } catch (e) { /* repli CSS déjà actif */ }
        }
    });

    const finPleinEcranNatif = () => { if (!elementPleinEcran() && pleinEcranActif()) basculerClasse(false); };
    document.addEventListener('fullscreenchange', finPleinEcranNatif);
    document.addEventListener('webkitfullscreenchange', finPleinEcranNatif);
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape' && pleinEcranActif() && !elementPleinEcran()) basculerClasse(false);
    });

    // --- Barre d'outils flottante ---------------------------------------------

    let barreX = 8, barreY = 8;

    const placerBarre = () => {
        barreX = borner(barreX, 0, Math.max(0, vue.clientWidth - barre.offsetWidth));
        barreY = borner(barreY, 0, Math.max(0, vue.clientHeight - barre.offsetHeight));
        barre.style.left = `${barreX}px`;
        barre.style.top = `${barreY}px`;
    };

    poignee.addEventListener('pointerdown', (ev) => {
        ev.preventDefault();
        poignee.setPointerCapture(ev.pointerId);
        const decalage = { x: ev.clientX - barreX, y: ev.clientY - barreY };
        const bouger = (e) => { barreX = e.clientX - decalage.x; barreY = e.clientY - decalage.y; placerBarre(); };
        const lacher = () => {
            poignee.removeEventListener('pointermove', bouger);
            poignee.removeEventListener('pointerup', lacher);
            poignee.removeEventListener('pointercancel', lacher);
        };
        poignee.addEventListener('pointermove', bouger);
        poignee.addEventListener('pointerup', lacher);
        poignee.addEventListener('pointercancel', lacher);
    });

    boutonReplier.addEventListener('click', () => {
        const repliee = barre.classList.toggle('repliee');
        boutonReplier.setAttribute('aria-expanded', repliee ? 'false' : 'true');
        boutonReplier.textContent = repliee ? '▾' : '▴';
        boutonReplier.title = repliee ? 'Déplier la barre d\'outils' : 'Replier la barre d\'outils';
        placerBarre();
    });

    // --- Photo -------------------------------------------------------------

    const chargerPhoto = (src) => {
        scene.innerHTML = '';
        svg = null;
        vide.hidden = !!src;
        vue.hidden = !src;
        if (!src) return;

        const img = new Image();
        img.alt = 'Photo de la zone';
        img.draggable = false;
        img.onload = () => {
            W = img.naturalWidth;
            H = img.naturalHeight;
            scene.style.width = `${W}px`;
            scene.style.height = `${H}px`;
            svg = document.createElementNS(SVG, 'svg');
            svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
            svg.setAttribute('preserveAspectRatio', 'none');
            scene.appendChild(svg);
            zoom = 1;
            largeurVue = 0; // recadrage centré sur la nouvelle photo
            dimensionner();
            recadrer();
            dessiner();
        };
        img.src = src;
        scene.appendChild(img);
    };

    if (selectPhoto) {
        selectPhoto.addEventListener('change', () => {
            const option = selectPhoto.selectedOptions[0];
            const src = option ? option.dataset.src : null;
            blocUpload.hidden = !!src;
            if (src) inputFichier.value = '';
            chargerPhoto(src);
        });
    }

    inputFichier.addEventListener('change', () => {
        const fichier = inputFichier.files[0];
        if (!fichier) return;
        if (selectPhoto) selectPhoto.value = '';
        chargerPhoto(URL.createObjectURL(fichier));
    });

    // --- Dessin ------------------------------------------------------------

    const el = (nom, attrs) => {
        const n = document.createElementNS(SVG, nom);
        for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, v);
        return n;
    };

    /** Épaisseur des traits constante à l'écran, quel que soit le zoom. */
    const majTraits = () => {
        if (!svg) return;
        const trait = W * 0.005 / zoom;
        svg.querySelectorAll('.prise').forEach(c => {
            c.setAttribute('stroke-width', trait);
            if (c.classList.contains('prise-pied')) {
                c.setAttribute('stroke-dasharray', `${W * 0.012 / zoom} ${W * 0.008 / zoom}`);
            }
        });
    };

    const dessiner = () => {
        if (!svg) return;
        svg.innerHTML = '';
        const defs = el('defs', {});
        const masque = el('mask', { id: 'masque-edition' });
        masque.appendChild(el('rect', { width: W, height: H, fill: 'white' }));
        prises.forEach(p => masque.appendChild(el('circle', {
            cx: p.x / 100 * W, cy: p.y / 100 * H, r: p.r / 100 * W, fill: 'black',
        })));
        defs.appendChild(masque);
        svg.appendChild(defs);
        svg.appendChild(el('rect', { width: W, height: H, fill: 'rgba(0,0,0,0.45)', mask: 'url(#masque-edition)' }));
        prises.forEach(p => svg.appendChild(el('circle', {
            class: `prise prise-${p.type}${p === derniere ? ' selection' : ''}`,
            cx: p.x / 100 * W, cy: p.y / 100 * H, r: p.r / 100 * W,
        })));
        majTraits();
    };

    // --- Interaction sur la photo ----------------------------------------------

    /** Point de la vue (px) → position sur la photo en %. */
    const position = (clientX, clientY) => {
        const r = vue.getBoundingClientRect();
        return {
            x: borner((clientX - r.left - tx) / echelle() / W * 100, 0, 100),
            y: borner((clientY - r.top - ty) / echelle() / H * 100, 0, 100),
        };
    };

    /** Index de la prise sous le point, ou -1. Tolérance minimale en px d'écran pour le tactile. */
    const priseSous = ({ x, y }) => {
        const tolerance = TOLERANCE / (W * echelle()) * 100; // en % de largeur
        let meilleur = -1, distMin = Infinity;
        prises.forEach((p, i) => {
            const dx = x - p.x;
            const dy = (y - p.y) * H / W; // ramené en % de largeur
            const d = Math.hypot(dx, dy);
            if (d <= Math.max(p.r, tolerance) && d < distMin) { meilleur = i; distMin = d; }
        });
        return meilleur;
    };

    const selectionner = (p) => {
        derniere = p;
        curseurTaille.value = p.r;
    };

    const pointeurs = new Map(); // pointerId → {x, y} en coordonnées client
    let geste = null;
    let dernierToucher = null;   // pour reconnaître le double toucher

    const ecartDepart = (ev) => Math.hypot(ev.clientX - geste.depart.x, ev.clientY - geste.depart.y);

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
        if (!svg || ev.target.closest('.barre-outils, .zoom')) return;
        if (ev.pointerType === 'mouse' && ev.button !== 0) return;
        ev.preventDefault();
        try { vue.setPointerCapture(ev.pointerId); } catch (e) { /* pointeur déjà relâché */ }
        pointeurs.set(ev.pointerId, { x: ev.clientX, y: ev.clientY });

        if (pointeurs.size === 2) {
            // Le deuxième doigt transforme le geste en zoom / déplacement de l'image.
            if (geste && geste.type === 'prise' && geste.bouge) synchroniser();
            dernierToucher = null;
            debutPincement();
            return;
        }
        if (pointeurs.size > 2) return;

        const pt = position(ev.clientX, ev.clientY);
        const i = priseSous(pt);
        const depart = { x: ev.clientX, y: ev.clientY };

        if (outil === 'gomme' && i >= 0) {
            memoriser();
            prises.splice(i, 1);
            synchroniser();
            geste = { type: 'aucun' };
            return;
        }
        geste = i >= 0
            ? { type: 'prise', index: i, bouge: false, depart }
            : { type: 'image', bouge: false, depart, tx, ty };
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

        if (!geste.bouge && ecartDepart(ev) < SEUIL_GLISSE) return;

        if (geste.type === 'prise') {
            if (!geste.bouge) {
                memoriser();
                selectionner(prises[geste.index]);
            }
            geste.bouge = true;
            Object.assign(prises[geste.index], position(ev.clientX, ev.clientY));
            synchroniser();
        } else if (geste.type === 'image') {
            geste.bouge = true;
            tx = geste.tx + ev.clientX - geste.depart.x;
            ty = geste.ty + ev.clientY - geste.depart.y;
            appliquer();
        }
    });

    const finPointeur = (ev) => {
        if (!pointeurs.has(ev.pointerId)) return;
        pointeurs.delete(ev.pointerId);
        if (!geste) return;
        const lache = ev.type === 'pointerup';

        if (geste.type === 'pincement') {
            if (pointeurs.size === 1) {
                // Il reste un doigt : il continue de déplacer l'image, sans saut.
                const [p] = [...pointeurs.values()];
                geste = { type: 'image', bouge: true, depart: p, tx, ty };
            } else if (pointeurs.size === 0) {
                geste = null;
            }
            return;
        }
        if (pointeurs.size > 0) return;

        if (lache && geste.type === 'prise' && !geste.bouge) {
            // Simple toucher sur une prise : type de l'outil courant, et cible du curseur de taille.
            const p = prises[geste.index];
            if (p.type !== outil) { memoriser(); p.type = outil; }
            selectionner(p);
            synchroniser();
        } else if (lache && geste.type === 'image' && !geste.bouge) {
            const maintenant = performance.now();
            const double = dernierToucher
                && maintenant - dernierToucher.t < DELAI_DOUBLE
                && Math.hypot(ev.clientX - dernierToucher.x, ev.clientY - dernierToucher.y) < DISTANCE_DOUBLE;
            if (double && outil !== 'gomme') {
                memoriser();
                const p = { ...position(ev.clientX, ev.clientY), r: parseFloat(curseurTaille.value), type: outil };
                prises.push(p);
                derniere = p;
                synchroniser();
            }
            dernierToucher = double ? null : { x: ev.clientX, y: ev.clientY, t: maintenant };
        }
        geste = null;
    };
    vue.addEventListener('pointerup', finPointeur);
    vue.addEventListener('pointercancel', finPointeur);

    // --- Outils ----------------------------------------------------------------

    boutonsOutils.forEach(b => b.addEventListener('click', () => {
        outil = b.dataset.outil;
        boutonsOutils.forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
        placerBarre(); // la barre repliée change de largeur
    }));

    // Le curseur de taille règle aussi la dernière prise posée, touchée ou déplacée, tant
    // qu'elle existe (effacée ou annulée, elle n'est plus dans la liste et n'est plus concernée).
    let reglageEnCours = false;
    curseurTaille.addEventListener('input', () => {
        if (!derniere || !prises.includes(derniere)) return;
        if (!reglageEnCours) { memoriser(); reglageEnCours = true; } // un seul « Annuler » par réglage
        derniere.r = parseFloat(curseurTaille.value);
        synchroniser();
    });
    curseurTaille.addEventListener('change', () => { reglageEnCours = false; });

    document.getElementById('annuler').addEventListener('click', () => {
        if (!historique.length) return;
        prises = JSON.parse(historique.pop());
        derniere = null;
        synchroniser();
    });

    // --- Cotation : n'affiche que le champ de l'échelle choisie ----------------

    const radiosSysteme = document.querySelectorAll('input[name="voie[systeme]"]');
    const majCotation = () => {
        const choisi = [...radiosSysteme].find(r => r.checked)?.value ?? 'fr';
        document.querySelectorAll('[data-pour-systeme]').forEach(b => {
            b.hidden = b.dataset.pourSysteme !== choisi;
        });
    };
    radiosSysteme.forEach(r => r.addEventListener('change', majCotation));
    majCotation();

    // --- Démarrage -----------------------------------------------------------

    if (selectPhoto && selectPhoto.value) {
        selectPhoto.dispatchEvent(new Event('change'));
    }
    synchroniser();
})();
