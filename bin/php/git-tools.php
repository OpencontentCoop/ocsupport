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
    "[tags][branches][logs;]",
    "[path]",
    array(
        'tags' => 'List tags',
        'branches' => 'List branches',
        'logs' => 'List logs ',
    )
);
$script->initialize();
$cli = eZCLI::instance();

$path = isset($options['arguments'][0]) ? $options['arguments'][0] : '../';

try {
    $repo = new GitRepository($path);

    if ($options['tags']){
        $tags = $repo->getTags();
        foreach ($tags as $tag){
            $cli->output($tag);
        }
    }

    if ($options['branches']){
        $branches = $repo->getBranches();
        foreach ($branches as $branch){
            $cli->output($branch);
        }
    }

    if ($options['logs']){
        $logs = $repo->getLogs($options['logs']);
        foreach ($logs as $log){
            $cli->output($log);
        }
    }

}catch (Exception $e){
    $cli->error($e->getMessage());
}

$script->shutdown();