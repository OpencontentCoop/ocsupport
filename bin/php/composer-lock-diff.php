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
    "[from:][to:][table]",
    "[path]",
    array(
        'from' => 'The file, git ref, or git ref with filename to compare from (HEAD:composer.lock)',
        'to' => 'The file, git ref, or git ref with filename to compare to (composer.lock)',
        'table' => 'Output format as table'
    )
);
$script->initialize();
$cli = eZCLI::instance();

$path = isset($options['arguments'][0]) ? $options['arguments'][0] : '../';

try {
    $composerLockDiff = new ComposerLockDiff($path, $options);
    $changes = $composerLockDiff->getChanges();

    if (!$options['table']) {

        foreach ($changes as $repo => $values) {
            if (!empty($values['from']) && !empty($values['to'])) {
                $cli->output("Updating $repo (" . $values['from'] . " => " . $values['to'] . ")");
            } elseif (!empty($values['to']) ) {
                $cli->warning("Installing $repo (" . $values['to'] . ")");
            } elseif (!empty($values['from']) ) {
                $cli->error("Removing $repo (" . $values['from'] . ")");
            }
        }

    } else {

        $table = new ezcConsoleTable(new ezcConsoleOutput(), 300);
        foreach (['Changes', 'From', 'To', 'Compare'] as $cell) {
            $table[0][]->content = $cell;
        }
        $index = 0;
        foreach ($changes as $repo => $values) {
            $index++;
            $table[$index][]->content = $repo;
            $table[$index][]->content = empty($values['from']) ? '' : $values['from'];
            $table[$index][]->content = empty($values['to']) ? '' : $values['to'];
            $table[$index][]->content = empty($values['compare']) ? '' : $values['compare'];
        }
        $table->outputTable();
        $cli->output();
    }

}catch (Exception $e){
    $cli->error($e->getMessage());
}

$script->shutdown();