<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\StrRepository;
use Doctrine\ORM\Mapping as ORM;
use Survos\StateBundle\Traits\MarkingInterface;
use Survos\StateBundle\Traits\MarkingTrait;
use Survos\BabelBundle\Entity\Base\StrBase;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: StrRepository::class)]
#[ORM\Table(name: 'str')]
class Str extends StrBase implements MarkingInterface
{
    use MarkingTrait;

    #[ORM\OneToMany(
        mappedBy: 'str',
        targetEntity: StrTranslation::class,
        cascade: ['persist'],
        orphanRemoval: false,
        fetch: 'EXTRA_LAZY',
        indexBy: 'locale'
    )]
    private Collection $translations;

    public function __construct(string $hash, string $original, string $srcLocale, ?string $context = null)
    {
        parent::__construct($hash,$original,$srcLocale,$context);
        $this->translations = new ArrayCollection();
    }

    /** @return Collection<string, StrTranslation> keyed by locale (because of indexBy) */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(StrTranslation $tr): void
    {
        if (!$this->translations->contains($tr)) {
            $this->translations->add($tr);
            $tr->str = $this;
        }
    }

    public function removeTranslation(StrTranslation $tr): void
    {
        if ($this->translations->removeElement($tr)) {
            if ($tr->str === $this) {
                $tr->str = null;
            }
        }
    }
}
