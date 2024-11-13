<?php

class OCSupportListeners
{
    /**
     * Listen 'content/cache/all' ezpEvent
     * @return void
     */
    public static function logOnClearAllCache()
    {
        $messageParts = [];
        $messageParts[] = 'user:' . eZUser::currentUserID();
        $messageParts[] = 'request:' . eZSys::requestURI();

        eZDebug::instance()->write(
            implode(' ', $messageParts),
            eZDebug::LEVEL_DEBUG,
            'CLEAR_ALL_CACHE',
            '',
            true
        );
    }
}