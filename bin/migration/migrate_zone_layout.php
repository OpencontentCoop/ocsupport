<?php

use Opencontent\Opendata\Api\PublicationProcess;

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance([
    'description' => ("Migra i zone_layout in default"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();

$options = $script->getOptions(
    '[node:][run]',
    '',
    [
        'node' => 'Solo nel nodo selezionato (node_id)',
        'run' => 'Esegue le modifiche',
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));
$isDryRun = !$options['run'];

function migrateZone(eZContentObject $object)
{
    global $isDryRun;
    $doMigrate = false;
    $version = $object->currentVersion();
    $availableLanguages = $version->translationList(false, false);
    foreach ($availableLanguages as $languageCode) {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $object->dataMap();
        foreach ($dataMap as $attribute) {
            if ($attribute->attribute('data_type_string') == eZPageType::DATA_TYPE_STRING && $attribute->hasContent()) {
                /** @var eZPage $page */
                $page = $attribute->content();
                $zones = $page->attribute('zone_layout');

                eZCLI::instance()->output('#' . $object->attribute('main_node_id') . ' ' . $object->attribute('name'));
                eZCLI::instance()->warning('  - ' . $zones);
                foreach ($page->attribute('zones') as $zone) {
                    /** @var eZPageBlock $block */
                    foreach ($zone->attribute('blocks') as $block) {
                        eZCLI::instance()->output(
                            '      - ' . $block->attribute('type') . ' ' . $block->attribute('view')
                        );
                    }
                }
                $page = new \Opencontent\Opendata\Api\AttributeConverter\Page(
                    $object->contentClassIdentifier(),
                    $attribute->contentClassAttributeIdentifier()
                );
                $pageContent = $page->get($attribute)['content'];

                if (is_array($pageContent)) {
                    if ($zones !== 'desItaGlobal') {
                        $pageContent['zone_layout'] = 'desItaGlobal';
                        foreach (['main', 'right'] as $z) {
                            if (isset($pageContent[$z])) {
                                $pageContent['global'] = $pageContent[$z];
                                unset($pageContent[$z]);
                            }
                        }
                        $doMigrate = true;
                    }
                    $pageContent['global']['zone_id'] = 'mm_'.substr($pageContent['global']['zone_id'], 0, 29);
                    foreach ($pageContent['global']['blocks'] as $index => $block){
                        $typeView = $block['type'] . '-' . $block['view'];
                        switch ($typeView){
                            case 'Lista-lista_carousel':
                                $pageContent['global']['blocks'][$index]['block_id'] = 'mm_'.substr($pageContent['global']['blocks'][$index]['block_id'], 0, 29);
                                $pageContent['global']['blocks'][$index]['type'] = 'ListaPaginata';
                                $pageContent['global']['blocks'][$index]['view'] = 'lista_paginata_card';
                                $pageContent['global']['blocks'][$index]['custom_attributes']['limite'] = 3;
                                $pageContent['global']['blocks'][$index]['custom_attributes']['elementi_per_riga'] = 3;
                                $pageContent['global']['blocks'][$index]['custom_attributes']['color_style'] = 'section section-muted section-inset-shadow pb-5';
                                $doMigrate = true;
                                break;
                            case 'Lista-lista_banner':
                                $pageContent['global']['blocks'][$index]['block_id'] = 'mm_'.substr($pageContent['global']['blocks'][$index]['block_id'], 0, 29);
                                $pageContent['global']['blocks'][$index]['type'] = 'ListaAutomatica';
                                $pageContent['global']['blocks'][$index]['view'] = 'lista_banner';
                                $doMigrate = true;
                                break;
                            case 'Lista-lista_accordion':
                                $pageContent['global']['blocks'][$index]['block_id'] = 'mm_'.substr($pageContent['global']['blocks'][$index]['block_id'], 0, 29);
                                $pageContent['global']['blocks'][$index]['type'] = 'ListaAutomatica';
                                $doMigrate = true;
                                break;
                            case 'Lista3-lista_tab':
                                $pageContent['global']['blocks'][$index]['block_id'] = 'mm_'.substr($pageContent['global']['blocks'][$index]['block_id'], 0, 29);
                                $pageContent['global']['blocks'][$index]['type'] = 'ListaManuale';
                                $pageContent['global']['blocks'][$index]['view'] = 'lista_card_teaser';
                                $doMigrate = true;
                                break;
                            case 'Singolo-singolo_box':
                            case 'Singolo-singolo_full':
                                $pageContent['global']['blocks'][$index]['block_id'] = 'mm_'.substr($pageContent['global']['blocks'][$index]['block_id'], 0, 29);
                                $pageContent['global']['blocks'][$index]['view'] = 'alt';
                                $doMigrate = true;
                                break;
                            case 'Singolo-singolo_box_piccolo':
                                $pageContent['global']['blocks'][$index]['block_id'] = 'mm_'.substr($pageContent['global']['blocks'][$index]['block_id'], 0, 29);
                                $pageContent['global']['blocks'][$index]['view'] = 'default';
                                $doMigrate = true;
                                break;
                            case 'Singolo-singolo_img':
                                $pageContent['global']['blocks'][$index]['block_id'] = 'mm_'.substr($pageContent['global']['blocks'][$index]['block_id'], 0, 29);
                                $pageContent['global']['blocks'][$index]['view'] = 'image';
                                $doMigrate = true;
                                break;
                            default:
                                if (eZINI::instance('block.ini')->hasGroup($block['type'])){
                                    $ViewList = eZINI::instance('block.ini')->variable($block['type'], 'ViewList');
                                    if (!in_array($block['view'], $ViewList)){
                                        eZCLI::instance()->error('??? ' . $block['view']);
                                    }
                                }else{
                                    eZCLI::instance()->error('??? ' . $block['type']);
                                }
                        }
                    }
                    if (!$isDryRun) {
                        if ($doMigrate) {

                            $repository = new \Opencontent\Opendata\Api\ContentRepository();
                            $repository->setCurrentEnvironmentSettings(new DefaultEnvironmentSettings());
                            try {
                                $repository->update([
                                    'metadata' => ['id' => (int)$object->attribute('id')],
                                    'data' => [
                                        $attribute->contentClassAttributeIdentifier() => $pageContent
                                    ],
                                ]);
                            }catch (Exception $e){
                                eZCLI::instance()->error($e->getMessage());
                            }
//
//                            $xml = $page->set($pageContent, new PublicationProcess(false));
//                            $attribute->setAttribute('data_text', $xml);
//                            $attribute->store();
//                            $attribute->dataType()->onPublish($attribute, $object, $object->assignedNodes());
                            eZCLI::instance()->output('...done');
                        } else {
                            eZCLI::instance()->output('...skip');
                        }
                    }
                    eZCLI::instance()->output();
                }
            }
        }
    }
}

$node = (int)$options['node'];

if ($node > 0) {
    $cli->output("Cerca nel node " . $node);
    $object = eZContentObject::fetchByNodeID($node);
    migrateZone($object);
} else {
    $classIdList = eZContentClass::fetchIDListContainingDatatype(eZPageType::DATA_TYPE_STRING);
    foreach ($classIdList as $classId) {
        $class = eZContentClass::fetch($classId);

        $cli->output("Search for " . $class->attribute('name'));
        /** @var eZContentObject[] $objects */
        $objects = $class->objectList();
        foreach ($objects as $object) {
            $run = true;
            if ($run) {
                migrateZone($object);
            }
            eZContentObject::clearCache();
        }

        eZCLI::instance()->output();
    }
}


$script->shutdown();
