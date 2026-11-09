# Journal des versions

Toutes les évolutions notables de GrimPanBloc (anciennement GrimpePan). Format inspiré de
[Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), numérotation
[SemVer](https://semver.org/lang/fr/) : `MAJEURE.MINEURE.CORRECTIF`.

Le texte de chaque version sert de notes de release sur GitHub et s'affiche
aux administrateurs sur la page « Mises à jour ».

## [Non publié]

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
