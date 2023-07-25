<?php

require 'autoload.php';

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

/** @var eZUser $user */
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$dataTypeStringList = [
    'weather',
    'mugoobjectrelationlist',
    'ocwhatsapp',
];

foreach ($dataTypeStringList as $dataTypeString) {
    $classIds = eZContentClass::fetchIDListContainingDatatype($dataTypeString);
    foreach ($classIds as $id) {
        $classId = (int)$id;
        $contentClass = eZContentClass::fetch($classId);
        if ($contentClass) {
            $cli->warning("Remove class " . $contentClass->attribute('identifier') . " with datatype $dataTypeString");
            eZContentClassOperations::remove($classId);
            ezpEvent::getInstance()->notify('content/class/cache', [$classId]);
            eZCache::clearByID(['classid', 'ocopendata_classes']);
        }
    }
}

$script->shutdown();

