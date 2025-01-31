<?php

namespace App\Command;

use App\Controller\ApiController;
use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\Items;
use Survos\LibreTranslateBundle\Dto\TranslationPayload;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
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
        private ApiController $apiController,
        #[Autowire('%kernel.enabled_locales%')] private array $supportedLocales,
    )
    {
        parent::__construct();
    }

    public function __invoke(
        IO $io,
        #[Argument()] ?string $action=null, // source or target.
        #[Option(description: 'overwrite the database entry')]
        string $marking = Target::PLACE_UNTRANSLATED,

        #[Option(description: 'limit source language')]
        ?string $from='en',

        #[Option(name: 'to', description: 'limit (or add) target languages, comma-delimited')]
        ?string $toString=null,

        #[Option(description: 'limit the number of records')]
        int $limit = 0,

        #[Option(description: 'batch size for dispatch')]
        int $batch = 100,


    ): int {

        assert($toString, "require tostring for now");
        $to = $toString ? explode(',', $toString): $this->supportedLocales;


//        if ($purgeUntranslated) {
            // ./c d:run "delete from target where marking='u'"
//        }

        if (!$action) {
            $io->writeln("Actions: 'dispatch, translate");
            return self::SUCCESS;
        }

        $qb =  $this->sourceRepository->createQueryBuilder('s');
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
            $items[] = $row->getText();
            if (count($items) > $batch || ($progressBar->getProgress() >= $count)) {
                $this->dispatch($from, $to, $items);
            }
            $progressBar->advance();
        }
        assert(count($items) ==0);
//        $this->dispatch($from, $to, $items);
        $progressBar->finish();

        return self::SUCCESS;
    }

    private function dispatch(string $locale, array $to, array $items): array
    {
        $results = $this->apiController->dispatch(
            new TranslationPayload(
                from: $locale,
                engine: 'libre',
                forceDispatch: true,
                to: $to,
                insertNewStrings: true,
                text: $items,
            )
        );
        return json_decode($results->getContent(), true);

    }

}
