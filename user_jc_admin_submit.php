<?php

include_once 'header.php';
include_once 'database.php';
include_once 'tohtml.php';
include_once 'methods.php';

echo userHTML( );

// If current user does not have the privileges, send her back to  home 
// page.
if( ! isJCAdmin( $_SESSION[ 'user' ] ) )
{
    echo printWarning( "You don't have permission to access this page" );
    echo goToPage( "user.php", 2 );
    exit;
}

// Hand the $_POST here as only.
if( __get__( $_POST, 'response', '' ) == 'Add' )
{
    // Add new members
    $logins = explode( ',', $_POST[ 'logins'] );
    $anyWarning = false;
    foreach( $logins as $login )
    {
        $login = explode( '@', $login )[0];

        if( ! getLoginInfo( $login ) )
        {
            echo printWarning( "$login is not a valid id. Ignoring " );
            $anyWarning = true;
            continue;
        }

        $_POST[ 'status' ] = 'VALID';
        $_POST[ 'login' ] = $login;
        $res = insertOrUpdateTable( 'jc_subscriptions'
            , 'jc_id,login', 'status', $_POST );
        if( ! $res )
            $anyWarning = true;
        else
            echo printInfo( "$login is successfully added to JC" );
    }

    if( ! $anyWarning )
    {
        goToPage( "user_jc_admin.php", 1 );
        exit;
    }
}
else if( $_POST['response'] == 'DO_NOTHING' )
{
    echo printInfo( "User cancelled operation." );
    goToPage( "user_jc_admin.php", 0 );
    exit;
}
else if( $_POST['response'] == 'delete' )
{
    echo printInfo( "Removing user from subscription list" );
    $_POST[ 'status' ] = 'UNSUBSCRIBED';
    $res = updateTable( 'jc_subscriptions', 'login,jc_id', 'status', $_POST );
    if( $res )
    {
        echo printInfo( ' ... success.' );
        goToPage( 'user_jc_admin.php', 1 );
    }
}
else if( $_POST['response'] == 'Assign Presentation' )
{
    echo printInfo( 'Assigning schedule' );
    $_POST[ 'title' ] = 'NA';
    $_POST[ 'status' ] = 'VALID';
    $res = insertOrUpdateTable( 'jc_presentations'
        , 'presenter,jc_id,date,title', 'status'
        , $_POST 
    );
    if( $res )
    {
        echo printInfo( "Successfully assigned to schedule" );
        goToPage( 'user_jc_admin.php', 1 );
        exit;
    }
}
else if( $_POST[ 'response' ] == 'Remove Presentation' )
{
    $data = json_decode( $_POST[ 'json_data' ], true );
    $data[ 'status' ] = 'INVALID';
    $res = updateTable( 'jc_presentations', 'jc_id,presenter,date', 'status', $data );
    if( $res )
    {
        echo printInfo( "Successfully invalidated entry." );
        goToPage( 'user_jc_admin.php', 1 );
        exit( 1 );
    }
}
else
{
    var_dump( $_POST );
    echo alertUser( "Response " . $_POST[ 'response' ] . ' is not known or not 
        supported yet' );
}

echo goBackToPageLink( 'user_jc_admin.php', 'Go Back' );
