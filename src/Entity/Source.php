<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Survos\LibreTranslateBundle\Service\TranslationClientService;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
#[ORM\UniqueConstraint(
    name: 'source_hash',
    columns: ['hash'],
)]
#[ApiResource]
#[Get]
#[GetCollection]

class Source implements RouteParametersInterface
{
    use RouteParametersTrait;


    const UNIQUE_PARAMETERS=['sourceId' => 'id'];
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
//    #[ApiProperty(identifier: false)]
    #[Groups(['source.read', 'source.export'])]
    private ?int $id = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function __construct(
        #[ORM\Column(type: Types::TEXT)]
        #[Groups(['source.read', 'source.write'])]
        private ?string $text = null,

        #[ORM\Column(length: 6)]
        #[Groups(['source.read', 'source.write'])]
        private ?string $locale = null,

        #[ORM\Column(length: 18)] // 16 chars + 2 for locale
        #[Groups(['source.read'])]
        private ?string $hash = null,


    ) {
        if (null === $this->hash) {
            if ($this->text) {
                $this->hash = TranslationClientService::calcHash( $this->text, $this->locale );
            }
        }
        $this->targets = new ArrayCollection();
    }




    /**
     * @var Collection<int, Target>
     */
    #[ORM\OneToMany(targetEntity: Target::class, mappedBy: 'source', orphanRemoval: true)]
    #[ORM\OrderBy(['engine'=>'asc'])]
    #[Groups(['source.export'])]
    private Collection $targets;

    #[ORM\Column(nullable: true, options: ['jsonb' => true])]
//    #[Groups(['source.read'])]
    private ?array $existingTranslations = null;


    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getText(?int $trim=null): ?string
    {
        return $trim ? substr($this->text, 0, $trim): $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return Collection<int, Target>
     */
    public function getTargets(): Collection
    {
        return $this->targets;
    }

    public function addTarget(Target $target): static
    {
        if (!$this->targets->contains($target)) {
            $this->targets->add($target);
            // hmm. Could also be a key to save the lookup?
            if (!in_array($target->getTargetLocale(), $this->getExistingTranslations())) {
                $this->existingTranslations[] = $target->getTargetLocale();
            }
            $target->setSource($this);
        }

        return $this;
    }

    public function removeTarget(Target $target): static
    {
        if ($this->targets->removeElement($target)) {
            // set the owning side to null (unless already changed)
            if ($target->getSource() === $this) {
                $target->setSource(null);
            }
        }

        return $this;
    }

    #[Groups(['source.read'])]
    public function getTranslations()
    {
        $translations = [];
        // bing is first, since engines are alphabetical.  hackish
//        $translations[$this->getLocale()] = $this->getText();
        foreach ($this->targets as $target) {
            // bing overrides libre, but we could also have custom or deepl, so not very elegant
            $translations[$target->getTargetLocale()] = $target->getBingTranslation()??$target->getTargetText();
//            if (empty($translations[$target->getTargetLocale()])) {
//                $translations[$target->getTargetLocale()] = $target->getTargetText();
//            }
        }
        return $translations;

    }

    public function setTranslations(?array $translations): static
    {
        $this->existingTranslations = $translations;

        return $this;
    }

    public function getExistingTranslations(): array
    {
        return $this->existingTranslations??[];
    }

    public function setExistingTranslations(?array $existingTranslations): static
    {
        $this->existingTranslations = $existingTranslations;

        return $this;
    }
}
