<?php

require 'autoload.php';

use Opencontent\Installer\Installer;

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => (""),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();

$options = $script->getOptions();
$script->initialize();
$script->setUseDebugAccumulators(true);

$logger = new InstallerToolsLogger();
$logger->isVerbose = $options['verbose'];

$currentIdentifier = InstallerTools::getInBackgroundInstalling();
if ($currentIdentifier) {
    $currentInstaller = InstallerTools::getInstaller($currentIdentifier);
    $cli->output('Run installer ' . $currentIdentifier);

    $db = eZDB::instance();
    try {
        $logger->info('Purge trash');
        $purgeHandler = new eZScriptTrashPurge($cli);
        $purgeHandler->run();

        $logger->info(
            'Run installer ' . $currentIdentifier . ' from ' . $currentInstaller['data_dir']
        );
        $installer = new Installer($db, $currentInstaller['data_dir'], $logger);

        if ($currentInstaller['can_update'] && !$installer->canUpdate()) {
            $logger->info("No update needed or module is not installed");
        } elseif (!$installer->needUpdate()) {
            $logger->info("No update needed");
        } else {
            $installer->getInstallerVars()['schema_already_exists'] = true;
            $installer->getInstallerVars()['is_install_from_scratch'] = false;
            $installer->install();

            $logger->info("Clear all cache");
            $cacheHelper = new eZCacheHelper($cli, $script);
            $cacheHelper->clearItems(eZCache::fetchList(), false);
        }

        InstallerTools::setInBackgroundInstalling($currentIdentifier, false);
        $logger->info('Success!');

    } catch (Throwable $exception) {
        $logger->critical($exception->getMessage());
        if ($options['verbose']) {
            $logger->critical($exception->getTraceAsString());
        }
        $cli->error($exception->getMessage());
        InstallerTools::setInBackgroundInstalling($currentIdentifier, false);
        $logger->critical('Failure!');
    }
}


$script->shutdown();
