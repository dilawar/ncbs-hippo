<?php

include_once 'header.php';
include_once 'database.php';

if( isset( $_POST['months'] ) )
    $howManyMonths = intval( $_POST[ 'months' ] );
else
    $howManyMonths = 36;

echo '
    <form method="post" action="">
    Show AWS interaction in last <input type="text" name="months" 
        value="' . $howManyMonths . '" />
    months.
    <button name="response" value="Submit">Submit</button>
    </form>
    ';


$from = date( 'Y-m-d', strtotime( 'today' . " -$howManyMonths months"));

$fromD = date( 'M d, Y', strtotime( $from ) );
echo "<div class=\"info\">
    Following graph shows the interaction among faculty since $fromD.
    Add numbers on each node to count the number of AWSs.
    Edge to another node points to either co-supervisor or tcm member.
    <br>
    External faculty is not shown in this graph.
    <br>
    Self Loop indicates that I could not find any TCM member from NCBS for this AWS in my database.
    </div>";

$awses = getAWSFromPast( $from  );
$dotText = "digraph community {
    rankdir=TB;
    overlap=false;
    sep=\"+30\";
    splines=true;
    maxiters=100;
    ";

echo printInfo( "Total " . count( $awses) . " AWSs found in database since $fromD" );

$community = array();


/** 
 * Here we collect all the unique PIs. This is to make sure that we don't draw 
 * a node for external PI who is/was not on NCBS faculty list. Sometimes, we 
 * can't get all relevant PIs if we only search in AWSs given in specific time. 
 * Therefore we query the faculty table to get the list of all PIs.
 */
$faculty = getFaculty( );
$pis = array( );                                // Just the email
foreach( $faculty as $fac )
{
    $pi = $fac[ 'email' ];
    array_push( $pis, $pi );
    $community[ $pi ] = array( 
        'count' => 0  //  Number of AWS by this supervisor
        , 'edges' => array( )  // Outgoiing edges
        , 'degree' => 0 // Degree of this supervisor.
    );
}

foreach( $awses as $aws )
{
    // How many AWSs this supervisor has.
    $pi = $aws[ 'supervisor_1' ];
    if( ! in_array( $pi, $pis ) )
        continue;

    $community[ $pi ]['count'] += 1;
    $community[ $pi ]['degree'] += 1;

    // Co-supervisor is an edge
    if( strlen( $aws[ "supervisor_2" ] ) > 0 )
    {
        // Only if PI is from NCBS.
        $super2 = $aws[ 'supervisor_2' ];
        if( in_array( $super2, $pis ) )
        {
            array_push( $community[ $pi ]['edges'], $super2 );
            $community[ $super2 ]['degree'] += 1;
        }
    }

    // All TCM members are edges
    $foundAnyTcm = false;
    for( $i = 1; $i < 5; $i++ )
    {
        if( ! array_key_exists( "tcm_member_$i", $aws ) )
            continue;

        $tcmMember = trim( $aws[ "tcm_member_$i" ] );
        if( strpos( $tcmMember, '@') == false ) // Invalid email id.
            continue;

        if( in_array( $tcmMember, $pis ) )
        {
            array_push( $community[ $pi ][ 'edges' ], $tcmMember );
            $community[ $tcmMember ][ "degree" ] += 1;
            $foundAnyTcm = true;
        }
    }

    // If not TCM member is found for this TCM. Add an edge onto PI.
    if(! $foundAnyTcm )
        array_push( $community[ $pi ][ 'edges' ], $pi );
}

// Now generate dot text.
foreach( $community as $pi => $value )
{
    // If there are not edges from or onto this PI, ignore it.
    $login = explode( '@', $pi)[0];


    // This PI is not involved in any AWS for this duration.
    if( $value[ "degree" ] < 1 )
        continue;

    // Width represent AWS per month.
    $count = $value[ 'count' ];
    $width = $count / $howManyMonths;
    $dotText .= "\t$login ["
        //. "xlabel=\"$count\",xlp=\"0,0\","
        . "color=lightblue,style=\"filled\" ,shape=circle,"
        . "width=$width,"
        . "fixedsize=true,"
        . "];\n";

    foreach( array_count_values( $value['edges'] ) as $val => $edgeNum )
    {
        $buddy = explode( '@', $val)[0];
        $penwidth = $edgeNum / 4.0;
        $color = 1.0 / $edgeNum;
        $dotText .= "\t$login -> $buddy [ "
                . "color=red,"
                . "penwidth=$penwidth,"
                . "taillabel=\"$edgeNum\"," 
                . "arrowhead=none,"
                . "];\n";
    }
}

$curdir = getcwd( );
$dotText .= "}";

$dotfileURI = "data/community_$from.dot.txt";
$dotFilePath = "$curdir/$dotfileURI";

// Write graphviz to dot file.
$dotFile = fopen( $dotFilePath, "w" );
fwrite( $dotFile, $dotText );
fclose( $dotFile );

//  Image generation.
$layout = "sfdp";
$imgFormat = "svg";
$imgfileURI = "data/community_$from.$imgFormat";

//Create both SVG and PNG.
exec( "$layout -T$imgFormat -o $curdir/$imgfileURI $dotFilePath", $out, $res );
exec( "$layout -Tpng -o $curdir/data/fallback.png $dotFilePath", $out, $res );

// Now load the image into browser.
echo "<div class=\"image\">";
echo "<object width=\"100%\" data=\"$imgfileURI\" type=\"image/svg+xml\">
    <img src=\"data/fallback.png\" />
    </object>
    ";
echo "</div>";

echo "<a href=\"$dotfileURI\" target=\"_blank\">Download graphviz</a>";
echo goBackToPageLink( "index.php", "Go back" );

?>