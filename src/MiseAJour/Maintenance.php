<?php

namespace App\MiseAJour;

/**
 * Mode maintenance pendant une mise à jour : public/index.php répond 503 à tout
 * le monde, sauf à la requête de finalisation qui porte le bon jeton.
 *
 * Le verrou expire tout seul après DUREE_MAX, pour qu'une mise à jour
 * interrompue ne bloque pas le site indéfiniment.
 */
final class Maintenance
{
    public const DUREE_MAX = 1800; // 30 min (même valeur dans public/index.php)

    public function __construct(private readonly string $racine)
    {
    }

    public function fichier(): string
    {
        return $this->racine.'/var/maintenance.lock';
    }

    /** Active la maintenance et renvoie le jeton de la requête de finalisation. */
    public function activer(): string
    {
        $jeton = bin2hex(random_bytes(24));
        $contenu = json_encode(['jeton' => hash('sha256', $jeton), 'depuis' => time()]);
        if (false === @file_put_contents($this->fichier(), $contenu, LOCK_EX)) {
            throw new \RuntimeException('Impossible d\'activer le mode maintenance (var/ non accessible en écriture).');
        }

        return $jeton;
    }

    public function jetonValide(string $jeton): bool
    {
        $d = json_decode((string) @file_get_contents($this->fichier()), true);

        return \is_array($d) && isset($d['jeton'], $d['depuis'])
            && time() - (int) $d['depuis'] < self::DUREE_MAX
            && hash_equals((string) $d['jeton'], hash('sha256', $jeton));
    }

    public function desactiver(): void
    {
        @unlink($this->fichier());
    }
}
