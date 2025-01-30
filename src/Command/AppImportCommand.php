<?php

namespace App\Command;

use App\Entity\Source;
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

#[AsCommand('app:import', 'Import a json dump file to the database')]
final class AppImportCommand extends InvokableServiceCommand
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
        #[Autowire('%kernel.project_dir%/public/data/')] string $publicDir,
        #[Argument(description: 'path where the json file will be read')]
        string $path = 'dump.json',

        #[Option(description: 'overwrite the database entry')]
        bool $overwrite = false,
    ): int {
        $io->success($this->getName().' success.');


        $sources = Items::fromFile($publicDir .  $path);
        foreach ($sources as $idx => $row) {
            if (!$source = $this->sourceRepository->findOneBy(['hash' => $row->hash])) {
                $source = new Source($row->text, $row->locale, $row->hash);
            }
            dd($source, $row);
        }

        return self::SUCCESS;
    }
}
