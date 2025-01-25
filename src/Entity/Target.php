<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TargetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Survos\WorkflowBundle\Traits\MarkingTrait;

#[ORM\Entity(repositoryClass: TargetRepository::class)]
#[ORM\UniqueConstraint(
    name: 'target_unique_idx',
    fields: ['targetLocale','source','engine']
)]
#[ORM\Index(
    name: 'target_source',
    fields: ['source']
)]
#[ApiResource]
class Target implements RouteParametersInterface, MarkingInterface
{
    use MarkingTrait;
    use RouteParametersTrait;

    const PLACE_UNTRANSLATED='u';
    const PLACE_TRANSLATED='t';
    const PLACE_IDENTICAL='i';
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'targets', fetch: 'EAGER')]
        #[ORM\JoinColumn(nullable: false)]
        private ?Source $source = null,
        #[ORM\Column(length: 6)]
        private ?string $targetLocale = null,
        #[ORM\Column(length: 12)]
        private ?string $engine = null,
        #[ORM\Id]
        #[ORM\Column(length: 32)]
        private ?string $key = null

    ) {
        if ($this->source) {
            $this->source->addTarget($this);
            $this->key = self::calcKey($this->source, $this->targetLocale, $this->engine);
        }
        $this->marking = self::PLACE_UNTRANSLATED;

        // if empty key, calculate, but maybe just require for now.

    }

    public static function calcKey(Source $source, string $targetLocale, string $engine)
    {
        return sprintf('%s-%s-%s', $source->getHash(), $targetLocale, $engine);

    }

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $targetText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bingTranslation = null;

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

    public function getEngine(): ?string
    {
        return $this->engine;
    }

    public function setEngine(string $engine): static
    {
        $this->engine = $engine;

        return $this;
    }

    public function getTargetLocale(): ?string
    {
        return $this->targetLocale;
    }

    public function setTargetLocale(string $targetLocale): static
    {
        $this->targetLocale = $targetLocale;

        return $this;
    }

    public function getBingTranslation(): ?string
    {
        return $this->bingTranslation;
    }

    public function setBingTranslation(?string $bingTranslation): static
    {
        $this->bingTranslation = $bingTranslation;

        return $this;
    }

    public function isUntranslated(): bool
    {
        return $this->getMarking() === self::PLACE_UNTRANSLATED;
    }
    public function isTranslated(): bool
    {
        return $this->getMarking() === self::PLACE_TRANSLATED;
    }
    public function isIdentical(): bool
    {
        return $this->getMarking() === self::PLACE_IDENTICAL;
    }

}
