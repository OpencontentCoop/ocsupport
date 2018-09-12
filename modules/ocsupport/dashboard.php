<?php

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$title = 'OpenContent Support';
$error = false;
$packages = array();
$gitRepos = false;

try {
    if ($http->hasGetVariable('force-git-discover')){
        throw new InvalidArgumentException('Opzione force-git-discover attivata');
    }
    $packages = OCSupportTools::getComposerPackages();

} catch (InvalidArgumentException $e) {

    $error = $e->getMessage();
    $gitRepos = OCSupportTools::getGitRepositories();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$tpl->setVariable('error', $error);
$tpl->setVariable('site_title', $title);
$tpl->setVariable('packages', $packages);
$tpl->setVariable('repos', $gitRepos);

$Result = array();
$Result['content'] = $tpl->fetch('design:ocsupport/dashboard.tpl');
$Result['content_info'] = array(
    'node_id' => null,
    'class_identifier' => null,
    'persistent_variable' => array(
        'show_path' => true,
        'site_title' => $title
    )
);
$Result['path'] = array(
    array(
        'url' => false,
        'text' => $title
    )
);
