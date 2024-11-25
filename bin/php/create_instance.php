<?php

require 'autoload.php';

$script = eZScript::instance([
    'description' => ('
    Crea istanza OpenCity\n\n
php ../create_instance.php -sopencitybugliano_backend \
    --identifier="opencityempolese" \
    --name="Unione dei Comuni del Circondario empolese valdesa" \ 
    --prod-url="www.empolese-valdelsa.it" \
    --temp-url="opencityempolese.openpa.opencontent.io" \
    --adm-name="Regione Toscana" \
    --adm-link="https://www.regione.toscana.it"
'),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => false,
]);

$script->startup();

$options = $script->getOptions(
    "[adm-name:][adm-link:][prod-url:][temp-url:][name:][identifier:]",
    "",
    [
        'adm-name' => "nome dell'amministrazione afferente",
        'adm-link' => "link dell'amministrazione afferente",
        'prod-url' => "url di produzione",
        'temp-url' => "url temporaneo",
        'name' => "nome del sito",
        'identifier' => "identificatore del sito",
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(true);
$output = new ezcConsoleOutput();
$cli = eZCLI::instance();

function getOptionOrDie($name)
{
    global $options;
    if (empty($options[$name])) {
        throw new InvalidArgumentException("Option '$name' not found.");
    }

    return $options[$name];
}

try {
    $current = OpenPABase::getCurrentSiteaccessIdentifier();

    $identifier = getOptionOrDie('identifier');
    $nomeAmministrazioneAfferente = getOptionOrDie('adm-name');
    $urlAmministrazioneAfferente = getOptionOrDie('adm-link');
    $urlProd = getOptionOrDie('prod-url');
    $urlTemp = getOptionOrDie('temp-url');
    $name = getOptionOrDie('name');

    if (file_exists("settings/siteaccess/{$identifier}_backend")) {
        throw new Exception("Esiste già un'istanza con identificatore $identifier");
    }

    $siteAccessIdentifierList = [];
    foreach (eZDir::findSubdirs('settings/siteaccess') as $item) {
        if (strpos($item, "{$current}_") !== false) {
            $siteAccessIdentifier = str_replace("{$current}_", '', $item);
            if ($siteAccessIdentifier != 'backend' && $siteAccessIdentifier != 'debug') {
                $siteAccessIdentifierList[] = $siteAccessIdentifier;
            }
        }
    }
    sort($siteAccessIdentifierList);

    $frontendIdentifierList = [
        'frontend',
        'agenda',
    ];
    $siteAccessSelectedList = [
        'frontend',
        'backend',
    ];

    $siteUrl = $urlSuffix = $siteName = $tempSiteUrl = [];
    foreach ($siteAccessSelectedList as $siteAccessSelected) {
        $siteUrl[$siteAccessSelected] = $urlProd;
        $tempSiteUrl[$siteAccessSelected] = $urlTemp;

        if (in_array($siteAccessSelected, $frontendIdentifierList)) {
            $urlSuffix[$siteAccessSelected] = '';
        } elseif ($siteAccessSelected === 'backend') {
            $urlSuffix[$siteAccessSelected] = 'backend';
        }
        $siteName[$siteAccessSelected] = $name;
    }

    foreach ($siteAccessSelectedList as $suffix) {
        $dirPath = "settings/siteaccess/{$current}_{$suffix}";

        $originalDirectory = "settings/siteaccess/{$current}_{$suffix}";
        $directory = "settings/siteaccess/{$identifier}_{$suffix}";
        eZDir::mkdir($directory);
        if (eZDir::copy($originalDirectory, $directory, false) === false) {
            throw new Exception("Fallita creazione cartella $directory");
        }

        $originalOpenpaIni = file_get_contents($directory . '/' . 'openpa.ini.append.php');
        $newOpenpaIni = $originalOpenpaIni;
        $openpaIni = new eZINI('openpa.ini.append.php', $dirPath, false, false, false, true, false);
        $originalNomeAmministrazioneAfferente = $openpaIni->variable(
            'InstanceSettings',
            'NomeAmministrazioneAfferente'
        );
        $originalUrlAmministrazioneAfferente = $openpaIni->variable('InstanceSettings', 'UrlAmministrazioneAfferente');
        $newOpenpaIni = str_replace(
            "NomeAmministrazioneAfferente={$originalNomeAmministrazioneAfferente}",
            "NomeAmministrazioneAfferente=" . $nomeAmministrazioneAfferente,
            $newOpenpaIni
        );
        $newOpenpaIni = str_replace(
            "UrlAmministrazioneAfferente={$originalUrlAmministrazioneAfferente}",
            "UrlAmministrazioneAfferente=" . $urlAmministrazioneAfferente,
            $newOpenpaIni
        );
        if (file_put_contents($directory . '/' . 'openpa.ini.append.php', $newOpenpaIni) === false) {
            throw new Exception("Fallita Modifica al file openpa.ini.append.php");
        }

        foreach (['file.ini.append.php', 'solr.ini.append.php', 'site.ini.append.php'] as $file) {
            $originalFileIni = file_get_contents($directory . '/' . $file);
            $newFileIni = str_replace($current, $identifier, $originalFileIni);
            if ($file == 'site.ini.append.php') {
                $siteIni = new eZINI('site.ini.append.php', $dirPath, false, false, false, true, false);
                $originalSiteName = $siteIni->variable('SiteSettings', 'SiteName');
                $originalSiteUrl = $siteIni->variable('SiteSettings', 'SiteURL');
                $originalSiteUrl = str_replace($current, $identifier, $originalSiteUrl);

                $originalAdditionalLoginFormActionURL = $siteIni->variable(
                    'SiteSettings',
                    'AdditionalLoginFormActionURL'
                );
                $originalAdditionalLoginFormActionURL = str_replace(
                    $current,
                    $identifier,
                    $originalAdditionalLoginFormActionURL
                );

                $newFileIni = str_replace(
                    "SiteName={$originalSiteName}",
                    "SiteName=" . $siteName[$suffix],
                    $newFileIni
                );

                $siteUrlSuffixed = !empty($siteUrl[$suffix]) ? $siteUrl[$suffix] : $tempSiteUrl[$suffix];
                if (!empty($urlSuffix[$suffix])) {
                    $siteUrlSuffixed .= '/' . $urlSuffix[$suffix];
                }
                $newFileIni = str_replace(
                    "SiteURL={$originalSiteUrl}",
                    "SiteURL={$siteUrlSuffixed}",
                    $newFileIni
                );

                $backendUrl = $tempSiteUrl['backend'];
                if (!empty($urlSuffix['backend'])) {
                    $backendUrl .= '/' . $urlSuffix['backend'];
                }
                $additionalLoginFormActionURL = 'https://' . $backendUrl . '/user/login';
                $newFileIni = str_replace(
                    "AdditionalLoginFormActionURL={$originalAdditionalLoginFormActionURL}",
                    "AdditionalLoginFormActionURL={$additionalLoginFormActionURL}",
                    $newFileIni
                );

                foreach ($siteAccessIdentifierList as $siteAccessIdentifier) {
                    if (!in_array($siteAccessIdentifier, $siteAccessSelectedList)) {
                        $newFileIni = str_replace(
                            "RelatedSiteAccessList[]={$identifier}_{$siteAccessIdentifier}\n",
                            "",
                            $newFileIni
                        );
                    }
                }
            }
            if (file_put_contents($directory . '/' . $file, $newFileIni) === false) {
                throw new Exception("Fallia modifica al file $file");
            }
        }
    }

    $originalConfigClusterPath = "settings/cluster-config/config_cluster_{$current}.php";
    $configClusterPath = "settings/cluster-config/config_cluster_{$identifier}.php";
    if (file_exists($originalConfigClusterPath)) {
        $originalConfigCluster = file_get_contents($originalConfigClusterPath);
        $configCluster = str_replace($current, $identifier, $originalConfigCluster);
        if (file_put_contents($configClusterPath, $configCluster) === false) {
            throw new Exception("Fallita Creazione del file $configClusterPath");
        }
    }

    $reorderSiteAccessSelectedList = [];
    foreach ($siteAccessSelectedList as $suffix) {
        if (!empty($urlSuffix[$suffix])) {
            $reorderSiteAccessSelectedList[] = $suffix;
        }
    }
    foreach ($siteAccessSelectedList as $suffix) {
        if (!in_array($suffix, $reorderSiteAccessSelectedList)) {
            $reorderSiteAccessSelectedList[] = $suffix;
        }
    }
    $siteAccessSelectedList = $reorderSiteAccessSelectedList;

    $newFileList = '';
    $newAvailableSiteAccessList = '';
    $newHostUriMatchMapItems = '';
    $newHostUriMatchMapItems_temp = '';
    foreach ($siteAccessSelectedList as $suffix) {
        $newFileList .= "SiteList[]={$identifier}_{$suffix}\n";
        $newAvailableSiteAccessList .= "AvailableSiteAccessList[]={$identifier}_{$suffix}\n";
        if (!empty($siteUrl[$suffix])) {
            $newHostUriMatchMapItems .= "HostUriMatchMapItems[]=" . $siteUrl[$suffix] . ";" . $urlSuffix[$suffix] . ";{$identifier}_{$suffix}\n";
        }
        if (!empty($tempSiteUrl[$suffix])) {
            $newHostUriMatchMapItems_temp .= "HostUriMatchMapItems[]=" . $tempSiteUrl[$suffix] . ";" . $urlSuffix[$suffix] . ";{$identifier}_{$suffix}\n";
        }
    }
    $newFileList .= '#SiteList';
    $newAvailableSiteAccessList .= '#AvailableSiteAccessList';
    $newHostUriMatchMapItems .= "#HostUriMatchMapItems";
    $newHostUriMatchMapItems_temp .= "#TempHostUriMatchMapItems";

    $override = file_get_contents("settings/override/site.ini.append.php");
    $override = str_replace('#SiteList', $newFileList, $override);
    $override = str_replace('#AvailableSiteAccessList', $newAvailableSiteAccessList, $override);
    $override = str_replace('#HostUriMatchMapItems', $newHostUriMatchMapItems, $override);
    $override = str_replace('#TempHostUriMatchMapItems', $newHostUriMatchMapItems_temp, $override);

    if (file_put_contents("settings/override/site.ini.append.php", $override) === false) {
        throw new Exception("Fallita modifica al file override/site.ini");
    }
    $script->shutdown();
} catch (Exception $e) {
    $errCode = 1; // If an error has occured, script must terminate with a status other than 0
    $script->shutdown($errCode, $e->getMessage());
}
