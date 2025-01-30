<?php

namespace App\Command;

use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Zenstruck\Bytes;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use \ZipArchive;

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
        #[Autowire('%kernel.project_dir%/public/data/')] string $publicDir,

        #[Argument(description: 'path where the json file will be written')]
        string $path = 'dump.json',

        #[Option(description: 'zip upon completion')]
        bool   $zip = true,

        #[Option(description: 'limit the number of records')]
        int $limit = 0
    ): int
    {

        $count =  $this->sourceRepository->count();
        $progressBar = new ProgressBar($io->output(), $count);
        $progressBar->setFormat(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $qb =  $this->sourceRepository->createQueryBuilder('s')
            ->getQuery()
            ->toIterable();
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0777, true);
        }
        $f = fopen($filename = $publicDir . $path, 'w');
        fwrite($f, "[");
        foreach ($qb as $idx => $source) {
            $progressBar->advance();
            if ($idx) fwrite($f, "\n,\n");
            $json = $this->serializer->serialize($source, 'json', ['groups' => ['source.export', 'source.read']]);
//            dd(json_encode(json_decode($json), JSON_PRETTY_PRINT));
            fwrite($f, $json);
            if ($limit && ($idx >= $limit)) {
                break;
            }
        }
        fwrite($f, "\n]");
        fclose($f);
        $progressBar->finish();


        if ($zip) {
            $zip = new ZipArchive;
            if ($zip->open($zipFile = $publicDir . 'all.zip', ZipArchive::CREATE)) {
                // add the count so that we can use progressBar when importing.  Or add a meta file?
                $zip->addFile($filename, sprintf('translations-%s.json', $idx));
                $zip->close();
                dump(filesize($zipFile));
                $io->success($zipFile . sprintf(" written with $idx records %s",  Bytes::parse(filesize($zipFile))));
            } else {
                $io->error('Failed to open zip file. '.$zipFile );
            }
        } else {
            $io->success($filename . " written with $idx records: " );
        }

        return self::SUCCESS;
    }
}
