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
    "[remote:][from:][to:]",
    "[path][repo]",
    array(
        'remote' => 'Remote repo uri',
        'path' => 'CHANGELOG.md path (default ../CHANGELOG.md)',
        'repo' => 'repo path (default ../)',
        'from' => 'From tag',
        'To' => 'To tag'
    )
);
$script->initialize();
$cli = eZCLI::instance();
$isVerbose = isset($options['verbose'][0]);

function addText($data, $position, $text)
{
    return substr($data, 0, $position) . $text . substr($data, $position);
}

function getLogs($tag, $previousTag)
{
    global $gitRepo;
    $logs = [];
    $rawLogs = $gitRepo->getLogs("$previousTag..$tag");
    foreach ($rawLogs as $log) {
        if (
            strpos($log, 'Merge branch') === false
            && strpos($log, 'Updating ') === false
            && strpos($log, 'no message') === false
        ) {
            $oneLine = str_replace(["\r\n", "\r", "\n", '# ', '#', '*'], ' ', $log);
            $logs[] = $oneLine;
        }
    }

    return array_unique($logs);
}

function generateTagText($tag, $previousTag, $newTagName = false)
{
    global $gitRepo, $composerDiffer, $remoteUri, $cli, $isVerbose;

    $date = $gitRepo->getTagDate($tag);
    if (!$previousTag) {
        $previousTag = 'first';
    }
    $logs = getLogs($tag, $previousTag);
    $logText = [];
    $installerText = [];
    foreach ($logs as $log) {
        if (strpos($log, '[installer]') !== false) {
            $installerText[] = '- ' . str_replace('[installer] ', '', $log);
        } else {
            $logText[] = '- ' . $log;
        }
    }
    $logText = implode("\n", $logText);
    if (!empty($installerText)) {
        array_unshift($installerText, "\n", '#### Installer');
        $installerText[] = '';
    }
    $installerText = implode("\n", $installerText);

    $changeTable = '';
    if ($previousTag !== 'first') {
        $composerDiffer->setOptions(['from' => $previousTag, 'to' => $tag]);
        if ($isVerbose) $cli->output(' - get composer changes');
        $changes = $composerDiffer->getChanges();
        $changeTable = ComposerLockDiff::makeChangesAsMDTable($changes);
        if (!empty($changeTable)) {
            $changeTable = "#### Code dependencies\n" . $changeTable;
        }

        $relevantChanges = [];
        foreach ($changes as $repo => $change) {
            if (strpos($repo, 'opencontent') !== false) {
                if (!empty($change['compare'])) {
                    $from = $change['from'];
                    $to = $change['to'];
                    $compareUrl = $change['compare'];
                    if ($isVerbose) $cli->output(' - get changes from ' . $compareUrl);
                    $reader = new PackageChangeReader($compareUrl);
                    $repoChanges = $reader->getChanges(['Merge pull request']);
                    if (!empty($repoChanges)) {
                        $relevantChanges[] = "**[{$repo} changes between {$from} and {$to}]({$compareUrl})**\n";
                        foreach ($repoChanges as $repoChange) {
                            $oneLine = str_replace(["\r\n", "\r", "\n", '# ', '#', '*'], ' ', $repoChange);
                            $relevantChanges[] = "* {$oneLine}\n";
                        }
                        $relevantChanges[] = "\n";
                    }
                }
            }
        }
    }

    $relevantChangesText = '';
    if (!empty($relevantChanges)) {
        array_unshift($relevantChanges, "Relevant changes by repository:\n\n");
        $relevantChangesText = implode('', $relevantChanges);
    }

    if ($newTagName) {
        $tag = $newTagName;
    }

    $text = <<<INTRO
## [{$tag}]({$remoteUri}/compare/{$previousTag}...{$tag}) - {$date}
{$logText}
{$installerText}
{$changeTable}
{$relevantChangesText}

INTRO;

    return $text;
}

$remoteUri = rtrim($options['remote'], '/');
$file = isset($options['arguments'][0]) ? $options['arguments'][0] : '../CHANGELOG.md';
$repo = isset($options['arguments'][1]) ? $options['arguments'][1] : '../';
$gitRepo = new GitRepository($repo);

$composerDiffer = new ComposerLockDiff($repo);

if ($options['from'] && $options['to']){
    echo generateTagText($options['to'], $options['from']);
    $script->shutdown();
    exit(0);
}

$changeLogContents = file_get_contents($file);

$intro = <<<INTRO
# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


INTRO;
$hasIntro = strpos($changeLogContents, $intro) !== false;
if (!$hasIntro) {
    $cli->warning('Add intro');
    $changeLogContents = addText($changeLogContents, 0, $intro);
    file_put_contents($file, $changeLogContents);
}


$tagList = $gitRepo->getTagsSortedByDate(['1.0.0']);

$previousTag = false;
foreach ($tagList as $index => $tag) {
    if ($index > 0) {
        $previousTag = $tagList[$index - 1];
    }
    $tagPosition = strpos($changeLogContents, "## [$tag]");
    if ($tagPosition === false) {
        $previousTagPosition = strpos($changeLogContents, "## [$previousTag]");
        $cli->warning('Add changelog for tag ' . $tag);

        $tagText = generateTagText($tag, $previousTag);
        $changeLogContents = addText($changeLogContents, $previousTagPosition, $tagText);
        file_put_contents($file, $changeLogContents);

    } elseif ($isVerbose) {
        $cli->output('Found changelog for tag ' . $tag);
    }
}

$currentBranch = $gitRepo->getCurrentBranchName();
$lastTag = array_pop($tagList);

$output = new ezcConsoleOutput();
$question = new ezcConsoleQuestionDialog($output);
$question->options->text = "Add new tag? (current branch is $currentBranch and last tag is $lastTag)";
$question->options->showResults = true;

$newTagName = ezcConsoleDialogViewer::displayDialog($question);
if ($newTagName) {
    $lastTagPosition = strpos($changeLogContents, "## [$lastTag]");
    $tagText = generateTagText($currentBranch, $lastTag, $newTagName);

    $updateChangeLog = ezcConsoleDialogViewer::displayDialog(ezcConsoleQuestionDialog::YesNoQuestion(
            $output,
            "Update CHANGELOG.md?",
            "y"
        )) == "y";
    if ($updateChangeLog) {
        $changeLogContents = addText($changeLogContents, $lastTagPosition, $tagText);
        file_put_contents($file, $changeLogContents);
    }

    $updatePublicCode = false;
    $publicCodeFile = dirname($file) . '/publiccode.yml';
    if (file_exists($publicCodeFile)) {
        $updatePublicCode = ezcConsoleDialogViewer::displayDialog(ezcConsoleQuestionDialog::YesNoQuestion(
                $output,
                "Update publiccode.yml?",
                "y"
            )) == "y";
        if ($updatePublicCode) {
            $publicCodeData = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($publicCodeFile));
            $publicCodeData['softwareVersion'] = $newTagName;
            $publicCodeData['releaseDate'] = date('Y-m-d', time());
            file_put_contents($publicCodeFile, Symfony\Component\Yaml\Yaml::dump($publicCodeData, 10));
        }
    }

    if ($updateChangeLog || $updatePublicCode) {
        $commitAndTag = ezcConsoleDialogViewer::displayDialog(ezcConsoleQuestionDialog::YesNoQuestion(
                $output,
                "Add changes and tag repo?",
                "y"
            )) == "y";
        if ($commitAndTag) {
            $message = $updatePublicCode ? 'Update changelog and publiccode' : 'Update changelog';
            $gitRepo->addAllChanges();
            $gitRepo->commit($message);
            $gitRepo->createTag($newTagName);
        }
    }
}

$script->shutdown();
