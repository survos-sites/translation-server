<?php

namespace App\Command;

use App\Controller\ApiController;
use App\Entity\Source;
use App\Entity\Target;
use App\Message\TranslateTarget;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\Items;
use Survos\LibreTranslateBundle\Dto\TranslationPayload;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
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
        private SourceRepository                              $sourceRepository,
        private EntityManagerInterface                        $entityManager,
        private SerializerInterface                           $serializer,
        private MessageBusInterface                           $messageBus,
        private ApiController                                 $apiController,
        #[Autowire('%kernel.enabled_locales%')] private array $supportedLocales,
        private readonly TargetRepository                     $targetRepository,
    )
    {
        parent::__construct();
    }

    public function __invoke(
        IO                    $io,
        #[Argument()] ?string $action = null, // source or target.
        #[Option(description: 'overwrite the database entry')]
        string                $marking = Target::PLACE_UNTRANSLATED,

        #[Option(description: 'limit source language')]
        ?string               $from = 'en',

        #[Option(name: 'to', description: 'limit (or add) target languages, comma-delimited')]
        ?string               $toString = null,

        #[Option(description: 'sync or async')]
        ?string               $transport = null,

        #[Option(description: 'limit the number of records')]
        int                   $limit = 0,

        #[Option(description: 'batch size for dispatch')]
        int                   $batch = 100,


    ): int
    {

        if ($action ==='source') {
            assert($toString, "require tostring for now");
            $to = $toString ? explode(',', $toString) : $this->supportedLocales;
        }


//        if ($purgeUntranslated) {
        // ./c d:run "delete from target where marking='u'"
//        }

        if (!$action) {
            $io->writeln("Actions: 'source, target");
            return self::SUCCESS;
        }

        if ($action === 'target') {
            $qb = $this->targetRepository->createQueryBuilder('t');
            if ($marking) {
                $qb->andWhere('t.marking = :marking')
                    ->setParameter('marking', $marking);
            }
            if ($limit) {
                $qb->setMaxResults($limit);
            }
            $stamps = [];
            if ($transport) {
                $stamps[] = new TransportNamesStamp([$transport]);
            }
            $count = 0;
            /** @var Target $target */
            foreach ($qb->getQuery()->getResult() as $idx => $target) {
                $this->messageBus->dispatch(new TranslateTarget(
                    $target->getKey(),
                ),
                    $stamps,
                );
                $count++;
            }
            $io->writeln("Finished dispatching " . $count);

            return self::SUCCESS;

        }

        $qb = $this->sourceRepository->createQueryBuilder('s');
        if ($from) {
            $qb->andWhere('s.locale = :from')
                ->setParameter('from', $from);
            // hmm.  target marking?
        }
        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $rows = $qb
            ->getQuery()
            ->toIterable();

        $items = [];
        $count = $this->sourceRepository->count(['locale' => $from]);
        $progressBar = new ProgressBar($io, $count);
        $progressBar->start();
        foreach ($rows as $idx => $row) {
            $progressBar->advance();
            $items[] = $row->getText();
            if ( (count($items) > $batch) || ($progressBar->getProgress() >= $idx)) {
                $results = $this->dispatch($from, $to, $items);
                if ($this->io()->isVeryVerbose()) {
                    dump($results);
                }
                $items=[];
            }
        }

        $progressBar->finish();
        $io->writeln("\nFinished dispatching " . $idx+1 . "\n");
        assert(count($items) == 0, sprintf(" %d <> %d", $progressBar->getProgress(), $idx));
//        $this->dispatch($from, $to, $items);

        return self::SUCCESS;
    }

    private function dispatch(string $locale, array $to, array $items): array
    {
        $results = $this->apiController->dispatch(
            new TranslationPayload(
                from: $locale,
                engine: 'libre',
                forceDispatch: true,
                transport: $this->io()->input()->getOption('transport'),
                to: $to,
                insertNewStrings: true, // new translation targets
                text: $items,
            )
        );
        return json_decode($results->getContent(), true);

    }

}
