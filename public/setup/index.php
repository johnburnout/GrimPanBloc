<?php

/*
 * Assistant d'installation de GrimpePan (https://votre-domaine/setup/).
 *
 * Étapes : 1. prérequis · 2. base de données · 3. tables · 4. administrateur · 5. finalisation.
 * À la fin, l'assistant se bloque (var/installation.lock) puis supprime ce dossier.
 * Il refuse aussi de fonctionner si GrimpePan a déjà été configuré à la main (.env.local).
 */

declare(strict_types=1);

const GRIMPEPAN_SETUP = true;
$racine = dirname(__DIR__, 2);

require __DIR__.'/_inc/Installateur.php';
require __DIR__.'/_inc/vue.php';

$installateur = new Installateur($racine);

// --- Session propre à l'assistant ---------------------------------------------

$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name('grimpepan_setup');
session_set_cookie_params([
    'path' => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/',
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Strict',
]);
session_start();
$_SESSION['jeton'] ??= bin2hex(random_bytes(32)); // identifie le navigateur qui installe
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$_SESSION['etape_max'] ??= 1;

$blocage = $installateur->raisonBlocage($_SESSION['jeton']);
if (null !== $blocage) {
    http_response_code(403);
    page('Assistant indisponible', 0, 0,
        '<p>'.h($blocage).'</p>'
        .'<p>Pour des raisons de sécurité, supprimez le dossier <code>public/setup/</code> du serveur.</p>'
        .'<p>Pour recommencer une installation, supprimez aussi <code>.env.local</code>, '
        .'<code>var/installation.lock</code> et <code>var/setup-en-cours.lock</code>.</p>'
        .'<div class="actions"><a class="bouton" href="../">Aller sur le site</a></div>',
        null, $racine);
    exit;
}

$etapeMax = (int) $_SESSION['etape_max'];
$etape = max(1, min($etapeMax, (int) ($_GET['etape'] ?? $etapeMax)));
$erreur = null;
$csrf = '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';

$avancer = function (int $vers): never {
    $_SESSION['etape_max'] = max((int) $_SESSION['etape_max'], $vers);
    header('Location: ?etape='.$vers, true, 303);
    exit;
};

// --- Traitement des formulaires ------------------------------------------------

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $erreur = 'La session a expiré. Rechargez la page et recommencez.';
    } else {
        try {
            switch ($etape) {
                case 1:
                    $bloquants = array_filter($installateur->verifierPrerequis(), fn ($v) => $v['bloquant'] && !$v['ok']);
                    if ([] !== $bloquants) {
                        throw new RuntimeException('Corrigez d\'abord les points en rouge.');
                    }
                    $avancer(2);

                case 2:
                    if (isset($_POST['env_cree'])) { // .env.local créé à la main
                        if (!$installateur->envPret()) {
                            throw new RuntimeException('Le fichier .env.local est introuvable ou ne contient pas « APP_ENV=prod ».');
                        }
                        unset($_SESSION['env_a_creer']);
                        $avancer(3);
                    }
                    $p = [
                        'hote' => trim((string) ($_POST['hote'] ?? '')),
                        'port' => trim((string) ($_POST['port'] ?? '3306')),
                        'base' => trim((string) ($_POST['base'] ?? '')),
                        'utilisateur' => trim((string) ($_POST['utilisateur'] ?? '')),
                        'mot_de_passe' => (string) ($_POST['mot_de_passe'] ?? ''),
                    ];
                    $_SESSION['base'] = array_diff_key($p, ['mot_de_passe' => true]); // pré-remplissage, sans le mot de passe
                    $infos = $installateur->testerBase($p);
                    $_SESSION['infos_base'] = $infos;
                    $contenu = $installateur->contenuEnv($p, $infos['version']);
                    $installateur->reserver($_SESSION['jeton']);
                    if (!$installateur->ecrireEnv($contenu)) {
                        $_SESSION['env_a_creer'] = $contenu;
                        header('Location: ?etape=2', true, 303);
                        exit;
                    }
                    unset($_SESSION['env_a_creer']);
                    $avancer(3);

                case 3:
                    if (!$installateur->envPret()) {
                        $_SESSION['etape_max'] = 2;
                        throw new RuntimeException('La configuration (.env.local) est absente : reprenez l\'étape 2.');
                    }
                    $r = $installateur->console(['command' => 'doctrine:migrations:migrate', '--allow-no-migration' => true]);
                    $_SESSION['sortie_tables'] = sortieConsole($r['sortie']);
                    if (0 !== $r['code']) {
                        throw new RuntimeException("La création des tables a échoué :\n".sortieConsole($r['sortie']));
                    }
                    $avancer(4);

                case 4:
                    $email = trim((string) ($_POST['email'] ?? ''));
                    $pseudo = trim((string) ($_POST['pseudo'] ?? ''));
                    $mdp = (string) ($_POST['mot_de_passe'] ?? '');
                    $_SESSION['admin'] = ['email' => $email, 'pseudo' => $pseudo];
                    if ($mdp !== (string) ($_POST['confirmation'] ?? '')) {
                        throw new RuntimeException('Les deux mots de passe ne correspondent pas.');
                    }
                    if (mb_strlen($mdp) < 8) {
                        throw new RuntimeException('Le mot de passe doit faire au moins 8 caractères.');
                    }
                    $r = $installateur->console(['command' => 'app:utilisateur:creer', 'email' => $email, 'pseudo' => $pseudo,
                        '--admin' => true, '--mot-de-passe' => $mdp]);
                    if (0 !== $r['code']) {
                        throw new RuntimeException(trim(preg_replace('/^\s*\[ERROR\]\s*/m', '', sortieConsole($r['sortie']))));
                    }
                    $avancer(5);

                case 5:
                    $avertissements = [];
                    $r = $installateur->console(['command' => 'asset-map:compile']);
                    if (0 !== $r['code']) {
                        $avertissements[] = 'Les logos n\'ont pas pu être compilés. Lancez « php bin/console asset-map:compile » sur le serveur.';
                    }
                    $r = $installateur->console(['command' => 'cache:clear']);
                    if (0 !== $r['code']) {
                        $avertissements[] = 'Le cache n\'a pas pu être préparé. Il sera créé à la première visite.';
                    }
                    $admin = $_SESSION['admin']['email'] ?? '';
                    $installateur->terminer();
                    $_SESSION = [];
                    session_destroy();
                    $supprime = $installateur->supprimerAssistant(__DIR__);

                    $html = '<div class="msg msg-ok">GrimpePan est installé.</div>';
                    foreach ($avertissements as $a) {
                        $html .= '<div class="msg msg-alerte">'.h($a).'</div>';
                    }
                    $html .= $supprime
                        ? '<p>L\'assistant d\'installation a été supprimé du serveur.</p>'
                        : '<div class="msg msg-alerte"><strong>Supprimez maintenant le dossier <code>public/setup/</code></strong> '
                          .'(le serveur web n\'a pas le droit de le faire). Il est déjà bloqué, mais ne doit pas rester en ligne.</div>';
                    $html .= '<p>Connectez-vous avec <strong>'.h($admin).'</strong> et le mot de passe choisi.</p>'
                        .'<div class="actions"><a class="bouton" href="../connexion">Se connecter</a></div>';
                    page('Installation terminée', 0, 0, $html, null, $racine);
                    exit;
            }
        } catch (Throwable $e) {
            $erreur = $e->getMessage();
        }
    }
}

// --- Affichage des étapes -------------------------------------------------------

switch ($etape) {
    case 1:
        $verifs = $installateur->verifierPrerequis();
        $bloque = (bool) array_filter($verifs, fn ($v) => $v['bloquant'] && !$v['ok']);
        $html = '<p>L\'assistant vérifie que le serveur peut faire tourner GrimpePan.</p><ul class="verifs">';
        foreach ($verifs as $v) {
            [$icone, $classe] = $v['ok'] ? ['✔', 'i-ok'] : ($v['bloquant'] ? ['✘', 'i-ko'] : ['!', 'i-alerte']);
            $html .= '<li><span class="'.$classe.'" aria-hidden="true">'.$icone.'</span><span>'.h($v['libelle'])
                .'<span class="sr">'.($v['ok'] ? '' : ($v['bloquant'] ? ' (bloquant)' : ' (conseillé)')).'</span></span>'
                .'<small>'.h($v['detail']).'</small></li>';
        }
        $html .= '</ul>';
        $html .= $bloque
            ? '<p class="msg msg-erreur">Corrigez les points en rouge puis rechargez la page.</p>'
            : '<p class="msg msg-info">Les points en orange ne bloquent pas l\'installation, mais il vaut mieux les corriger.</p>';
        $html .= '<form method="post">'.$csrf.'<div class="actions">'
            .'<button '.($bloque ? 'disabled' : '').'>Étape suivante →</button>'
            .'<a class="bouton secondaire" href="?etape=1">Revérifier</a></div></form>';
        page('Prérequis', 1, $etapeMax, $html, $erreur, $racine);
        break;

    case 2:
        if (isset($_SESSION['env_a_creer'])) {
            $html = '<p class="msg msg-alerte">Le serveur web n\'a pas le droit de créer le fichier <code>.env.local</code> '
                .'à la racine du projet. Créez-le vous-même avec le contenu ci-dessous (droits conseillés : 640).</p>'
                .'<pre><code>'.h($_SESSION['env_a_creer']).'</code></pre>'
                .'<form method="post">'.$csrf.'<input type="hidden" name="env_cree" value="1"><div class="actions">'
                .'<button>J\'ai créé le fichier →</button><a class="bouton secondaire" href="?etape=2&amp;modifier=1">Modifier la connexion</a></div></form>';
            if (isset($_GET['modifier'])) {
                unset($_SESSION['env_a_creer']);
                header('Location: ?etape=2', true, 303);
                exit;
            }
            page('Base de données', 2, $etapeMax, $html, $erreur, $racine);
            break;
        }
        $b = ($_SESSION['base'] ?? []) + ['hote' => 'localhost', 'port' => '3306', 'base' => 'grimpepan', 'utilisateur' => ''];
        $html = '<p>Indiquez la base MariaDB fournie par votre hébergeur. Si elle n\'existe pas, l\'assistant essaie de la créer.</p>'
            .'<form method="post" autocomplete="off">'.$csrf
            .'<div class="ligne"><div><label for="hote">Serveur</label><input type="text" id="hote" name="hote" required value="'.h($b['hote']).'"></div>'
            .'<div><label for="port">Port</label><input type="number" id="port" name="port" required min="1" max="65535" value="'.h($b['port']).'"></div></div>'
            .'<label for="base">Nom de la base</label><input type="text" id="base" name="base" required pattern="[A-Za-z0-9_]+" value="'.h($b['base']).'">'
            .'<label for="utilisateur">Utilisateur</label><input type="text" id="utilisateur" name="utilisateur" required value="'.h($b['utilisateur']).'">'
            .'<label for="mot_de_passe">Mot de passe</label><input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="new-password">'
            .'<div class="actions"><button>Tester et enregistrer →</button></div></form>';
        page('Base de données', 2, $etapeMax, $html, $erreur, $racine);
        break;

    case 3:
        $i = $_SESSION['infos_base'] ?? null;
        $html = $i ? '<p class="msg msg-ok">Connexion réussie à MariaDB '.h($i['version'])
            .($i['base_creee'] ? ' (base créée)' : '').'.</p>' : '';
        if ($i && $i['tables'] > 0) {
            $html .= '<p class="msg msg-alerte">La base contient déjà '.(int) $i['tables'].' table(s). Seules les tables manquantes seront créées : '
                .'les données existantes sont conservées.</p>';
        }
        $html .= '<p>L\'assistant va créer les tables de GrimpePan (utilisateurs, secteurs, photos, voies, prises, croix).</p>';
        if (isset($_SESSION['sortie_tables']) && $etapeMax > 3) {
            $html .= '<pre>'.h($_SESSION['sortie_tables']).'</pre>';
        }
        $html .= '<form method="post">'.$csrf.'<div class="actions"><button>Créer les tables →</button></div></form>';
        page('Création des tables', 3, $etapeMax, $html, $erreur, $racine);
        break;

    case 4:
        $a = ($_SESSION['admin'] ?? []) + ['email' => '', 'pseudo' => ''];
        $html = '<p>Ce compte pourra supprimer n\'importe quelle voie. Vous pourrez ensuite créer d\'autres comptes depuis le site.</p>'
            .'<form method="post">'.$csrf
            .'<label for="email">Email <small>(identifiant de connexion)</small></label><input type="email" id="email" name="email" required autocomplete="email" value="'.h($a['email']).'">'
            .'<label for="pseudo">Pseudo</label><input type="text" id="pseudo" name="pseudo" required minlength="2" maxlength="50" value="'.h($a['pseudo']).'">'
            .'<label for="mot_de_passe">Mot de passe <small>(8 caractères minimum)</small></label><input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="8" autocomplete="new-password">'
            .'<label for="confirmation">Confirmation</label><input type="password" id="confirmation" name="confirmation" required minlength="8" autocomplete="new-password">'
            .'<div class="actions"><button>Créer le compte →</button></div></form>';
        page('Compte administrateur', 4, $etapeMax, $html, $erreur, $racine);
        break;

    case 5:
        $html = '<p class="msg msg-ok">Le compte administrateur est créé.</p>'
            .'<p>Dernière étape : l\'assistant prépare le site (logos, cache), se bloque définitivement puis se supprime du serveur.</p>'
            .'<form method="post">'.$csrf.'<div class="actions"><button>Terminer l\'installation</button></div></form>';
        page('Finalisation', 5, $etapeMax, $html, $erreur, $racine);
        break;
}
