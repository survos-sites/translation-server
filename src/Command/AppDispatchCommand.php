<?php

namespace App\Command;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\Items;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;

#[AsCommand('app:dispatch', 'dispatch messages from the database')]
final class AppDispatchCommand extends InvokableServiceCommand
{
    use RunsCommands;
    use RunsProcesses;

    public function __construct(
        private SourceRepository $sourceRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
    )
    {
        parent::__construct();
    }

    public function __invoke(
        IO $io,
        #[Argument()] ?string $action=null,
        #[Option(description: 'overwrite the database entry')]
        string $marking = Target::PLACE_UNTRANSLATED,

        #[Option(description: 'limit source language')]
        ?string $from=null,

        #[Option(description: 'limit (or add) target languages, comma-delimited')]
        ?string $toString=null,

        #[Option(description: 'limit the number of records')]
        int $limit = 0

    ): int {

//        if ($purgeUntranslated) {
            // ./c d:run "delete from target where marking='u'"
//        }

        if (!$action) {
            $io->writeln("Actions: 'dispatch, translate");
            return self::SUCCESS;
        }

        $qb =  $this->sourceRepository->createQueryBuilder('s');
        if ($from) {
            $qb->andWhere('s.from = :from')
            ->setParameter('from', $from);
            // hmm.  target marking?
        }
        $rows = $qb
            ->getQuery()
            ->toIterable();
        foreach ($rows as $row) {
            dump($row);
            switch ($action) {
                case 'translate':
                    // batch and call api
                    foreach (explode(',', $toString) as $to) {
                        $items[] = $row->getText();
                    }
                    break;
                case 'dispatch':
                    dd($toString);
        }



        }
        return self::SUCCESS;
    }
}
