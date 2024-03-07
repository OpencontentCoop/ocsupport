<?php
set_time_limit ( 0 );
require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array(  'description' => ( "Remove all contents by owner" ),
                                      'use-session' => false,
                                      'use-modules' => true,
                                      'use-extensions' => true ) );

$script->startup();
$options = $script->getOptions(
    '[owner_id:]',
    '',
    array(
        'owner_id'  => 'Owner user id'
    )
);
$script->initialize();
$script->setUseDebugAccumulators( true );

$user = eZUser::fetchByName( 'admin' );
eZUser::setCurrentlyLoggedInUser( $user , $user->attribute( 'contentobject_id' ) );

try
{
	$ownerId = $options['owner_id'];
    $user = eZUser::fetch((int)$ownerId);
    if (!$user instanceof eZUser){
        throw new Exception("User not found", 1);
        
    }
    
    $list = eZContentObjectTreeNode::subTreeByNodeID(array(
        'AttributeFilter' => array(array('owner', '=', $ownerId))
    ), 1);

    $cli->output("Remove " . count($list) . " nodes");
    foreach ($list as $item) {        
        $item->removeNodeFromTree();
        $cli->output('.', false);
    }
    $cli->output();


    $script->shutdown();
}
catch( Exception $e )
{
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1; // If an error has occured, script must terminate with a status other than 0
    $script->shutdown( $errCode, $e->getMessage() );
}

