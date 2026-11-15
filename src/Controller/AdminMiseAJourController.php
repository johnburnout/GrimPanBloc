<?php

namespace App\Controller;

use App\Exception\Messages;
use App\MiseAJour\InstallateurMiseAJour;
use App\MiseAJour\LanceurCommandes;
use App\MiseAJour\Maintenance;
use App\MiseAJour\VerificateurMiseAJour;
use App\MiseAJour\VersionInstallee;
use App\Sauvegarde\GestionSauvegardes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mise à jour en un clic depuis l'administration.
 *
 * ⚠ Contrat entre versions : la route « admin_mise_a_jour_finaliser » et son
 * paramètre « jeton » doivent exister dans toutes les versions futures, car
 * c'est la NOUVELLE version qui reçoit cette requête après le remplacement du code.
 * Elle doit accepter le jeton en POST (envoyé depuis la 1.3.3) ET en GET dans
 * l'URL (?jeton=…, envoyé par les versions jusqu'à 1.3.2), de même que le
 * contrôle de maintenance de public/index.php.
 * Elle tourne AVANT les migrations : elle ne doit dépendre d'aucune entité (pas de
 * pare-feu, pas d'utilisateur connecté, voir security.yaml) ; seul le jeton l'autorise.
 */
#[Route('/admin/mise-a-jour')]
class AdminMiseAJourController extends AbstractController
{
    public function __construct(
        private readonly VerificateurMiseAJour $verificateur,
        private readonly InstallateurMiseAJour $installateur,
        private readonly VersionInstallee $version,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_mise_a_jour', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): Response
    {
        $release = $this->verificateur->derniereRelease($request->query->getBoolean('verifier'));

        return $this->render('admin/mise_a_jour.html.twig', [
            'versionInstallee' => $this->version->get(),
            'release' => $release,
            'disponible' => $release && $this->version->estDepasseePar($release->version),
            'problemes' => $this->installateur->problemes(),
        ]);
    }

    #[Route('/installer', name: 'admin_mise_a_jour_installer', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[IsCsrfTokenValid('mise_a_jour')]
    public function installer(Request $request, GestionSauvegardes $sauvegardes): Response
    {
        $release = $this->verificateur->miseAJourDisponible(true);
        if (!$release) {
            $this->addFlash('info', new TranslatableMessage('flash.maj.deja_a_jour'));

            return $this->redirectToRoute('admin_mise_a_jour');
        }

        // Tout ce qui sert à répondre est préparé AVANT de remplacer le code :
        // ensuite, les fichiers sur disque sont ceux de la nouvelle version.
        $journal = [];
        $session = $request->getSession();
        $urlFinaliser = $this->generateUrl('admin_mise_a_jour_finaliser');
        $reponse = new Response(headers: ['Cache-Control' => 'no-store']);
        // Textes de la page de finalisation, traduits avec l'ancien code (voir pageFinalisation())
        $textes = [
            'langue' => $request->getLocale(),
            'titre' => $this->translator->trans('maj.finalisation.titre'),
            'texte' => $this->translator->trans('maj.finalisation.texte'),
            'bouton' => $this->translator->trans('maj.finalisation.bouton'),
        ];
        class_exists(TranslatableMessage::class);
        class_exists(Messages::class);

        // Secteurs, voies et photos sauvegardés avant de toucher au code (et aux migrations)
        try {
            $sauvegarde = $sauvegardes->creerAutomatique("avant la mise à jour vers la version {$release->version}");
            $journal[] = new TranslatableMessage('maj.journal.sauvegarde', ['date' => $sauvegarde->creeLe->format('d/m/Y H:i:s')]);
        } catch (\Throwable $e) {
            $this->addFlash('error', new TranslatableMessage('flash.maj.sauvegarde_echec', ['erreur' => Messages::de($e)]));

            return $this->redirectToRoute('admin_mise_a_jour');
        }

        try {
            $jeton = $this->installateur->installer($release, function (TranslatableMessage $ligne) use (&$journal) { $journal[] = $ligne; });
        } catch (\Throwable $e) {
            $this->addFlash('error', new TranslatableMessage('flash.maj.echec', ['erreur' => Messages::de($e)]));

            return $this->redirectToRoute('admin_mise_a_jour');
        }

        $session->set('mise_a_jour_journal', $journal);
        $session->set('mise_a_jour_depuis', $this->version->get());
        // Le jeton part en POST : il n'apparaît ni dans les logs d'accès ni dans l'historique.
        $reponse->setContent($this->pageFinalisation($urlFinaliser, $jeton, $textes));

        return $reponse;
    }

    /**
     * Page qui envoie aussitôt le jeton en POST à la nouvelle version. Écrite en
     * PHP pur : construite après le remplacement du code, elle ne doit charger ni
     * gabarit Twig ni autre classe de l'application.
     */
    /** @param array{langue: string, titre: string, texte: string, bouton: string} $textes */
    private function pageFinalisation(string $url, string $jeton, array $textes): string
    {
        $url = htmlspecialchars($url, \ENT_QUOTES);
        $jeton = htmlspecialchars($jeton, \ENT_QUOTES);
        ['langue' => $langue, 'titre' => $titre, 'texte' => $texte, 'bouton' => $bouton] = array_map(static fn (string $t) => htmlspecialchars($t, \ENT_QUOTES), $textes);

        return "<!DOCTYPE html><html lang=\"$langue\"><meta charset=\"UTF-8\">"
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            ."<title>$titre · GrimPanBloc</title>"
            .'<body style="font-family:system-ui,sans-serif;background:#f4f2ee;color:#1c1f24;display:grid;place-items:center;min-height:90vh;text-align:center">'
            ."<form id=\"finaliser\" method=\"post\" action=\"$url\">"
            ."<input type=\"hidden\" name=\"jeton\" value=\"$jeton\">"
            ."<h1>{$titre}…</h1>"
            ."<p>$texte</p>"
            ."<noscript><button type=\"submit\">$bouton</button></noscript>"
            .'</form>'
            .'<script>document.getElementById("finaliser").submit()</script>'
            .'</body></html>';
    }

    /**
     * Exécutée par la NOUVELLE version : migrations, logos, fin de la maintenance.
     * Autorisée par le seul jeton (secret aléatoire, valable 30 min, remis à l'admin
     * qui a lancé la mise à jour) : l'utilisateur n'est pas rechargé depuis la base.
     */
    #[Route('/finaliser', name: 'admin_mise_a_jour_finaliser', methods: ['GET', 'POST'])]
    public function finaliser(Request $request, Maintenance $maintenance, LanceurCommandes $lanceur): Response
    {
        // POST depuis la 1.3.3 ; GET (?jeton=…) quand l'ancienne version est antérieure
        $jeton = $request->isMethod('POST') ? $request->request->getString('jeton') : $request->query->getString('jeton');
        if (!$maintenance->jetonValide($jeton)) {
            $this->addFlash('error', new TranslatableMessage('flash.maj.lien_invalide'));

            return $this->redirectToRoute('admin_mise_a_jour');
        }

        $session = $request->getSession();
        $journal = (array) $session->get('mise_a_jour_journal', []);
        $depuis = (string) $session->get('mise_a_jour_depuis', '?');
        $session->remove('mise_a_jour_journal');
        $session->remove('mise_a_jour_depuis');

        try {
            foreach ($this->installateur->finaliser($lanceur->lancer(...)) as $avertissement) {
                $this->addFlash('error', $avertissement);
            }
            $journal[] = new TranslatableMessage('maj.journal.base');
            $this->addFlash('success', new TranslatableMessage('flash.maj.reussie', ['depuis' => $depuis, 'version' => $this->version->get()]));
        } catch (\Throwable $e) {
            $this->addFlash('error', Messages::de($e));
        }
        $session->set('mise_a_jour_dernier_journal', $journal);

        return $this->redirectToRoute('admin_mise_a_jour');
    }
}
