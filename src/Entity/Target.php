<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TargetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TargetRepository::class)]
#[ApiResource]
class Target
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 22)]
    private ?string $key = null;

    #[ORM\ManyToOne(inversedBy: 'targets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Source $source = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $targetText = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getTargetText(): ?string
    {
        return $this->targetText;
    }

    public function setTargetText(?string $targetText): static
    {
        $this->targetText = $targetText;

        return $this;
    }
}
