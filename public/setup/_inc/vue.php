<?php

/*
 * Assistant d'installation de GrimpePan : mise en page.
 * Ce fichier ne fait rien s'il est appelé directement.
 */

\defined('GRIMPEPAN_SETUP') || exit;

const ETAPES = [
    1 => 'Prérequis',
    2 => 'Base de données',
    3 => 'Tables',
    4 => 'Administrateur',
    5 => 'Finalisation',
];

function h(?string $texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Retire la mise en forme de la console Symfony (cadres « [OK] », espaces de remplissage). */
function sortieConsole(string $sortie): string
{
    $lignes = array_map('rtrim', explode("\n", $sortie));

    return trim(implode("\n", $lignes));
}

/**
 * Affiche une page complète de l'assistant.
 *
 * @param int $etape 0 pour une page hors étapes (blocage, fin)
 */
function page(string $titre, int $etape, int $etapeMax, string $contenu, ?string $erreur = null, ?string $racine = null): void
{
    $logo = $racine && is_file($racine.'/assets/logo/logo.svg') ? file_get_contents($racine.'/assets/logo/logo.svg') : '<strong>GrimpePan</strong>';
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; font-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h($titre) ?> · Installation de GrimpePan</title>
    <style>
        @font-face {
            font-family: 'Bricolage Grotesque'; font-weight: 200 800; font-display: swap;
            src: url('../fonts/bricolage-grotesque/bricolage-grotesque-latin-wght-normal.woff2') format('woff2-variations');
        }
        :root {
            --fond: #f4f2ee; --surface: #fff; --texte: #1c1f24; --doux: #5c636b;
            --accent: #e8622c; --accent-fonce: #c4430f; --bord: #ddd8cf;
            --ok: #15803d; --ko: #b42318; --alerte: #a16207; --rayon: 10px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fond); color: var(--texte); line-height: 1.5;
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .cadre { max-width: 760px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .entete svg { height: 40px; width: auto; display: block; }
        .entete p { margin: .25rem 0 1.5rem; color: var(--doux); }
        h1, h2 { font-family: 'Bricolage Grotesque', system-ui, sans-serif; letter-spacing: -.01em; line-height: 1.2; }
        h1 { font-size: 1.6rem; margin: 0 0 1rem; font-weight: 700; }
        h2 { font-size: 1.15rem; margin: 1.5rem 0 .5rem; font-weight: 650; }
        .etapes { display: flex; gap: .25rem; list-style: none; padding: 0; margin: 0 0 1.5rem; counter-reset: e; }
        .etapes li { flex: 1; font-size: .85rem; color: var(--doux); border-top: 4px solid var(--bord); padding-top: .4rem; }
        .etapes li.faite { border-color: var(--texte); color: var(--texte); }
        .etapes li.active { border-color: var(--accent); color: var(--texte); font-weight: 600; }
        .etapes a { color: inherit; }
        .etapes span { display: block; font-size: .75rem; }
        .carte { background: var(--surface); border: 1px solid var(--bord); border-radius: var(--rayon); padding: 1.25rem; }
        .verifs { list-style: none; padding: 0; margin: 0; }
        .verifs li { display: grid; grid-template-columns: 1.6rem 1fr; padding: .5rem 0; border-bottom: 1px solid var(--bord); }
        .verifs li:last-child { border-bottom: 0; }
        .verifs small { grid-column: 2; color: var(--doux); }
        .i-ok { color: var(--ok); } .i-ko { color: var(--ko); } .i-alerte { color: var(--alerte); }
        .msg { padding: .75rem 1rem; border-radius: var(--rayon); margin: 0 0 1rem; }
        .msg-erreur { background: #fee2e2; color: #7f1d1d; }
        .msg-ok { background: #dcfce7; color: #14532d; }
        .msg-info { background: #e0f2fe; color: #0c4a6e; }
        .msg-alerte { background: #fef3c7; color: #713f12; }
        label { display: block; font-weight: 600; margin: .9rem 0 .25rem; }
        label small { font-weight: normal; color: var(--doux); }
        input[type=text], input[type=email], input[type=password], input[type=number] {
            width: 100%; padding: .6rem .7rem; font: inherit; border: 1px solid var(--bord); border-radius: var(--rayon); background: #fff; }
        .ligne { display: grid; grid-template-columns: 1fr 8rem; gap: 0 1rem; }
        .actions { margin-top: 1.5rem; display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
        button, .bouton { background: var(--accent); color: #fff; border: 0; border-radius: var(--rayon);
            padding: .7rem 1.3rem; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        button:hover, .bouton:hover { background: var(--accent-fonce); }
        button:disabled { background: var(--bord); color: var(--doux); cursor: not-allowed; }
        .secondaire { background: #fff; color: var(--texte); border: 1px solid var(--bord); }
        .secondaire:hover { background: var(--fond); }
        pre { background: var(--texte); color: #f4f2ee; padding: 1rem; border-radius: var(--rayon); overflow-x: auto; font-size: .85rem; white-space: pre-wrap; }
        code { background: #ece8e1; padding: .1rem .3rem; border-radius: 4px; font-size: .9em; }
        pre code { background: none; padding: 0; }
        .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
        @media (max-width: 520px) { .etapes span { display: none; } .ligne { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="cadre">
    <div class="entete">
        <?= $logo ?>
        <p>Assistant d'installation</p>
    </div>

    <?php if ($etape > 0): ?>
        <ol class="etapes" aria-label="Étapes">
            <?php foreach (ETAPES as $n => $nom): ?>
                <?php $classe = $n === $etape ? 'active' : ($n < $etape || $n <= $etapeMax - 1 ? 'faite' : ''); ?>
                <li class="<?= $classe ?>" <?= $n === $etape ? 'aria-current="step"' : '' ?>>
                    <?php if ($n <= $etapeMax && $n !== $etape): ?>
                        <a href="?etape=<?= $n ?>"><?= $n ?>. <span><?= h($nom) ?></span></a>
                    <?php else: ?>
                        <?= $n ?>. <span><?= h($nom) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <main class="carte">
        <h1><?= h($titre) ?></h1>
        <?php if ($erreur): ?><div class="msg msg-erreur" role="alert"><?= nl2br(h($erreur)) ?></div><?php endif; ?>
        <?= $contenu ?>
    </main>
</div>
</body>
</html>
    <?php
}
