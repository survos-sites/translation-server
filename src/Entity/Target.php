<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TargetRepository;
use App\Workflow\TargetWorkflowInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\BabelBundle\Util\HashUtil;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Survos\StateBundle\Traits\MarkingInterface;
use Survos\StateBundle\Traits\MarkingTrait;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TargetRepository::class)]
#[ORM\UniqueConstraint(
    name: 'target_unique_idx',
    fields: ['targetLocale', 'source'] // engine dropped from uniqueness
)]
#[ORM\Index(name: 'target_source', fields: ['source'])]
#[ORM\Index(name: 'target_marking', fields: ['marking'])]
#[ORM\HasLifecycleCallbacks]
#[ApiFilter(filterClass: SearchFilter::class, properties: [
    'key'          => 'exact',
    'targetLocale' => 'exact',
    'marking'      => 'exact',
])]
#[ApiResource]
#[Get]
#[GetCollection]
class Target implements RouteParametersInterface, MarkingInterface
{
    use MarkingTrait;
    use RouteParametersTrait;

    public const UNIQUE_PARAMETERS = ['targetId' => 'key'];

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'targets', fetch: 'EXTRA_LAZY')]
        #[ORM\JoinColumn(nullable: false)]
        public ?Source $source = null,

        #[ORM\Column(length: 6)]
        #[Groups(['target.read', 'target.write', 'source.export'])]
        public ?string $targetLocale = null,

        #[ORM\Column(length: 12, nullable: true)]
        #[Groups(['target.read', 'target.write', 'source.export'])]
        public ?string $engine = null,

        #[ORM\Id]
        #[ORM\Column(length: 32)]
        public ?string $key = null,

        #[ORM\Column(type: Types::TEXT, nullable: true)]
        #[Groups(['target.read', 'target.write', 'source.export'])]
        public ?string $targetText = null,

        #[ORM\Column(nullable: false)]
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable('now'),

        #[ORM\Column(nullable: true)]
        public ?\DateTimeImmutable $updatedAt = null,
    ) {
        if ($this->source) {
            $this->source->addTarget($this);

            $computedKey = self::calcKey($this->source, $this->targetLocale ?? '', $this->engine);

            if ($this->key === null) {
                $this->key = $computedKey;
            } else {
                \assert(
                    $this->key === $computedKey,
                    sprintf('Target key mismatch: "%s" != "%s"', $this->key, $computedKey)
                );
            }
        }

        // default marking if not set by MarkingTrait
        $this->marking ??= TargetWorkflowInterface::PLACE_UNTRANSLATED;
    }

    public $snippet {
        get => \mb_substr($this->targetText ?? '', 0, 40, 'UTF-8');
    }

    public $length {
        get => \mb_strlen($this->targetText ?? '');
    }

    public bool $isTranslated { get => $this->marking === TargetWorkflowInterface::PLACE_TRANSLATED; }
    public bool $isIdentical { get => $this->marking === TargetWorkflowInterface::PLACE_IDENTICAL; }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updatedTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public static function calcKey(Source $source, string $targetLocale, ?string $engine = null): string
    {
        return HashUtil::calcTranslationKey($source->hash, $targetLocale, $engine);
    }

    public function getId(): string
    {
        return $this->key ?? '';
    }
}
