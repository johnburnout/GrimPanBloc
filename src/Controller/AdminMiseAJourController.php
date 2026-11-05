<?php

namespace App\Controller;

use App\MiseAJour\InstallateurMiseAJour;
use App\MiseAJour\Maintenance;
use App\MiseAJour\VerificateurMiseAJour;
use App\MiseAJour\VersionInstallee;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mise à jour en un clic depuis l'administration.
 *
 * ⚠ Contrat entre versions : la route « admin_mise_a_jour_finaliser » et son
 * paramètre « jeton » doivent exister dans toutes les versions futures, car
 * c'est la NOUVELLE version qui reçoit cette requête après le remplacement du code.
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
    public function installer(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('mise_a_jour', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $release = $this->verificateur->miseAJourDisponible(true);
        if (!$release) {
            $this->addFlash('info', 'GrimpePan est déjà à jour.');

            return $this->redirectToRoute('admin_mise_a_jour');
        }

        // Tout ce qui sert à répondre est préparé AVANT de remplacer le code :
        // ensuite, les fichiers sur disque sont ceux de la nouvelle version.
        $journal = [];
        $session = $request->getSession();
        $urlFinaliser = $this->generateUrl('admin_mise_a_jour_finaliser');
        $reponse = new RedirectResponse($urlFinaliser);

        try {
            $jeton = $this->installateur->installer($release, function (string $ligne) use (&$journal) { $journal[] = $ligne; });
        } catch (\Throwable $e) {
            $this->addFlash('error', 'La mise à jour a échoué : '.$e->getMessage());

            return $this->redirectToRoute('admin_mise_a_jour');
        }

        $session->set('mise_a_jour_journal', $journal);
        $session->set('mise_a_jour_depuis', $this->version->get());
        $reponse->setTargetUrl($urlFinaliser.'?jeton='.$jeton);

        return $reponse;
    }

    /**
     * Exécutée par la NOUVELLE version : migrations, logos, fin de la maintenance.
     * Autorisée par le seul jeton (secret aléatoire, valable 30 min, remis à l'admin
     * qui a lancé la mise à jour) : l'utilisateur n'est pas rechargé depuis la base.
     */
    #[Route('/finaliser', name: 'admin_mise_a_jour_finaliser', methods: ['GET'])]
    public function finaliser(Request $request, Maintenance $maintenance, KernelInterface $kernel): Response
    {
        if (!$maintenance->jetonValide($request->query->getString('jeton'))) {
            $this->addFlash('error', 'Lien de finalisation invalide ou expiré.');

            return $this->redirectToRoute('admin_mise_a_jour');
        }

        $console = function (array $arguments) use ($kernel): array {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $sortie = new BufferedOutput();
            $code = $application->run(new ArrayInput($arguments + ['--no-interaction' => true]), $sortie);

            return ['code' => $code, 'sortie' => trim($sortie->fetch())];
        };

        $session = $request->getSession();
        $journal = (array) $session->get('mise_a_jour_journal', []);
        $depuis = (string) $session->get('mise_a_jour_depuis', '?');
        $session->remove('mise_a_jour_journal');
        $session->remove('mise_a_jour_depuis');

        try {
            foreach ($this->installateur->finaliser($console) as $avertissement) {
                $this->addFlash('error', $avertissement);
            }
            $journal[] = 'Base de données à jour, maintenance terminée.';
            $this->addFlash('success', sprintf('GrimpePan est passé de la version %s à la version %s.', $depuis, $this->version->get()));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }
        $session->set('mise_a_jour_dernier_journal', $journal);

        return $this->redirectToRoute('admin_mise_a_jour');
    }
}
