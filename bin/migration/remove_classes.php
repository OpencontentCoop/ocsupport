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

$classIdentifiers = [
    'determinazione_old',
    'agenda_calendar',
    'agenda_root',
    'booking_root',
    'consultation_survey',
    'consultation_root',
    'dimmi_category',
    'dimmi_forum_reply',
    'dimmi_forum',
    'dimmi_forum_root',
    'dimmi_root',
    'dimmi_forum_topic',
    'sensor_category',
    'sensor_operator',
    'sensor_area',
    'sensor_root',
    'sensor_post',
    'app_comuneintasca',
    'area_ecologica',
    'azienda',
    'apps_container',
    'item_comuneintasca',
    'ristorante',
    'iniziativa',
    'itinerario',
    'profilo_comuneintasca',
    'nuova_classe',
    'programma_eventi',
    'raccolta_differenziata',
    'riciclabolario',
    'root_comuneintasca',
    'accomodation',
    'testo_comuneintasca',
    'tipologia_rifiuto',
    'tipologia_utenza',
    'tipologia_punto_raccolta',
    'tipologia_raccolta',
    'zona_comuneintasca',
    'pratica_edilizia',
    'ambito',
    'area_interesse',
    'area_interesse_sub',
    'caratteristica_del_servizio',
    'eventodellavita',
    'finalita',
    'io_sono',
    'macroeventodellavita',
    'macroargomento',
    'periodo_storico',
    'stato_sensor',
    'tematica',
    'tipo_competenza_ordinanze',
    'tipo_sensor',
    'tipo_accordo',
    'tipo_alloggio',
    'tipo_associazione',
    'tipo_documento',
    'tipologia_di_scheda_cultura',
    'tipo_servizio_ristoranti',
    'tipo_struttura',
    'tipologia_edificio_storico',
    'toponimo',
];

foreach ($classIdentifiers as $classIdentifier) {
    $contentClass = eZContentClass::fetchByIdentifier($classIdentifier);
    if ($contentClass) {
        if ($contentClass->objectCount() > 0){
            $cli->error("can not remove class " . $contentClass->attribute('identifier'));
        }else {
            $cli->warning("Remove class " . $contentClass->attribute('identifier'));
            eZContentClassOperations::remove($contentClass->attribute('id'));
            ezpEvent::getInstance()->notify('content/class/cache', [$contentClass->attribute('id')]);
            eZCache::clearByID(['classid', 'ocopendata_classes']);
        }
    }
}

$script->shutdown();

