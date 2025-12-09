<?php

use Castor\Attribute\AsTask;

use function Castor\{io,run,capture,import};
import('.castor/vendor/tacman/castor-tools/castor.php');


#[AsTask('build', description: 'setup for testing')]
function build(): void
{
//    run('bin/console doctrine:schema:validate');


}

#[AsTask('euro', description: 'Import the Europeana dataset')]
function euro_import(): void
{
    run('bin/console agg:load euro');
    io()->write("Euro Inst loaded");
    // this fires up the workflow and will automatically navigate if the workers are running
    run('bin/console state:iterate Inst --marking new -t download -v --limit 3 --sync');
}



#[AsTask(description: 'Welcome to Castor!')]
function hello(): void
{
    $currentUser = capture('whoami');

    io()->title(sprintf('Hello %s!', $currentUser));
}
