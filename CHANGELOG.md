# Journal des versions

Toutes les évolutions notables de GrimpePan. Format inspiré de
[Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), numérotation
[SemVer](https://semver.org/lang/fr/) : `MAJEURE.MINEURE.CORRECTIF`.

Le texte de chaque version sert de notes de release sur GitHub et s'affiche
aux administrateurs sur la page « Mises à jour ».

## [1.0.0] - 2026-11-05

Première version publique.

### Voies et secteurs
- Comptes (inscription, connexion), secteurs, voies avec photo du mur.
- Éditeur de prises tactile : départ, main, pied, top ; déplacement, gomme,
  annulation ; le curseur de taille règle aussi la dernière prise posée ou déplacée.
- Cotation française (3a → 9c) ou couleur ; liste filtrable par secteur,
  échelle et difficulté.
- Croix (« j'ai réussi cette voie »). Le créateur peut modifier sa voie tant
  qu'aucun autre grimpeur ne l'a réussie, puis seulement son nom et sa description.
- Suppression d'une voie réussie protégée : il faut taper `SUPPRIMER`.
- Photos redressées selon l'EXIF et réduites à 2000 × 2000 px.

### Installation et mises à jour
- Assistant d'installation web (`/setup/`), qui se supprime une fois l'installation terminée.
- Vérification quotidienne des nouvelles versions et mise à jour en un clic
  depuis l'administration (ou `php bin/console app:mise-a-jour` en SSH).
- Fonctionne avec Apache (VirtualHost ou `.htaccess`), PHP 8.4 et MariaDB 11.8.
