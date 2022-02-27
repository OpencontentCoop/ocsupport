<?php

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance(['description' => ("Rrrrreindex\n\n"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true]);

$script->startup();

$options = $script->getOptions('[from:][class:]',
    '',
    [
        'class' => 'Class identifier',
        'from' => 'From modified date YYYY-MM-YY',
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$searchEngine = eZSearch::getEngine();

if (!$searchEngine instanceof ezpSearchEngine) {
    $cli->error("The configured search engine does not implement the ezpSearchEngine interface or can't be found.");
    $script->shutdown(1);
}

$classId = false;
if ($options['class']) {
    $classId = eZContentClass::classIDByIdentifier($options['class']);
    if (!$classId) {
        $cli->error("Class " . $options['class'] . " not found");
    } else {
        $cli->output("Filter by class " . $options['class']);
    }
}
$modified = false;
if ($options['from']) {
    $modified = strtotime($options['from']);
    $cli->output("Filter from modified " . date('Y-m-j', $modified));
}

$def = eZContentObject::definition();
$conds = [];
if ($classId) {
    $conds['contentclass_id'] = $classId;
}
if ($modified) {
    $conds['modified'] = ['>=', $modified];
}
$count = (int)eZPersistentObject::count($def, $conds);

$sort = ['id' => 'desc'];

$output = new ezcConsoleOutput();
$progressBar = new ezcConsoleProgressbar($output, $count, ['emptyChar' => ' ', 'barChar' => '=']);

$length = 50;
$limit = ['offset' => 0, 'limit' => $length];
$cli->output("Reindex $count objects");
$progressBar->start();
do {
    // clear in-memory object cache
    eZContentObject::clearCache();
    $objects = eZPersistentObject::fetchObjectList($def, null, $conds, $sort, $limit);
    foreach ($objects as $object) {
        $searchEngine->removeObjectById($object->attribute("id"), false);
        $searchEngine->addObject($object, false);
        $progressBar->advance();
    }
    $searchEngine->commit();
    $limit['offset'] += $length;
} while (count($objects) == $length);

$cli->output();

$script->shutdown();
