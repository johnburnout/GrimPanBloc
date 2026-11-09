<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
#[UniqueEntity(fields: ['pseudo'], message: 'Ce pseudo est déjà pris.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    private ?string $pseudo = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    /** Un compte désactivé par un administrateur ne peut plus se connecter. */
    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    /**
     * Compte supprimé : email et mot de passe effacés, connexion impossible. Le pseudo
     * reste affiché sur ses voies et ses croix (seul un admin peut supprimer ces voies).
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $supprimeLe = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getSupprimeLe(): ?\DateTimeImmutable
    {
        return $this->supprimeLe;
    }

    public function estSupprime(): bool
    {
        return null !== $this->supprimeLe;
    }

    /** Supprime le compte en gardant le pseudo (voir $supprimeLe). Irréversible. */
    public function supprimer(): static
    {
        $this->supprimeLe = new \DateTimeImmutable();
        $this->email = sprintf('supprime-%d-%s@comptes-supprimes.invalid', $this->id, bin2hex(random_bytes(4)));
        $this->password = '!'; // aucun hash ne correspond
        $this->roles = [];
        $this->actif = false;

        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function setAdmin(bool $admin): static
    {
        $roles = array_values(array_diff($this->roles, ['ROLE_ADMIN']));
        $this->roles = $admin ? [...$roles, 'ROLE_ADMIN'] : $roles;

        return $this;
    }

    /**
     * Comparé à chaque requête avec le compte rechargé depuis la base : un compte
     * désactivé, rétrogradé ou dont le mot de passe a changé est déconnecté aussitôt.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getId() === $this->id
            && $user->getUserIdentifier() === $this->getUserIdentifier()
            && $user->getPassword() === $this->password
            && $user->isActif() === $this->actif
            && $user->getRoles() == $this->getRoles();
    }

    public function eraseCredentials(): void
    {
    }
}
