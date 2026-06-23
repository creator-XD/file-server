<?php

namespace App\Auth;

use App\Entity\User;
use App\Entity\Session;
use Doctrine\ORM\EntityManager;

class LoginService
{
    public function __construct(
        private EntityManager $em
    ) {}

    public function login(string $login, string $password): ?string
    {
        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy(['login' => $login]);

        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user->getPasswordHash())) {
            return null;
        }

        
        $token = bin2hex(random_bytes(32));

        
        $expiresAt = new \DateTimeImmutable('+24 hours');

        $session = new Session($user, $token, $expiresAt);

        $this->em->persist($session);
        $this->em->flush();

        return $token;
    }
}