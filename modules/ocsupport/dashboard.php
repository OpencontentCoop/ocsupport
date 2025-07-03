<?php

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$title = 'OpenContent Support';
$error = false;
$packages = [];
$gitRepos = false;

try {
    if ($http->hasGetVariable('force-git-discover')) {
        throw new InvalidArgumentException('Opzione force-git-discover attivata');
    }
    $packages = OCSupportTools::getComposerPackages();
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage();
    $gitRepos = OCSupportTools::getGitRepositories();
} catch (Exception $e) {
    $error = $e->getMessage();
}

try {
    $installers = InstallerTools::getInstallers();
} catch (Exception $e) {
    $installers = [];
    $error = $e->getMessage();
    eZDebug::writeError($e->getTraceAsString(), $error);
}

$tpl->setVariable('error', $error);
$tpl->setVariable('site_title', $title);
$tpl->setVariable('packages', $packages);
$tpl->setVariable('repos', $gitRepos);
$tpl->setVariable('installers', $installers);
$tpl->setVariable('can_run_installer', !InstallerTools::isInBackgroundInstalling());
$tpl->setVariable('running_installer', InstallerTools::getInBackgroundInstalling());

$Result = [];
$Result['content'] = $tpl->fetch('design:ocsupport/dashboard.tpl');
$Result['content_info'] = [
    'node_id' => null,
    'class_identifier' => null,
    'persistent_variable' => [
        'show_path' => true,
        'site_title' => $title,
    ],
];
$Result['path'] = [
    [
        'url' => false,
        'text' => $title,
    ],
];
