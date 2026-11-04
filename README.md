# GrimpePan

Création et affichage de voies sur une structure artificielle d'escalade.
Symfony 8.1 · Twig · Doctrine · JavaScript natif (pas de build).

## Fonctionnalités (V1)

- Inscription / connexion
- Secteurs (pan, mur, dévers…)
- Création de voie : envoi d'une photo (ou réutilisation d'une photo du secteur),
  marquage des prises (départ, main, pied, top), cotation française (3a → 9c)
  ou couleur (vert → blanc)
- Affichage de la voie : photo assombrie, prises entourées
- Liste des voies filtrable par secteur, échelle et plage de difficulté
- Croix (« j'ai réussi cette voie »)
- Modification d'une voie par son créateur : tout reste modifiable tant qu'aucun
  autre grimpeur ne l'a réussie ; ensuite, seuls le nom et la description le restent

## Installation (macOS)

Prérequis : PHP ≥ 8.4 avec les extensions `pdo_mysql` (MariaDB 11.8), `gd`, `exif`, `intl`, et Composer.

```bash
brew install php composer          # si besoin
brew install symfony-cli/tap/symfony-cli   # optionnel, serveur de dev

cd ~/Documents/php/GrimpePan
composer install

# Secret local (ne pas versionner)
echo "APP_SECRET=$(openssl rand -hex 16)" > .env.local

# Base de données (MariaDB 11.8, comme en prod)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

# Lancer
symfony serve        # ou : php -S 127.0.0.1:8000 -t public
```

Puis ouvrir http://127.0.0.1:8000, créer un compte, un secteur, puis une voie.

Par défaut : `mysql://root@127.0.0.1:3307/grimpepan` (MariaDB 11.8 local sur le port 3307, sans mot de passe). Pour d'autres identifiants, surcharger `DATABASE_URL` dans `.env.local`. La prod tourne sur MariaDB 11.8.6 : générer les migrations sur MariaDB, jamais sur MySQL.

## Structure

| Dossier | Contenu |
|---|---|
| `src/Entity` | `User`, `Secteur`, `Photo`, `Voie`, `Prise` |
| `src/Enum` | `CotationFr`, `CotationCouleur`, `SystemeCotation`, `TypePrise` |
| `src/Controller` | Accueil, secteurs, voies, sécurité |
| `src/Service/PhotoUploader.php` | Enregistrement des photos (+ redressement EXIF) |
| `templates/voie/_photo.html.twig` | Rendu photo + prises (SVG) |
| `public/js/editeur.js` | Éditeur de prises |

Les prises sont stockées en **pourcentage** de l'image (x, y, rayon) : elles restent
alignées quelle que soit la taille d'affichage.
La cotation est stockée en texte (`6a`, `bleu`) avec un **rang** numérique
qui permet de trier et filtrer par difficulté.

## Prochaines étapes (V2/V3)

- Plusieurs voies affichées sur une même photo
- Page « Mes voies », avis de cotation, commentaires
- Administration : modération, archivage des voies démontées
