<?php

require 'autoload.php';

$script = eZScript::instance([
    'description' => "",
    'use-session' => false,
    'use-modules' => true,
    'debug-timing' => true
]);

$script->startup();
$options = $script->getOptions(
    "[url:]",
    "",
    array(
        'url' => 'Compare url',
    )
);
$script->initialize();
$cli = eZCLI::instance();

$compare = new PackageChangeReader($options['url']);
$logs = $compare->getChanges();
foreach ($logs as $log) {
    $cli->output($log);
}

$script->shutdown();