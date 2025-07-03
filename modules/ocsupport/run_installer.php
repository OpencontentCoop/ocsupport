<?php

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$view = $Params['View'];

if ($http->hasPostVariable('RunInstaller')) {
    if (!InstallerTools::isInBackgroundInstalling()) {
        $action = $http->postVariable('RunInstaller');
        if ($http->hasPostVariable('Identifier')) {
            $identifier = $http->postVariable('Identifier');
            $installer = InstallerTools::getInstaller($identifier);
            if (isset($installer['can_' . $action]) && $installer['can_' . $action]) {
                InstallerTools::runInBackgroundInstaller($identifier);
                $Module->redirectTo('/ocsupport/run_installer/logs');
                return;
            }
        }
    }
    $Module->redirectTo('/ocsupport/dashboard');
    return;
}

if ($view === 'logs' && $http->hasGetVariable('data')) {
    header('HTTP/1.1 200 OK');
    header('Content-Type: application/json');
    echo json_encode([
        'data' => InstallerTools::getInstallingLogs() ?? '... logs not yet available...',
    ]);
    eZExecution::cleanExit();
} elseif ($view === 'logs' && InstallerTools::isInBackgroundInstalling()) {
    if ($http->hasGetVariable('data')) {
        header('HTTP/1.1 200 OK');
        header('Content-Type: application/json');
        echo json_encode([
            'data' => InstallerTools::isInBackgroundInstalling() ?
                InstallerTools::getInstallingLogs() ?? 'no logs available...' : null,
        ]);
        eZExecution::cleanExit();
    }
    $currentIdentifier = InstallerTools::getInBackgroundInstalling();
    $currentInstaller = InstallerTools::getInstaller($currentIdentifier);
    $tpl->setVariable('current_installer', $currentInstaller);
    $tpl->setVariable('current_identifier', $currentIdentifier);
    $title = 'Installer Log';
    $Result['content'] = $tpl->fetch('design:ocsupport/run_installer_logs.tpl');
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
            'url' => '/ocsupport/dashboard',
            'text' => 'OpenContent Support',
        ],
        [
            'url' => false,
            'text' => $title,
        ],
    ];
} else {
    $Module->redirectTo('/ocsupport/dashboard');
    return;
}


