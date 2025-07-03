<?php

use Opencontent\Installer\Installer;

class InstallerTools
{
    public static function getInstallerPath(): ?string
    {
        $mainInstallerPath = false;
        $mainInstallerVersion = eZSiteData::fetchByName('ocinstaller_version');
        if ($mainInstallerVersion instanceof eZSiteData) {
            $mainInstallerVersion = $mainInstallerVersion->attribute('value');
            $mainInstallerPath = eZSiteData::fetchByName('path_ocinstaller_version@' . $mainInstallerVersion);
            if ($mainInstallerPath instanceof eZSiteData) {
                $mainInstallerPath = self::reparentDirectoryIfNeeded($mainInstallerPath->attribute('value'));
            }
        }
        return $mainInstallerPath;
    }

    private static function reparentDirectoryIfNeeded(string $path): string
    {
        $documentRoot = getcwd();
        if (strpos($path, '/html/') !== false
            && strpos($path, $documentRoot) === false) {
            $parts = explode('/html', $path);
            return $documentRoot . $parts[1];
        }
        return $path;
    }

    public static function getInstallers(): array
    {
        $mainInstallerPath = InstallerTools::getInstallerPath();
        $installers = [];
        $isFork = self::isFork();
        $isAdmin = eZUser::currentUser()->attribute('login') === 'admin';
        if ($mainInstallerPath) {
            $db = eZDB::instance();
            $installer = new Installer($db, $mainInstallerPath, new \Psr\Log\NullLogger());
            $installers = $installer->getCurrentVersions();
            foreach ($installers as $index => &$installer) {
                $canShow = true;
                if (isset($installer['enable_gui'])) {
                    $canShow = $installer['enable_gui'] === true;
                }
                if (!$canShow) {
                    unset($installers[$index]);
                    continue;
                }
                if ($isFork && $installer['identifier'] === '__main__' && $installer['name'] === 'OpenAsl'){
                    eZDebug::writeWarning('OpenAsl IsOpenCityFork workaround applied', __METHOD__);
                    $isFork = false;
                }
                $installer['description'] = $installer['description'] ?? null;
                $installer['is_fork'] = $isFork;
                $installer['can_install'] = $isAdmin  && !$isFork && $installer['current'] === 'not-installed';
                $installer['can_update'] = $isAdmin  && !$isFork && $installer['current'] !== 'not-installed'
                    && version_compare($installer['current'], $installer['available'], '<');
            }
        }

        return $installers;
    }

    private static function isFork(): bool
    {
        $backendAccess = OpenPABase::getBackendSiteaccessName();
        $backendIni = eZSiteAccess::getIni($backendAccess, 'openpa.ini');
        $isFork = $backendIni->hasVariable('CreditsSettings', 'IsOpenCityFork') && $backendIni->variable('CreditsSettings', 'IsOpenCityFork');
        if (!$isFork){
            $frontendAccess = OpenPABase::getFrontendSiteaccessName();
            $frontendIni = eZSiteAccess::getIni($frontendAccess, 'openpa.ini');
            $isFork = $frontendIni->hasVariable('CreditsSettings', 'IsOpenCityFork') && $frontendIni->variable('CreditsSettings', 'IsOpenCityFork');
            eZDebug::writeError('Found IsOpenCityFork setting in frontend ini and not in backend ini', __METHOD__);
        }
        eZDebug::writeDebug('Found IsOpenCityFork in backend ini', __METHOD__);

        return $isFork;
    }

    public static function getInstaller($identifier): ?array
    {
        $installers = InstallerTools::getInstallers();
        foreach ($installers as $installer) {
            if ($installer['identifier'] == $identifier) {
                return $installer;
            }
        }

        return null;
    }

    public static function runInBackgroundInstaller(string $installerIdentifier): void
    {
        InstallerTools::setInBackgroundInstalling($installerIdentifier, true);
        $backendAccess = OpenPABase::getBackendSiteaccessName();
        $command = 'php extension/ocsupport/bin/php/run_installer.php -s' . $backendAccess . ' > /dev/null &';
        eZDebug::writeError($command);
        exec($command);
    }

    public static function setInBackgroundInstalling(string $installerIdentifier, bool $status): void
    {
        $data = eZSiteData::fetchByName('gui_installer_is_running');
        if ($data instanceof eZSiteData) {
            if (!$status) {
                $data->remove();
            }
            return;
        }
        if (!$status) {
            return;
        }

        self::registerInstallingLogs(null);
        $data = new eZSiteData([
            'name' => 'gui_installer_is_running',
            'value' => $installerIdentifier,
        ]);
        $data->store();
    }

    public static function isInBackgroundInstalling(): bool
    {
        $data = eZSiteData::fetchByName('gui_installer_is_running');
        return $data instanceof eZSiteData;
    }

    public static function getInBackgroundInstalling(): ?string
    {
        $data = eZSiteData::fetchByName('gui_installer_is_running');
        return $data instanceof eZSiteData ? $data->attribute('value') : null;
    }

    public static function registerInstallingLogs(?string $logs): void
    {
        $data = eZSiteData::fetchByName('gui_installer_logs');
        if (!$data instanceof eZSiteData) {
            $data = new eZSiteData([
                'name' => 'gui_installer_logs',
                'value' => $logs,
            ]);
        } else {
            $data->setAttribute('value', $logs);
        }
        if (!empty($logs)) {
            $data->store();
        } else {
            $data->remove();
        }
    }

    public static function getInstallingLogs(): ?string
    {
        $data = eZSiteData::fetchByName('gui_installer_logs');
        return $data instanceof eZSiteData ? $data->attribute('value') : null;
    }
}