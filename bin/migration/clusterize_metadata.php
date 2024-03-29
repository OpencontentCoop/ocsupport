<?php

require 'autoload.php';

$script = eZScript::instance([
    'description' => ("Clusterize\n\n"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();

$options = $script->getOptions(
    '[key;][secret;][bucket:][region:]',
    '',
    []
);
$script->initialize();
$script->setUseDebugAccumulators(true);
$cli = eZCLI::instance();
$startTime = new eZDateTime();

/** @var eZClusterFileHandlerInterface $fileHandler */
$fileHandler = eZClusterFileHandler::instance();

if ($options['key']) {
    putenv("AWS_ACCESS_KEY_ID=" . $options['key']);
}
if ($options['secret']) {
    putenv("AWS_SECRET_ACCESS_KEY=" . $options['secret']);
}
putenv("AWS_REGION=" . $options['region']);
putenv("AWS_BUCKET=" . $options['bucket']);

$dispatcher = OpenPADFSFileHandlerDFSDispatcher::build();

/** @var eZDFSFileHandlerPostgresqlBackend $dbBackend */
$dbBackend = eZExtension::getHandlerClass(
    new ezpExtensionOptions(
        [
            'iniFile' => 'file.ini',
            'iniSection' => 'eZDFSClusteringSettings',
            'iniVariable' => 'DBBackend',
        ]
    )
);
$dbBackend->_connect();

$fileINI = eZINI::instance('file.ini');
if (
    $fileINI->variable('ClusterEventsSettings', 'ClusterEvents') === 'enabled'
    && $dbBackend instanceof eZClusterEventNotifier
) {
    $listener = eZExtension::getHandlerClass(
        new ezpExtensionOptions(
            [
                'iniFile' => 'file.ini',
                'iniSection' => 'ClusterEventsSettings',
                'iniVariable' => 'Listener',
                'handlerParams' => [new eZClusterEventLoggerEzdebug()],
            ]
        )
    );

    if ($listener instanceof eZClusterEventListener) {
        $dbBackend->registerListener($listener);
        $listener->initialize();
    }
}

function fileStore($filePath, $scope = false, $datatype = false)
{
    global $dispatcher, $dbBackend, $cli;

    $filePath = eZDFSFileHandler::cleanPath($filePath);

    if ($scope === false) {
        $scope = 'UNKNOWN_SCOPE';
    }

    if ($datatype === false) {
        $datatype = 'misc';
    }

    $contentLength = $dispatcher->getDfsFileSize($filePath);
    if ($contentLength) {
        return $dbBackend->storeClusterizedFileMetadata($filePath, $datatype, $scope, $contentLength);
    }

    return false;
}

function filePathForBinaryFile($fileName, $mimeType)
{
    $storageDir = eZSys::storageDirectory();
    list($group, $type) = explode('/', $mimeType);
    $filePath = $storageDir . '/original/' . $group . '/' . $fileName;
    return $filePath;
}

if (!is_object($fileHandler)) {
    $cli->error("Clustering settings specified incorrectly or the chosen file handler is ezfs.");
    $script->shutdown(1);
} elseif (!$fileHandler->requiresClusterizing()) {
    $message = "The current cluster handler (" . get_class($fileHandler) . ") doesn't require/support running this script";
    $cli->output($message);
    $script->shutdown(0);
}

$db = eZDB::instance();
$remove = false;

$cli->warning("Importing binary files");
$rows = $db->arrayQuery('select distinct filename, mime_type from ezbinaryfile');
$total = count($rows);
foreach ($rows as $index => $row) {
    if ($row['filename'] == '') {
        continue;
    }

    $filePath = filePathForBinaryFile($row['filename'], $row['mime_type']);
    $cli->output("$index/$total - " . $filePath . " ", false);
    if (fileStore($filePath, 'binaryfile')) {
        $cli->output('OK');
    } else {
        $cli->error('K0');
    }
}
$cli->output();

$cli->warning("Importing media and ezflowmedia files");
$rows = $db->arrayQuery('select distinct filename, mime_type from ezmedia');
$total = count($rows);
foreach ($rows as $index => $row) {
    if ($row['filename'] == '') {
        continue;
    }

    $filePath = filePathForBinaryFile($row['filename'], $row['mime_type']);
    $cli->output("$index/$total - " . $filePath . " ", false);
    if (fileStore($filePath, 'mediafile')) {
        $cli->output('OK');
    } else {
        $cli->error('K0');
    }
}
$cli->output();

$cli->warning("Importing images and imagealiases files");
$rows = $db->arrayQuery('select distinct filepath from ezimagefile');
$total = count($rows);
foreach ($rows as $index => $row) {
    if ($row['filepath'] == '') {
        continue;
    }

    $filePath = $row['filepath'];
    $cli->output("$index/$total - " . $filePath . " ", false);

    $mimeData = eZMimeType::findByFileContents($filePath);
    if (fileStore($filePath, 'image', $mimeData['name'])) {
        $cli->output('OK');
    } else {
        $cli->error('K0');
    }
}
$cli->output();


$script->shutdown();