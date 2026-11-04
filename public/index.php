<?php

use App\Kernel;

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
        header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/setup/', true, 302);
        exit;
    }
})();

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
