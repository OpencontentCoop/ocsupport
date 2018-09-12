<?php

class PackagesCollectionTemplatizable
{
    public static function instance(ComposerLockParser\PackagesCollection $collection)
    {
        $data = array();
        foreach ($collection as $package) {
            $data[] = new PackageTemplatizable($package);
        }

        return $data;
    }
}
