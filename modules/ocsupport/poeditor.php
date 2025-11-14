<?php

use Opencontent\I18n\Poeditor\InstallerAwareInterface;
use Opencontent\I18n\Poeditor\ProjectHandlerFactory;
use Opencontent\I18n\Poeditor\ProjectHandlerInterface;
use Opencontent\I18n\Poeditor\TagAwareInterface;
use Opencontent\I18n\PoEditorClient;

$module = $Params['Module'];
$currentProjectId = $Params['ProjectID'];

$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$title = 'OpenContent PoEditor Support';
$error = false;
$projects = [];
$tags = [];
$currentProject = ['id' => null];
$currentLocale = null;
$currentTag = null;
$client = null;
$translationsDiff = null;
$termDiff = null;
$installers = null;
$terms = [];
$installerDirectory = InstallerTools::getInstallerPath();

try {

    if ($http->hasPostVariable('store_poeditor_token')) {
        $token = $http->postVariable('store_poeditor_token');
        $client = (new PoEditorClient($token));
        $projects = $client->getProjects();
        if (empty($projects)) {
            throw new Exception('invalid-token');
        } else {
            $http->setSessionVariable('poeditor_token', $token);
        }
    }
    $handler = null;
    if (!$http->hasSessionVariable('poeditor_token')) {
        throw new Exception('missing-token');
    } else {
        $token = $http->sessionVariable('poeditor_token');
        $client = (new PoEditorClient($token));
        $factory = new ProjectHandlerFactory($client);
        $projects = $factory->getProjects();
        if ($currentProjectId) {
            $currentProject = $client->getProject($currentProjectId);
            $handler = $factory->createProjectHandler($currentProjectId);
            if ($handler instanceof TagAwareInterface) {
                $tags = $handler->getAvailableTags();
            }
        }
    }

    if ($http->hasVariable('locale') && $handler instanceof ProjectHandlerInterface) {

        $terms = $handler->getFlattenTerms();
        if ($http->hasGetVariable('debug-terms')) {
            echo '<pre>';print_r($terms);echo '</pre>';eZExecution::cleanExit();
        }

        $currentLocale = $http->variable('locale');
        $handler->setCurrentLanguage($http->getVariable('locale'));

        if ($handler instanceof TagAwareInterface) {
            if ($http->hasVariable('tag')) {
                $currentTag = $http->variable('tag');
                $handler->setCurrentTags([$http->variable('tag')]);
            } else {
                throw new Exception('missing-tag');
            }
        }

        if ($http->hasPostVariable('send_poeditor_term')) {
            $queryString = "locale=" . urlencode($currentLocale);
            $pushTags = [];
            if (isset($currentTag)){
                $queryString .= "&tag=" . urlencode($currentTag);
                $pushTags[] = $currentTag;
            }
            $pushContext = $http->postVariable('context');
            $pushTerm = $http->postVariable('term');
            if (!empty($pushContext) && !empty($pushTerm)) {
                $handler->pushTerm($pushContext, $pushTerm, $pushTags);
            }
            $module->redirectTo('ocsupport/poeditor/' . $handler->getProjectId() . '?' . $queryString);
            return;
        }

        if ($handler instanceof InstallerAwareInterface) {
            if (empty($installerDirectory)) {
                throw new Exception('missing-installer-directory');
            }
            $handler->setInstaller($installerDirectory);
        }

        $translationsDiff = $handler->getTranslationsDiff();
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$tpl->setVariable('title', $title);
$tpl->setVariable('languages', PoEditorClient::$languageMap);
$tpl->setVariable('tags', $tags);
$tpl->setVariable('error', $error);
$tpl->setVariable('ezxform_token', ezxFormToken::getToken());
$tpl->setVariable('projects', $projects);
$tpl->setVariable('current_project', $currentProject);
$tpl->setVariable('current_locale', $currentLocale);
$tpl->setVariable('current_tag', $currentTag);
$tpl->setVariable('translation_diff', $translationsDiff);
$tpl->setVariable('term_diff_items', $termDiff);
$tpl->setVariable('installers', $installers);
$tpl->setVariable('default_installer', $installerDirectory);
$tpl->setVariable('current_project_terms', $terms);

$Result = [];
$Result['content'] = $tpl->fetch('design:ocsupport/poeditor.tpl');
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
$contentInfoArray = [
    'node_id' => null,
    'class_identifier' => null,
];
$contentInfoArray['persistent_variable'] = [
    'show_path' => false,
];
if (is_array($tpl->variable('persistent_variable'))) {
    $contentInfoArray['persistent_variable'] = array_merge(
        $contentInfoArray['persistent_variable'],
        $tpl->variable('persistent_variable')
    );
}
$Result['content_info'] = $contentInfoArray;
$Result['pagelayout'] = false;