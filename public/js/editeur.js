/**
 * Éditeur de voie : marquage des prises sur une photo.
 *
 * Les prises sont stockées en pourcentage : x et y par rapport à la largeur et à
 * la hauteur de l'image, le rayon par rapport à la largeur. Elles sont envoyées
 * au serveur en JSON dans le champ caché "voie[prises]".
 */
(() => {
    const SVG = 'http://www.w3.org/2000/svg';

    const editeur = document.getElementById('editeur');
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
    let glisse = null; // {index, bouge, depart}

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

    // --- Photo -------------------------------------------------------------

    const chargerPhoto = (src) => {
        editeur.innerHTML = '';
        if (!src) {
            editeur.innerHTML = '<p class="editeur-vide">Choisissez ou envoyez une photo pour commencer.</p>';
            svg = null;
            return;
        }
        const conteneur = document.createElement('div');
        conteneur.className = 'photo-voie edition';
        const img = new Image();
        img.alt = 'Photo de la zone';
        img.onload = () => {
            W = img.naturalWidth;
            H = img.naturalHeight;
            svg = document.createElementNS(SVG, 'svg');
            svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
            svg.setAttribute('preserveAspectRatio', 'none');
            conteneur.appendChild(svg);
            brancherPointeur();
            dessiner();
        };
        img.src = src;
        conteneur.appendChild(img);
        editeur.appendChild(conteneur);
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
            class: `prise prise-${p.type}`,
            cx: p.x / 100 * W, cy: p.y / 100 * H, r: p.r / 100 * W,
            'stroke-width': W * 0.005,
            ...(p.type === 'pied' ? { 'stroke-dasharray': `${W * 0.012} ${W * 0.008}` } : {}),
        })));
    };

    // --- Interaction -------------------------------------------------------

    const position = (ev) => {
        const r = svg.getBoundingClientRect();
        return {
            x: Math.max(0, Math.min(100, (ev.clientX - r.left) / r.width * 100)),
            y: Math.max(0, Math.min(100, (ev.clientY - r.top) / r.height * 100)),
        };
    };

    /** Index de la prise sous le point, ou -1. Tolérance minimale pour le tactile. */
    const priseSous = ({ x, y }) => {
        let meilleur = -1, distMin = Infinity;
        prises.forEach((p, i) => {
            const dx = x - p.x;
            const dy = (y - p.y) * H / W; // ramené en % de largeur
            const d = Math.hypot(dx, dy);
            if (d <= Math.max(p.r, 2.5) && d < distMin) { meilleur = i; distMin = d; }
        });
        return meilleur;
    };

    const brancherPointeur = () => {
        svg.addEventListener('pointerdown', (ev) => {
            ev.preventDefault();
            const pt = position(ev);
            const i = priseSous(pt);

            if (outil === 'gomme') {
                if (i >= 0) { memoriser(); prises.splice(i, 1); synchroniser(); }
                return;
            }
            if (i >= 0) {
                memoriser();
                glisse = { index: i, bouge: false, depart: pt };
                svg.setPointerCapture(ev.pointerId);
                return;
            }
            memoriser();
            prises.push({ x: pt.x, y: pt.y, r: parseFloat(curseurTaille.value), type: outil });
            synchroniser();
        });

        svg.addEventListener('pointermove', (ev) => {
            if (!glisse) return;
            const pt = position(ev);
            if (!glisse.bouge && Math.hypot(pt.x - glisse.depart.x, pt.y - glisse.depart.y) < 0.8) return;
            glisse.bouge = true;
            Object.assign(prises[glisse.index], pt);
            synchroniser();
        });

        const finGlisse = () => {
            if (!glisse) return;
            if (!glisse.bouge) {
                // simple toucher : applique le type de l'outil courant
                const p = prises[glisse.index];
                if (p.type === outil) historique.pop(); else p.type = outil;
                synchroniser();
            }
            glisse = null;
        };
        svg.addEventListener('pointerup', finGlisse);
        svg.addEventListener('pointercancel', finGlisse);
    };

    boutonsOutils.forEach(b => b.addEventListener('click', () => {
        outil = b.dataset.outil;
        boutonsOutils.forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
    }));

    document.getElementById('annuler').addEventListener('click', () => {
        if (!historique.length) return;
        prises = JSON.parse(historique.pop());
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
