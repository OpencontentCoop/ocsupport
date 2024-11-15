<?php

class OCSupportListeners
{
    private static $alreadyLogged = false;
    /**
     * Listen 'content/cache/all' ezpEvent
     * @return void
     */
    public static function logOnClearAllCache()
    {
        if (self::$alreadyLogged){
            return;
        }
        $messageParts = [];
        $messageParts[] = eZUser::currentUserID();
        $messageParts[] = eZSys::requestURI() ?? 'cli';
        if (class_exists('OpenPABase')) {
            $messageParts[] = OpenPABase::getCurrentSiteaccessIdentifier();
        }

        eZDebug::instance()->write(
            implode(' ', $messageParts),
            eZDebug::LEVEL_DEBUG,
            'CLEAR_ALL_CACHE',
            '',
            true
        );

        self::$alreadyLogged = true;
    }
}