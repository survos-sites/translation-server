<?php

namespace App\Command;

use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;

#[AsCommand('app:export', 'Dump the entire database, and optionally zip it')]
final class AppExportCommand extends InvokableServiceCommand
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
        IO     $io,
        #[Autowire('%kernel.project_dir%/public/')] string $publicDir,
        #[Argument(description: 'path where the json file will be written')]
        string $path = 'dump.json',

        #[Option(description: 'zip upon completion')]
        bool   $zip = true,

        #[Option(description: 'limit the number of records')]
        int $limit = 0
    ): int
    {

        $qb =  $this->sourceRepository->createQueryBuilder('s')
            ->getQuery()
            ->toIterable();
        $f = fopen($filename = $publicDir . $path, 'w');
        fwrite($f, "[");
        foreach ($qb as $idx => $source) {
            $json = $this->serializer->serialize($source, 'json', ['groups' => ['source.read']]);
            fwrite($f, $json);
            if ($limit && ($idx >= $limit)) {
                break;
            }
        }
        fwrite($f, "\n]");
        fclose($f);


        $io->success($filename . " written with $idx records");

        return self::SUCCESS;
    }
}
