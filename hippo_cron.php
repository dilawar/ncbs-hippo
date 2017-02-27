<?php

include_once 'methods.php';
include_once 'database.php';
include_once 'tohtml.php';
include_once 'mail.php';

ini_set( 'date.timezone', 'Asia/Kolkata' );
ini_set( 'log_errors', 1 );
ini_set( 'error_log', '/var/log/hippo.log' );


// Directory to store the mdsum of sent emails.
$maildir = getDataDir( ) . '/_mails';
if( ! file_exists( $maildir ) )
    mkdir( $maildir, 0777, true );

$now = dbDateTime( strtotime( "now" ) );

error_log( "Running cron job at $now" );
echo( "Running cron at $now" );

function generateAWSEmail( $monday )
{

    $res = array( );

    $upcomingAws = getUpcomingAWS( $monday );
    if( ! $upcomingAws )
        $upcomingAws = getTableEntries( 'annual_work_seminars', "date" , "date='$monday'" );

    if( count( $upcomingAws ) < 1 )
        return null;

    $html = '';

    $speakers = array( );
    $logins = array( );
    $outfile = getDataDir( ) . "AWS_" . $monday . "_";

    foreach( $upcomingAws as $aws )
    {
        $html .= awsToHTML( $aws );
        array_push( $logins, $aws[ 'speaker' ] );
        array_push( $speakers, __ucwords__( loginToText( $aws['speaker'], false ) ) );
    }

    $outfile .= implode( "_", $logins );  // Finished generating the pdf file.
    $pdffile = $outfile . ".pdf";
    $res[ 'speakers' ] = $speakers;

    $data = array( 'EMAIL_BODY' => $html
        , 'DATE' => humanReadableDate( $monday ) 
        , 'TIME' => '4:00 PM'
    );

    $mail = emailFromTemplate( 'aws_template', $data );

    echo "Generating pdf";
    $script = __DIR__ . '/generate_pdf_aws.php';
    $cmd = "php -q -f $script date=$monday";
    echo "Executing <pre> $cmd </pre>";
    ob_flush( );

    $ret = `$cmd`;

    if( ! file_exists( $pdffile ) )
    {
        echo printWarning( "Could not generate PDF $pdffile." );
        echo $res;
        $pdffile = '';
    }

    $res[ 'pdffile' ] = $pdffile;
    $res[ 'email' ] = $mail;
    return $res;
}


/*
 * Task 1. If today is Friday. Then prepare a list of upcoming AWS and send out 
 * and email at 4pm.
 */
$today = dbDate( strtotime( 'today' ) );
echo printInfo( "Today is $today" );

if( $today == dbDate( strtotime( 'this friday' ) ) )
{
    // Send any time between 4pm and 4:15 pm.
    $awayFrom = strtotime( 'now' ) - strtotime( '4:00 pm' );
    if( $awayFrom >= -1 && $awayFrom < 15 * 60 )
    {
        echo printInfo( "Today is Friday 4pm. Send out emails for AWS" );
        $nextMonday = dbDate( strtotime( 'next monday' ) );
        $subject = 'Next Week AWS (' . humanReadableDate( $nextMonday) . ') by ';

        $res = generateAWSEmail( $nextMonday );
        if( $res )
        {
            $subject = 'Next Week AWS (' . humanReadableDate( $nextMonday) . ') by ';
            $subject .= implode( ', ', $res[ 'speakers'] );

            $cclist = 'ins@ncbs.res.in,reception@ncbs.res.in';
            $cclist .= ',multimedia@ncbs.res.in,hospitality@ncbs.res.in';
            $to = 'academic@lists.ncbs.res.in';

            $mail = $res[ 'email' ];

            // generate md5 of email. And store it in archive.
            $archivefile = $maildir . '/' . md5($subject . $mail) . '.email';
            if( file_exists( $archivefile ) )
            {
                echo printInfo( "This email has already been sent. Doing nothing" );
            }
            else 
            {
                $pdffile = $res[ 'pdffile' ];
                $res = sendPlainTextEmail( $mail, $subject, $to, $cclist, $pdffile );
                echo printInfo( "Saving the mail in archive" . $archivefile );
                file_put_contents( $archivefile, "SENT" );
            }
            ob_flush( );
        }
    }
}
else if( $today == dbDate( strtotime( 'this monday' ) ) )
{
    error_log( "Monday 10am. Notify about AWS" );
    // Send on 10am.
    $awayFrom = strtotime( 'now' ) - strtotime( '10:00 am' );
    if( $awayFrom >= -1 && $awayFrom < 15 * 60 )
    {
        echo printInfo( "Today is Monday 10am. Send out emails for AWS" );
        $thisMonday = dbDate( strtotime( 'this monday' ) );
        $subject = 'Today\'s AWS (' . humanReadableDate( $thisMonday) . ') by ';
        $res = generateAWSEmail( $thisMonday );

        if( $res )
        {
            echo printInfo( "Sending mail about today's AWS" );
            $subject .= implode( ', ', $res[ 'speakers'] );

            $cclist = 'ins@ncbs.res.in,reception@ncbs.res.in';
            $cclist .= ',multimedia@ncbs.res.in,hospitality@ncbs.res.in';
            $to = 'academic@lists.ncbs.res.in';

            $mail = $res[ 'email' ];

            // generate md5 of email. And store it in archive.
            $archivefile = $maildir . '/' . md5($subject . $mail) . '.email';
            error_log( "Sending to $to, $cclist with subject $subject" );
            echo( "Sending to $to, $cclist with subject $subject" );

            if( file_exists( $archivefile ) )
            {
                echo printInfo( "This email has already been sent. Doing nothing" );
            }
            else 
            {
                $pdffile = $res[ 'pdffile' ];
                $ret = sendPlainTextEmail( $mail, $subject, $to, $cclist, $pdffile );
                echo printInfo( "Return value $ret" );
                echo printInfo( "Saving the mail in archive" . $archivefile );
                file_put_contents( $archivefile, "SENT" );
            }
            ob_flush( );
        }
    }
}

/*
 * Task 2. Every day at 8am, check today's event and send out an email.
 */
$awayFrom = strtotime( 'now' ) - strtotime( '8:00 am' );
$today = dbDate( strtotime( 'today' ) );
echo "Looking for events on $today";
//if( $awayFrom >= -1 && $awayFrom < 15 * 60 )
{
    $todaysEvents = getPublicEventsOnThisDay( $today );

    $html = '';
    if( count( $todaysEvents ) > 0 )
    {
        foreach( $todaysEvents as $event )
        {
            $external_id = $event[ 'external_id' ];
            $talkid = explode( '.', $external_id );

            if( count( $talkid ) == 2 )
            {
                $data = array( 'id' => $talkid[1] );
                $talk = getTableEntry( 'talks', 'id', $data );
                if( $talk )
                    $html .= talkToHTML( $talk );
            }
        }
    }

    // Generate pdf now.
    $pdffile = getDataDir( ) . "/EVENTS_$today.pdf";
    $script = __DIR__ . '/generate_pdf_talk.php';
    $cmd = "php -q -f $script date=$today";
    echo "Executing <pre> $cmd </pre>";
    $res = `$cmd`;

    $attachment = '';
    if( file_exists( $pdffile ) )
    {
        echo printInfo( "Successfully generated PDF file" );
        $attachment = $pdffile;
    }


    // Now prepare an email to sent to mailing list.
    $macros = array( 'EMAIL_BODY' => $html, 'DATE' => $today );
    $subject = "Today's (" . humanReadableDate( $today ) . ") talks/seminars on the campus";
    $email = emailFromTemplate( 'todays_events', $macros );
    if( $email )
    {
        // Send it out.
        echo "<pre> $email </pre>";
        echo $subject;
    }

    ob_flush( );
}

?>
