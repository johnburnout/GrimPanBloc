# Journal des versions

Toutes les évolutions notables de GrimPanBloc (anciennement GrimpePan). Format inspiré de
[Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), numérotation
[SemVer](https://semver.org/lang/fr/) : `MAJEURE.MINEURE.CORRECTIF`.

Le texte de chaque version sert de notes de release sur GitHub et s'affiche
aux administrateurs sur la page « Mises à jour ».

## [Non publié]

### Ajouté
- **Pieds libres ou pieds imposés** : nouveau choix dans le formulaire d'une voie
  (pieds imposés par défaut). En pieds libres, les prises de pied ne sont plus
  dessinées sur la photo ni dans les listes ; elles restent enregistrées et
  apparaissent en transparence dans l'éditeur. Comme les prises et la cotation,
  ce choix est verrouillé une fois la voie validée par un autre grimpeur.
- **Dupliquer une voie** : lien « Dupliquer » dans la liste des voies d'un secteur
  (icônes et liste) et sur la page d'une voie. Il ouvre le formulaire de
  nouvelle voie pré-rempli (même photo, mêmes prises, cotation proposée, pieds,
  consignes), à ajuster avant d'enregistrer. La copie appartient à celui qui
  l'enregistre ; les croix ne sont pas reprises.

## [1.9.0] - 2026-11-15

### Ajouté
- **Partager le site d'un téléphone à l'autre** : sur l'accueil, le bouton
  « Partager » affiche le QR code de l'adresse du site. Il suffit de le faire
  scanner avec l'appareil photo d'un autre téléphone.

## [1.8.0] - 2026-11-15

### Ajouté
- **Deux modes d'affichage des voies**, sur l'accueil comme sur la page d'un
  secteur : **icônes** ou **liste**.
  - En icônes : un curseur règle la **taille des icônes** ; le **tri** (cotation,
    nom, secteur, ouvreur ; croissant ou décroissant) se choisit avec les
    filtres, puis « Valider ».
  - En liste : colonnes nom, cotation, secteur et ouvreur ; un clic sur un
    **en-tête de colonne** trie par celle-ci, un second clic inverse l'ordre.
    Une case à cocher affiche ou non une **miniature** de chaque voie.
  - La taille des icônes et l'affichage des miniatures sont retenus par le
    navigateur.

### Modifié
- **Mêmes filtres partout** : la page d'un secteur propose maintenant les filtres
  par échelle et difficulté de l'accueil (le filtre par secteur reste sur
  l'accueil). Le bouton « Filtrer » devient « Valider » et applique filtres et tri.

## [1.7.0] - 2026-11-15

### Ajouté
- **Suppression d'un secteur** (administrateurs) : bouton « Supprimer le
  secteur… » sur la page du secteur. Ses voies (avec leurs croix) et ses photos
  sont supprimées avec lui, après confirmation par le mot SUPPRIMER. Une
  sauvegarde automatique est faite juste avant : le secteur peut être restauré
  depuis Administration → Sauvegardes.

### Modifié
- **Création des secteurs réservée aux administrateurs.** Les grimpeurs
  continuent de créer des voies dans les secteurs existants.

## [1.6.1] - 2026-11-14

### Corrigé
- **Envoi de photos depuis un téléphone Android** : les photos de 48 ou 50
  mégapixels (souvent plus de 15 Mo) étaient refusées. Le navigateur les réduit
  désormais avant l'envoi, à la taille que le site garde de toute façon
  (2000 px), ce qui rend aussi l'envoi bien plus rapide.

## [1.6.0] - 2026-11-11

### Ajouté
- **Photo d'une voie zoomable** sur la page de consultation : zoom avec deux doigts
  ou le curseur sur le côté, **double toucher** pour zoomer sur un endroit (et de
  nouveau pour revenir à la photo entière), déplacement de l'image une fois
  zoomée, et bouton **plein écran**. Sans zoom, un doigt fait défiler la page
  comme d'habitude.

## [1.5.1] - 2026-11-11

### Corrigé
- **Nouvelle photo sur téléphone** : on peut de nouveau choisir une photo de la
  photothèque, et pas seulement en prendre une avec l'appareil photo.

## [1.5.0] - 2026-11-11

### Modifié
- **Éditeur des prises refait, pensé pour le téléphone** :
  - la photo se **zoome** (jusqu'à 8×) avec deux doigts ou le curseur vertical sur
    le bord droit (boutons +, − et « voir toute la photo ») ; on la **déplace avec
    deux doigts**, ou d'un doigt posé à côté des prises ;
  - **double toucher** (double-clic à la souris) pour poser une prise ; une prise se
    **déplace d'un doigt** ; un simple toucher lui donne le type choisi et permet de
    régler sa taille ;
  - la **barre d'outils flotte sur la photo** : elle se déplace par sa poignée ⠿ et
    se replie pour ne laisser que l'outil actif ;
  - bouton **plein écran** (sur iPhone, l'éditeur occupe toute la fenêtre).

## [1.4.0] - 2026-11-10

### Ajouté
- **Administration → Sauvegardes** : sauvegarde des secteurs, des voies (avec leurs
  prises et leurs croix) et des photos, dans une archive téléchargeable. Une
  sauvegarde se restaure en entier ou pour un seul secteur ; les liens vers les
  voies restent valables. Une archive téléchargée peut être réimportée, ici ou sur
  un autre site GrimPanBloc. Restaurer une sauvegarde manuelle demande s'il faut
  la garder ou la supprimer ensuite.
- **Sauvegardes automatiques** avant chaque restauration, nettoyage de secteur et
  mise à jour, et au moins une fois par semaine (à la visite d'un administrateur).
  Le nombre de sauvegardes automatiques conservées se règle de 3 à 10 (5 par
  défaut) ; les sauvegardes manuelles ne sont jamais supprimées d'office.
  En SSH ou par cron : `php bin/console app:sauvegarde [--automatique]`.
- **Nettoyer un secteur** (bouton sur la page du secteur, administrateurs) :
  toutes ses voies et toutes ses photos, même inutilisées, sont supprimées après
  une sauvegarde automatique ; il faut taper SUPPRIMER pour confirmer.

### Technique
- Composant Symfony Lock : une seule sauvegarde ou restauration à la fois.
- Les comptes utilisateurs et les réglages ne font pas partie de ces sauvegardes :
  pour eux, sauvegardez la base de données chez l'hébergeur.

## [1.3.11] - 2026-11-09

### Ajouté
- **Miniatures des photos** (800 px de côté au plus) : la liste des voies et la
  page Administration → Photos chargent bien plus vite, surtout sur téléphone.
  La page d'une voie affiche toujours la photo entière. Les miniatures des photos
  déjà envoyées sont créées à la fin de la mise à jour ; en cas d'avertissement,
  lancez `php bin/console app:photos:miniatures`.

## [1.3.10] - 2026-11-09

### Modifié
- **Administration → Photos** : au-delà de 30 photos inutilisées, la liste est
  découpée en pages, avec le nombre total de photos. Après une suppression, on
  reste sur la même page.

### Technique
- Retrait d'un composant de notifications (Notifier) qui n'a jamais servi. Aucune
  action n'est nécessaire sur les sites installés.
- Analyse statique (PHPStan) sans plus aucune erreur tolérée : types précisés
  dans le code, et cas impossibles signalés explicitement.

### Documentation
- README : précautions liées aux mises à jour depuis GitHub, configuration nginx
  (à adapter, non testée), et données personnelles conservées (RGPD).

## [1.3.9] - 2026-11-09

### Corrigé
- Après une mise à jour, les navigateurs pouvaient garder l'ancienne mise en page
  (feuille de style en cache) : les miniatures de la page Photos s'affichaient par
  exemple en taille réelle. La feuille de style et les scripts portent désormais
  le numéro de version dans leur adresse, et sont rechargés à chaque mise à jour.

## [1.3.8] - 2026-11-09

### Modifié
- **Administration → Photos** : les photos s'affichent en miniatures (environ
  5 par ligne sur ordinateur, 2 sur téléphone). Un clic sur une miniature ouvre
  la photo en grand dans un nouvel onglet.

## [1.3.7] - 2026-11-09

### Corrigé
- **Administration → Photos** : chaque photo s'affiche entière, dans ses
  proportions d'origine (sur fond sombre si elle est en portrait ou très allongée),
  au lieu d'être recadrée ou déformée.

## [1.3.6] - 2026-11-09

### Ajouté
- **Administration → Photos** : liste des photos qu'aucune voie n'utilise, avec
  leur secteur, et un bouton pour les supprimer définitivement (fichier compris).
  Ces photos restent sinon dans leur secteur après la suppression d'une voie ou
  un changement de photo.

## [1.3.5] - 2026-11-09

### Modifié
- **Liste des secteurs** : chaque secteur affiche son nombre de voies au lieu de
  son nombre de photos. Les photos restent dans le secteur pour être réutilisées,
  même quand plus aucune voie ne s'en sert : un secteur sans voie pouvait ainsi
  afficher « 3 photo(s) ».

## [1.3.4] - 2026-11-09

### Technique
- Le réglage « Toujours utiliser HTTPS », ainsi que le nom et le logo du club
  affichés en en-tête, sont gardés en cache au lieu d'être relus dans la base de
  données à chaque page. Une modification s'applique toujours dès son
  enregistrement. Aucune action n'est nécessaire sur les sites installés.

## [1.3.3] - 2026-11-09

### Sécurité
- **Mises à jour en un clic** : la dernière étape (mise à jour de la base) est
  déclenchée par un envoi de formulaire et non plus par un lien : le code
  d'autorisation n'apparaît plus dans les journaux du serveur ni dans l'historique
  du navigateur. Une page « Finalisation de la mise à jour… » s'affiche quelques
  secondes. Les mises à jour depuis les versions précédentes fonctionnent comme
  avant. Aucune action n'est nécessaire sur les sites installés.
- Création de secteurs et de voies : l'obligation d'être connecté est vérifiée
  directement sur ces pages, en plus de la règle par adresse.

### Technique
- Retrait d'un composant d'envoi différé des mails (Messenger) qui n'a jamais servi :
  les mails partaient déjà directement. La mise à jour supprime sa table, restée
  vide, dans la base de données.

## [1.3.2] - 2026-11-09

### Sécurité
- **Connexion** : après 5 mots de passe erronés pour un même compte, la connexion
  est bloquée 5 minutes depuis cette adresse (25 essais par adresse, tous comptes
  confondus). Freine les essais de mots de passe en série.
- **Déconnexion** : le lien « Déconnexion » devient un bouton protégé. Un autre site
  ne peut plus déconnecter un utilisateur à son insu.
- **Photos et logo** : refusés au-delà de 25 mégapixels (« réduisez-la avant de
  l'envoyer »). Une image aux dimensions démesurées pouvait saturer la mémoire du
  serveur pendant son redimensionnement. Les photos de téléphone et d'appareil
  photo courants restent acceptées.
- En-têtes de sécurité HTTP sur toutes les pages : le site ne peut plus être affiché
  dans un cadre sur un autre site, et les liens sortants ne transmettent que
  le nom du site.

### Corrigé
- Les messages d'erreur fournis par Symfony (connexion refusée, compte désactivé,
  certaines erreurs de formulaire) s'affichent en français et non plus en anglais.

### Technique
- Analyse statique (PHPStan) et vérification du style de code (php-cs-fixer) à
  chaque release. Aucune action n'est nécessaire sur les sites installés.

## [1.3.1] - 2026-11-07

### Modifié
- **Administration → Réglages** : chaque section (HTTPS, club, SMTP, mail de test,
  envoi d'un message) a son propre formulaire. Une saisie refusée est signalée
  sous le champ concerné, et la saisie est conservée.
- Un logo refusé (format ou taille) n'enregistre plus le reste de la section :
  corrigez le logo, puis enregistrez à nouveau.
- Un formulaire resté ouvert trop longtemps affiche « Le formulaire a expiré :
  rechargez la page et recommencez » au lieu d'une page d'erreur.

### Technique
- Code réorganisé (voies, croix, réglages, mises à jour) et couvert par des tests
  automatiques, dont l'installation des mises à jour et son retour arrière en cas
  d'échec. Aucune action n'est nécessaire sur les sites installés.

## [1.3.0] - 2026-11-05

### Modifié
- L'application s'appelle désormais **GrimPanBloc** (anciennement GrimpePan) :
  nouveaux logos, textes, titres des pages, mails et assistant d'installation.
- Les releases portent l'archive `grimpanbloc-vX.Y.Z.zip`, plus une copie sous
  l'ancien nom (`grimpepan-vX.Y.Z.zip`) pour que les installations antérieures se
  mettent à jour normalement. Aucune action n'est nécessaire sur les sites installés.

## [1.2.0] - 2026-11-05

### Ajouté
- Réglage **« Toujours utiliser HTTPS »** (Administration → Réglages) : redirige
  les visiteurs arrivant en `http://` vers `https://` et envoie l'en-tête HSTS
  (30 jours). Il ne peut être activé que depuis une page ouverte en HTTPS.

## [1.1.0] - 2026-11-05

### Ajouté
- **Cotation par consensus** : chaque grimpeur qui réussit une voie propose sa
  cotation (modifiable sur la fiche). La cotation affichée est la plus proposée ;
  en cas d'égalité, la proposition du créateur départage, sinon la médiane.
  Les cotations et croix existantes sont reprises telles quelles.
- **Mon compte** : modification de l'email et du mot de passe (pseudo non
  modifiable), liste de ses croix et de ses voies.
- **Administration** :
  - réglages : nom et logo de la salle ou du club (en-tête et fiche), serveur
    SMTP, mail de test, envoi d'un message à tous les utilisateurs ou aux admins ;
  - utilisateurs : nommer / retirer un admin, désactiver / réactiver, supprimer
    (les voies restent visibles sous le pseudo) ;
  - fiche A4 à imprimer avec un QR code vers le site.

### Corrigé
- Mise à jour en un clic : la finalisation (migrations) ne dépend plus de
  l'utilisateur connecté, ce qui la bloquait quand une version modifie la table
  des utilisateurs.
- Page de connexion : le vrai motif du refus est affiché (compte désactivé…).

## [1.0.2] - 2026-11-05

### Corrigé
- Assistant d'installation : si le transfert FTP a oublié les fichiers cachés
  (`.env`, `public/.htaccess`, `public/uploads/.htaccess`), l'assistant les
  recrée au lieu d'échouer avec « Unable to read the .env environment file ».
  Sans copie disponible, il explique quel fichier envoyer.

## [1.0.1] - 2026-11-05

### Corrigé
- Assistant d'installation : boucle de redirections sur `/setup/` avec les
  hébergements qui utilisent `public/.htaccess` (sans VirtualHost dédié).
  `public/.htaccess` laisse désormais Apache servir le dossier `setup/`, et la
  page d'accueil redirige vers `setup/index.php`.

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
