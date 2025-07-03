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
$ViewList['run_installer'] = array(
    'functions' => array( 'run_installer' ),
    'script' => 'run_installer.php',
    'params' => array('View'),
    'unordered_params' => array(),
    "default_navigation_part" => 'ezsetupnavigationpart',
);


$FunctionList = array();
$FunctionList['dashboard'] = array();
$FunctionList['run_installer'] = array();
