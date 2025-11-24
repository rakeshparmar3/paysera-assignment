<?php
// src/Entity/Account.php
namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    private string $owner;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $balance = '0.00';

    #[ORM\Column(type: 'string', length: 3)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 3)]
    private string $currency;

    #[ORM\Column(type: 'integer')]
    #[ORM\Version]
    private int $version = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): self
    {
        $this->balance = $balance;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function debit(string $amount): void
    {
        if (bccomp($this->balance, $amount, 2) < 0) {
            throw new \DomainException('Insufficient funds');
        }
        $this->balance = bcsub($this->balance, $amount, 2);
    }

    public function credit(string $amount): void
    {
        $this->balance = bcadd($this->balance, $amount, 2);
    }
}