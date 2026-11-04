<?php

use App\Kernel;

// Mise à jour en cours (voir src/MiseAJour/Maintenance.php) : tout le monde reçoit une
// page 503, sauf la requête de finalisation qui porte le bon jeton. Le verrou expire
// après 30 minutes pour qu'une mise à jour interrompue ne bloque pas le site.
(static function (): void {
    $verrou = dirname(__DIR__).'/var/maintenance.lock';
    if (!is_file($verrou)) {
        return;
    }
    $d = json_decode((string) @file_get_contents($verrou), true);
    if (!is_array($d) || time() - (int) ($d['depuis'] ?? 0) >= 1800) {
        return;
    }
    $chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (str_ends_with($chemin, '/admin/mise-a-jour/finaliser')
        && hash_equals((string) ($d['jeton'] ?? ''), hash('sha256', (string) ($_GET['jeton'] ?? '')))) {
        return;
    }
    http_response_code(503);
    header('Retry-After: 30');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<meta http-equiv="refresh" content="15"><title>Mise à jour en cours · GrimpePan</title>'
        .'<body style="font-family:system-ui,sans-serif;background:#f4f2ee;color:#1c1f24;display:grid;place-items:center;min-height:90vh;text-align:center">'
        .'<div><h1>Mise à jour en cours</h1><p>GrimpePan revient dans quelques instants.</p></div></body></html>';
    exit;
})();

// Serveur pas encore installé : on envoie vers l'assistant d'installation (public/setup/).
// « Pas installé » = assistant présent, aucune configuration locale (.env.local) et pas de
// verrou de fin d'installation. On l'applique seulement aux livraisons de production
// (sans le WebProfilerBundle, paquet de développement) pour ne pas gêner le poste de dev.
(static function (): void {
    $racine = dirname(__DIR__);
    if (is_dir(__DIR__.'/setup')
        && !is_file($racine.'/var/installation.lock')
        && !is_file($racine.'/.env.local')
        && !is_file($racine.'/.env.local.php')
        && !is_dir($racine.'/vendor/symfony/web-profiler-bundle')
    ) {
        // Vers setup/index.php (et non setup/) : fonctionne même si le serveur ne sert
        // pas index.php pour un dossier ou réécrit les dossiers vers ce contrôleur frontal.
        header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/setup/index.php', true, 302);
        exit;
    }
})();

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
