<?php

class OCSupportTools
{
    /**
     * @return array
     * @throws RuntimeException
     */
    public static function getComposerPackages()
    {
        $composerLockFile = eZSys::rootDir() . '/../composer.lock';
        if (file_exists($composerLockFile) && is_readable($composerLockFile)) {

            $composerInfo = new ComposerLockParser\ComposerInfo($composerLockFile);
            $composerInfo->parse();

            return PackagesCollectionTemplatizable::instance(
                $composerInfo->getPackages()
            );
        }

        throw new InvalidArgumentException("File composer.lock non trovato o non leggibile");
    }

    /**
     * @return array
     */
    public static function getGitRepositories()
    {
        $extensionDir = eZExtension::baseDirectory();
        $availableExtensionArray = eZDir::findSubItems($extensionDir, 'dl');
        $gitRepos = array();

        $path = "settings/.git";
        if (file_exists($path)) {
            try {
                $gitRepos[] = self::parseGitRepo($path, 'settings');
            } catch (\Cz\Git\GitException $e) {
                eZDebug::writeError($e->getMessage(), $path);
            }
        }

        foreach ($availableExtensionArray as $availableExtension) {
            $path = "$extensionDir/$availableExtension/.git";
            if (file_exists($path)) {
                try {
                    $gitRepos[] = self::parseGitRepo($path, $availableExtension);
                } catch (\Cz\Git\GitException $e) {
                    eZDebug::writeError($e->getMessage(), $path);
                }
            }
        }

        return $gitRepos;
    }

    /**
     * @param $path
     * @param $name
     *
     * @return array
     * @throws \Cz\Git\GitException
     */
    private static function parseGitRepo($path, $name)
    {
        $repo = new Cz\Git\GitRepository($path);
        $lastCommitHash = $currentTag = $currentBranch = $remote = $workingDir = array('');
        try {
            $lastCommitHash = (array)$repo->execute(array('log', '--pretty=format:\'%h\'', '-n 1'));
        } catch (\Cz\Git\GitException $e) {
            eZDebug::writeError($e->getMessage(), $path);
        }
        try {
            $currentTag = (array)$repo->execute(array('describe', '--exact-match', '--tags'));
        } catch (\Cz\Git\GitException $e) {
            eZDebug::writeError($e->getMessage(), $path);
        }
        try {
            $currentBranch = (array)$repo->execute(array('rev-parse', '--abbrev-ref', 'HEAD'));
        } catch (\Cz\Git\GitException $e) {
            eZDebug::writeError($e->getMessage(), $path);
        }
        try {
            $remote = (array)$repo->execute(array('remote', '-v'));
        } catch (\Cz\Git\GitException $e) {
            eZDebug::writeError($e->getMessage(), $path);
        }
        try {
            $workingDir = (array)$repo->execute(array('status', '--porcelain'));
        } catch (\Cz\Git\GitException $e) {
            eZDebug::writeError($e->getMessage(), $path);
        }

        return array(
            'name' => $name,
            'tag' => $currentTag[0],
            'branch' => $currentBranch[0],
            'hash' => $lastCommitHash[0],
            'remote' => $remote,
            'working_dir' => $workingDir
        );
    }
}
