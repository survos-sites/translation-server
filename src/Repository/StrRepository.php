<?php
declare(strict_types=1);

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Str;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperTrait;

/** @extends ServiceEntityRepository<Str> */
final class StrRepository extends ServiceEntityRepository implements QueryBuilderHelperInterface
{
    use QueryBuilderHelperTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Str::class);
    }

    /** @return list<string> */
    public function listSourceLocales(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('DISTINCT s.srcLocale')
            ->orderBy('s.srcLocale', 'ASC');

        return array_map('strval', array_column($qb->getQuery()->getScalarResult(), 'srcLocale'));
    }

    /** Count Str rows for a given source locale. */
    public function countBySourceLocale(string $sourceLocale): int
    {
        return (int)$this->createQueryBuilder('s')
            ->select('COUNT(s.hash)')
            ->where('s.srcLocale = :src')
            ->setParameter('src', $sourceLocale)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Return all locales present in the DB that are not in the given list of source locales.
     * Adjust if you store locales only in StrTr.
     * @param list<string> $sourceLocales
     * @return list<string>
     */
    public function listAllLocalesExcludingSources(array $sourceLocales): array
    {
        // If StrTr holds target locales, we could switch to querying StrTr instead.
        $conn = $this->getEntityManager()->getConnection();
        // Fallback: if you store target list elsewhere, replace this method.
        $sql = 'SELECT DISTINCT t.target_locale AS loc 
                FROM str_tr t';
        $rows = $conn->executeQuery($sql)->fetchFirstColumn();
        $rows = array_map('strval', $rows);
        return array_values(array_diff(array_unique($rows), $sourceLocales));
    }
}
