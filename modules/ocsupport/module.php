<?php

$Module = array( 'name' => 'OpenContent Support' );

$ViewList = array();
$ViewList['dashboard'] = array(
    'functions' => array( 'dashboard' ),
    'script' => 'dashboard.php',
    'params' => array( ),
    'unordered_params' => array(),
    "default_navigation_part" => 'ezsetupnavigationpart',
);


$FunctionList = array();
$FunctionList['dashboard'] = array();
