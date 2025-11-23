<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\StrTranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Survos\LinguaBundle\Workflow\StrTrWorkflowInterface;
use Survos\StateBundle\Traits\MarkingInterface;
use Survos\StateBundle\Traits\MarkingTrait;
use Survos\BabelBundle\Entity\Base\StrTranslationBase;

#[ORM\Entity(repositoryClass: StrTranslationRepository::class)]
//#[ORM\UniqueConstraint(columns: ['str_hash', 'locale'])]
#[ORM\Table('tr')]
class StrTranslation extends StrTranslationBase implements MarkingInterface
{
    use MarkingTrait;

    /**
     * Owning side. FK column is str_hash; it references str.hash.
     * onDelete CASCADE is usually what you want.
     */
    #[ORM\ManyToOne(targetEntity: Str::class, inversedBy: 'translations', fetch: 'LAZY')]
    #[ORM\JoinColumn(name: 'str_hash', referencedColumnName: 'hash', nullable: false, onDelete: 'CASCADE')]
    public ?Str $str = null;

    public function init(): void
    {
        $this->marking = StrTrWorkflowInterface::PLACE_NEW;
        dump($this->hash, $this->strHash);

    }

    public bool $isTranslated {
        get =>  $this->marking === StrTrWorkflowInterface::PLACE_TRANSLATED;
    }

}
