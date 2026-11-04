<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crée un compte (par exemple l'administrateur, utilisé par l'assistant d'installation).
 *
 *   php bin/console app:utilisateur:creer admin@exemple.org Admin --admin
 */
#[AsCommand(name: 'app:utilisateur:creer', description: 'Crée un compte utilisateur (option --admin pour un administrateur)')]
class CreerUtilisateurCommand
{
    public const LONGUEUR_MIN_MOT_DE_PASSE = 8;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse email (identifiant de connexion)')] string $email,
        #[Argument(description: 'Pseudo affiché')] string $pseudo,
        #[Option(description: 'Donne le rôle administrateur')] bool $admin = false,
        #[Option(description: 'Mot de passe (demandé de façon masquée si absent)', name: 'mot-de-passe')] ?string $motDePasse = null,
    ): int {
        $motDePasse ??= $io->askHidden('Mot de passe');
        if (mb_strlen((string) $motDePasse) < self::LONGUEUR_MIN_MOT_DE_PASSE) {
            $io->error(sprintf('Le mot de passe doit faire au moins %d caractères.', self::LONGUEUR_MIN_MOT_DE_PASSE));

            return Command::FAILURE;
        }

        $user = (new User())->setEmail(trim($email))->setPseudo(trim($pseudo));
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $erreurs = $this->validator->validate($user);
        if (\count($erreurs) > 0) {
            foreach ($erreurs as $e) {
                $io->error($e->getMessage());
            }

            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $motDePasse));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('%s « %s » créé (%s).', $admin ? 'Administrateur' : 'Utilisateur', $user->getPseudo(), $user->getEmail()));

        return Command::SUCCESS;
    }
}
