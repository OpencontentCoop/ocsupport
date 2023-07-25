<?php
require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance(array(
    'description' => ( "Elenca le zone e i blocchi in uso" ),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions(
    '[node:][subtree:][exclude-subtree:]',
    '',
    array(
        'node' => 'Solo nel nodo selezionato (node_id)',
        'subtree' => 'Solo nel subtree selezionato (node_id)',
        'exclude-subtree' => 'Ad eccezione dei subtree selezionati (node_id separati da virgola)'
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

function getZonesAndBlocks(eZContentObject $object, &$zones, &$blockTypes)
{
    $version = $object->currentVersion();
    $availableLanguages = $version->translationList(false, false);

    foreach ($availableLanguages as $languageCode) {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $object->fetchDataMap(false, $languageCode);
        foreach ($dataMap as $attribute) {
            if ($attribute->attribute('data_type_string') == eZPageType::DATA_TYPE_STRING && $attribute->hasContent()) {
                /** @var eZPage $page */
                $page = $attribute->content();
                $zones[] = $page->attribute('zone_layout');
                $zones = array_unique($zones);
                sort($zones);
                /** @var eZPageZone $zone */
                foreach ($page->attribute('zones') as $zone) {
                    /** @var eZPageBlock $block */
                    foreach ($zone->attribute('blocks') as $block) {
                        if (!isset($blockTypes[$block->attribute('type')])) {
                            $blockTypes[$block->attribute('type')] = array();
                        }

                        $blockTypes[$block->attribute('type')][] = $block->attribute('view');
                        $blockTypes[$block->attribute('type')] = array_unique($blockTypes[$block->attribute('type')]);
                        sort($blockTypes[$block->attribute('type')]);
                    }
                }

            }
        }
    }
}

$zones = array();
$blockTypes = array();

$subtree = (int)$options['subtree'];
$excludeSubtreeList = $options['exclude-subtree'] ? explode(',', $options['exclude-subtree']) : array();
$node = (int)$options['node'];

if ($node > 0) {
    $cli->error("Cerca nel node " . $node);
    $object = eZContentObject::fetchByNodeID($node);
    getZonesAndBlocks($object, $zones, $blockTypes);
} else {
    $classIdList = eZContentClass::fetchIDListContainingDatatype(eZPageType::DATA_TYPE_STRING);
    if ($subtree > 0) {
        $cli->error("Cerca nel subtree " . $subtree);
    } elseif (!empty($excludeSubtreeList)) {
        $cli->error("Esclude i subtree " . implode(' ', $excludeSubtreeList));
    }
    foreach ($classIdList as $classId) {
        $class = eZContentClass::fetch($classId);

        $cli->error("Search for " . $class->attribute('name'));
        /** @var eZContentObject[] $objects */
        $objects = $class->objectList();
        foreach ($objects as $object) {

            $run = true;
            if ($subtree > 0 || !empty($excludeSubtreeList)) {
                $assignedNodes = $object->assignedNodes();
                foreach ($assignedNodes as $assignedNode) {
                    $pathString = explode('/', $assignedNode->attribute('path_string'));
                    if ($subtree > 0) {
                        $run = in_array($subtree, $pathString);
                    } elseif (!empty($excludeSubtreeList)) {
                        foreach ($excludeSubtreeList as $excludeSubtree) {
                            $run = !in_array($excludeSubtree, $pathString);
                            if (!$run) {
                                break;
                            }
                        }
                    }
                }
            }
            if ($run) {
                getZonesAndBlocks($object, $zones, $blockTypes);
            }
            eZContentObject::clearCache();
        }
    }
}

$cli->warning();
$cli->warning("Zone");
foreach ($zones as $zone) {
    $cli->output($zone);
}

$cli->warning("Blocchi");
foreach ($blockTypes as $blockType => $views) {
    $cli->output($blockType);
    foreach ($views as $view) {
        $cli->output(' - ' . $view);
    }
}

$script->shutdown();
