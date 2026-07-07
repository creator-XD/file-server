<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $login;

    #[ORM\Column(name: 'password_hash', type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20, options: ['default' => 'free'])]
    private string $plan = 'free';

    public function __construct(string $login, string $passwordHash)
    {
        $this->login = $login;
        $this->passwordHash = $passwordHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function getPlan(): string
    {
        return $this->plan;
    }
    public function setPlan(string $plan): void
    {
        $allowedPlans = ['free', 'pro'];

        if (!in_array($plan, $allowedPlans, true)) {
            throw new \InvalidArgumentException('Unknown plan');
        }

        $this->plan = $plan;
    }
}