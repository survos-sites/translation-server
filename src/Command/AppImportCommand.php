<?php

namespace App\Command;

use JsonMachine\Items;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

        foreach ($sources as $id => $source) {
            dd($source);
        }

        return self::SUCCESS;
    }
}
