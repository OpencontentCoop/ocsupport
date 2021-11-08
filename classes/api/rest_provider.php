<?php

class OCSupportProvider implements ezpRestProviderInterface
{
    public function getRoutes()
    {
        $version = 1;
        return [
            'supportPackages' => new ezpRestVersionedRoute(new OpenApiRailsRoute(
                '/packages',
                'OCSupportController',
                'packages',
                [],
                'http-get'
            ), $version),
            'supportInstaller' => new ezpRestVersionedRoute(new OpenApiRailsRoute(
                '/version',
                'OCSupportController',
                'version',
                [],
                'http-get'
            ), $version),
        ];
    }

    public function getViewController()
    {
        return new OCSupportViewController();
    }

}
