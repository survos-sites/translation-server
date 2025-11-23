<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\StrTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperTrait;

/** @extends ServiceEntityRepository<StrTranslation> */
final class StrTranslationRepository extends ServiceEntityRepository implements QueryBuilderHelperInterface
{
    use QueryBuilderHelperTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StrTranslation::class);
    }

    /**
     * Count translations in the "translated" place for a specific
     * SOURCE locale and TARGET locale. Optionally filter by engine.
     */
    public function countTranslatedForSourceTarget(string $sourceLocale, string $targetLocale, ?string $engine = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.hash)')
            ->join('t.str', 's')
            ->where('s.srcLocale = :src')
            ->andWhere('t.locale = :loc')
            ->andWhere('t.marking = :translated')
            ->setParameter('src', $sourceLocale)
            ->setParameter('loc', $targetLocale)
            ->setParameter('translated', 'translated');

        if ($engine !== null && $engine !== '') {
//            $qb->andWhere('t.engine = :engine')->setParameter('engine', $engine);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
