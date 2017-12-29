<!-- <script src="http://code.highcharts.com/highcharts.js"></script> -->
<script src="./node_modules/highcharts/highcharts.js"></script>
<script src="./node_modules/highcharts/modules/exporting.js"></script>

<?php

include_once 'header.php';
include_once 'database.php';
include_once 'methods.php';


$upto = dbDate( 'tomorrow' );
$requests = getTableEntries( 'bookmyvenue_requests', 'date'
                , "date >= '2017-02-28' AND date <= '$upto'" );
$nApproved = 0;
$nRejected = 0;
$nCancelled = 0;
$nPending = 0;
$nOther = 0;
$timeForAction = array( );

$firstDate = $requests[0]['date'];
$lastDate = end( $requests )['date'];
$timeInterval = strtotime( $lastDate ) - strtotime( $firstDate );

foreach( $requests as $r )
{
    if( $r[ 'status' ] == 'PENDING' )
        $nPending += 1;

    else if( $r[ 'status' ] == 'APPROVED' )
        $nApproved += 1;

    else if( $r[ 'status' ] == 'REJECTED' )
        $nRejected += 1;

    else if( $r[ 'status' ] == 'CANCELLED' )
        $nCancelled += 1;
    else
        $nOther += 1;

    // Time take to approve a request, in hours
    if( $r[ 'last_modified_on' ] )
    {
        $time = strtotime( $r['date'] . ' ' . $r[ 'start_time' ] )
                    - strtotime( $r['last_modified_on'] );
        $time = $time / (24 * 3600.0);
        array_push( $timeForAction, array($time, 1) );
    }
}

// rate per day.
$rateOfRequests = 24 * 3600.0 * count( $requests ) / (1.0 * $timeInterval);

/*
 * Venue usage timne.
 */
$events = getTableEntries( 'events', 'date'
                , "status='VALID' AND date < '$upto'" );

$venueUsageTime = array( );
// How many events, as per class.
$eventsByClass = array( );

foreach( $events as $e )
{
    $time = (strtotime( $e[ 'end_time' ] ) - strtotime( $e[ 'start_time' ] ) ) / 3600.0;
    $venue = $e[ 'venue' ];

    $venueUsageTime[ $venue ] = __get__( $venueUsageTime, $venue, 0.0 ) + $time;
    $eventsByClass[ $e[ 'class' ] ] = __get__( $eventsByClass, $e['class'], 0 ) + 1;
}
$allVenues = array_keys( $venueUsageTime );

//// NOTE: This statistics takes too much time.
//// Venue usage at particular time from 8am to 8pm. AND concurrent events as
//// well.
//// For each 15 min gap, for 12 hours.
//$venueUsageAtTime = array( );
//$begin = strtotime( '8:00 am' );
//$timevec = array( );
//for ($i = 0; $i < 48; $i++)
//    $timevec[ ] = dbTime( $begin + 15 * 60 * $i );
//
//foreach( $allVenues as $venue )
//{
//    $data = array( );
//    foreach( $timevec as $t )
//    {
//        $res = array_filter( $events
//            , function( $v ) {
//                global $venue;
//                global $t;
//                return ($v['venue'] == $venue)
//                    && (strtotime($v['start_time']) <= strtotime($t))
//                    && (strtotime( $v['end_time']) > strtotime($t));
//            });
//        $data[] = count( $res );
//    }
//
//    // Make few of series visible by default ~ 10% (randomly).
//    $visible = false;
//    if( rand(1, 10 ) < 2 )
//        $visible = true;
//    $venueUsageAtTime[ ] = array( 'name' => $venue , 'visible' => $visible
//        , 'data' => $data);
//}
//
// AWS to this list.
$eventsByClass[ 'ANNUAL WORK SEMINAR' ] = count(
    getTableEntries( 'annual_work_seminars', 'date', "date>'2017-03-21'" ) );

// Add courses events generated by Hippo.
$eventsByClass[ 'CLASS' ] = __get__( $eventsByClass, 'CLASS', 0 )
    + totalClassEvents( );

$eventsByClassPie = array( );
foreach( $eventsByClass as $cl => $v )
    $eventsByClassPie[ ] = array( 'name' => $cl, 'y' => $v );

$venues = array_keys( $venueUsageTime );
$venueUsage = array_values( $venueUsageTime );
$venueUsagePie = array( );
foreach( $venueUsageTime as $v => $t )
    $venueUsagePie[ ] = array( "name" => $v, "y" => $t );

$bookingTable = "<table border='1'>
    <tr> <td>Total booking requests</td> <td>" . count( $requests ) . "</td> </tr>
    <tr> <td>Rate of booking (# per day)</td> <td>"
            .   number_format( $rateOfRequests, 2 ) . "</td> </tr>
    <tr> <td>Approved requests</td> <td> $nApproved </td> </tr>
    <tr> <td>Rejected requests</td> <td> $nRejected </td> </tr>
    <tr> <td>Pending requests</td> <td> $nPending </td> </tr>
    <tr> <td>Cancelled by user</td> <td> $nCancelled </td> </tr>
    </table>";

$thesisSeminars = getTableEntries( 'talks', 'class', "class='THESIS SEMINAR'" );
$thesisSemPerYear = array( );
$thesisSemPerMonth = array( );

for( $i = 1; $i <= 12; $i ++ )
    $thesisSemPerMonth[ date( 'F', strtotime( "2000/$i/01" ) )] = 0;

foreach( $thesisSeminars as $ts )
{
    // Get event of this seminar.
    $event = getEventsOfTalkId( $ts[ 'id' ] );

    $year = intval( date( 'Y', strtotime( $event['date'] )  ));
    $month = date( 'F', strtotime( $event['date'] ) );


    if( $year > 2000 )
        $thesisSemPerYear[ $year ] = __get__( $thesisSemPerYear, $year, 0 ) + 1;

    $thesisSemPerMonth[ $month ] += 1;

}
?>

<script type="text/javascript" charset="utf-8">
$(function( ) {

    var venueUsage = <?php echo json_encode( $venueUsage ); ?>;
    var venueUsagePie = <?php echo json_encode( $venueUsagePie ); ?>;
    var venues = <?php echo json_encode( $venues ); ?>;

    Highcharts.chart('venue_usage1', {
        chart : { type : 'column' },
        title: { text: 'Venue usage in hours' },
        yAxis: { title: { text: 'Time in hours' } },
        xAxis : { categories : venues },
        legend: { layout: 'vertical', align: 'right', verticalAlign: 'middle' },
        series: [{ name: 'Venue usage'
                    , data: venueUsage
                    , pointPlacement: 'middle'
                    , showInLegend:false
                 }],
        });

    Highcharts.chart('venue_usage2', {
        chart : { type : 'pie' },
        title: { text: 'Venue usage' },
        tooltip: { pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>' },
        legend: { layout: 'vertical', align: 'right', verticalAlign: 'middle' },
        series: [{ name: 'Venue usage'
                    , data: venueUsagePie
                    , showInLegend:false
                }],
    });

});
</script>

<script type="text/javascript" charset="utf-8">
$(function( ) {

    var eventsByClass = <?php echo json_encode( array_values( $eventsByClass) ); ?>;
    var eventsByClassPie = <?php echo json_encode( $eventsByClassPie ); ?>;
    var cls = <?php echo json_encode( array_keys( $eventsByClass) ); ?>;

    Highcharts.chart('events_class1', {

        chart : { type : 'column' },
        title: { text: 'Event distribution by categories' },
        yAxis: { title: { text: 'Number of events' } },
        xAxis : { categories : cls },
        legend: { layout: 'vertical', align: 'right', verticalAlign: 'middle' },
        series: [{ name: 'Total events grouped by class'
                    , data: eventsByClass, showInLegend:false
                 }],
    });

    Highcharts.chart('events_class2', {
        chart : { type : 'pie' },
        title: { text: 'Event distribution' },
        tooltip: { pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>' },
        series: [{ name: 'Total events grouped by class'
                    , data: eventsByClassPie, showInLegend:false
                }],
    });

});
</script>

<script type="text/javascript" charset="utf-8">
$(function( ) {
    var data = <?php echo json_encode( $venueUsageAtTime ); ?>;
    var xlabels = <?php echo json_encode( $timevec ); ?>;
    Highcharts.chart('venues_busy_time', {
        title: { text: 'Number of events V/s Time' },
        xAxis : { categories : xlabels },
        series: data,
        });
});
</script>


<script type="text/javascript" charset="utf-8">
$(function( ) {

    var thesisSemPerMonth = <?php echo json_encode( array_values( $thesisSemPerMonth) ); ?>;
    var cls = <?php echo json_encode( array_keys( $thesisSemPerMonth) ); ?>;

    Highcharts.chart('thesis_seminar_per_month', {

        chart : { type : 'column' },
        title: { text: 'Thesis Seminar distributuion (monthly)' },
        yAxis: { title: { text: 'Number of events' } },
        xAxis : { categories : cls },
        legend: { layout: 'vertical', align: 'right', verticalAlign: 'middle' },
        series: [{ name: 'Total Thesis Seminars', data: thesisSemPerMonth
            ,  showInLegend:false
            , pointPlacement: 'middle',
        }],
    });

});
</script>

<script type="text/javascript" charset="utf-8">
$(function( ) {

    var thesisSemPerYear = <?php echo json_encode( array_values( $thesisSemPerYear) ); ?>;
    var cls = <?php echo json_encode( array_keys( $thesisSemPerYear) ); ?>;

    Highcharts.chart('thesis_seminar_per_year', {

        chart : { type : 'column' },
        title: { text: 'Thesis Seminar distributuion (yearly)' },
        yAxis: { title: { text: 'Number of events' } },
        xAxis : { categories : cls },
        legend: { layout: 'vertical', align: 'right', verticalAlign: 'middle' },
        series: [{ name: 'Total Thesis Seminars', data: thesisSemPerYear, showInLegend : false}],
    });

});

</script>

<?php

$awses = getAllAWS( );
$speakers = getAWSSpeakers( );

// Construct a pie-data to be fed into Hightcharts.
$awsSpeakers = array( );
foreach( $speakers as $speaker )
{
    $pi = getPIOrHost( $speaker[ 'login' ] );
    $spec = getSpecialization( $speaker[ 'login' ], $pi );
    $awsSpeakers[ $spec ] = __get__( $awsSpeakers, $spec, 0 ) + 1;
}
$awsSpeakersPie = array( );
foreach( $awsSpeakers as $spec => $v )
    $awsSpeakersPie[] = array( 'name' => $spec, 'y' => $v );

$awsPerSpeaker = array( );

$awsYearData = array_map(
    function( $x ) { return array(date('Y', strtotime($x['date'])), 0); }
    , $awses
    );

// Here each valid AWS speaker initialize her count to 0.
foreach( $speakers as $speaker )
    $awsPerSpeaker[ $speaker['login'] ] = array();

// If there is already an AWS for a speaker, add to her count.
foreach( $awses as $aws )
{
    $speaker = $aws[ 'speaker' ];
    if( ! array_key_exists( $speaker, $awsPerSpeaker ) )
        $awsPerSpeaker[ $speaker ] = array();

    array_push( $awsPerSpeaker[ $speaker ], $aws );
}

$awsCounts = array( );
$awsCountsBySpec = array( );
$awsDates = array( );
foreach( $awsPerSpeaker as $speaker => $awses )
{
    $pi = getPIOrHost( $speaker );
    $awsCounts[ $speaker ] = count( $awses );
    $awsDates[ $speaker ] = array_map(
        function($x) { return $x['date']; }, $awses
    );

    // Get the AWS specialization by queries the specialization of PI. If not
    // found, use the current specialization of speaker.
    foreach( $awses as $aws )
    {
        $spec = getFacultySpecialization( $aws[ 'supervisor_1' ] );
        if( ! trim( $spec ) )
            $spec = getSpecialization( $speaker, $pi );
        if( $spec != 'UNSPECIFIED' )
            $awsCountsBySpec[ $spec ] = __get__( $awsCountsBySpec, $spec, 0) + 1;
    }
}
$awsCountsBySpecPie = array( );
foreach( $awsCountsBySpec as $spec => $v )
    $awsCountsBySpecPie[] = array( 'name' => $spec, 'y' => $v );

$numAWSPerSpeaker = array( );
$gapBetweenAWS = array( );
foreach( $awsCounts as $key => $val )
{
    array_push( $numAWSPerSpeaker,  array($val, 0) );
    for( $i = 1; $i < count( $awsDates[ $key ] ); $i++ )
    {
        $gap = (strtotime( $awsDates[ $key ][$i-1] ) -
            strtotime( $awsDates[ $key ][$i]) )/ (30.5 * 86400);

        // We need a tuple. Second entry is dummy. Only push if the AWS was
        array_push( $gapBetweenAWS, array( $gap, 0 ) );
    }
}


?>


<script type="text/javascript" charset="utf-8">
$(function () {

    var data = <?php echo json_encode( $awsYearData ); ?>;
    var speakers = <?php echo json_encode( $awsSpeakersPie); ?>;

    /**
     * Get histogram data out of xy data
     * @param   {Array} data  Array of tuples [x, y]
     * @param   {Number} step Resolution for the histogram
     * @returns {Array}       Histogram data
     */
    function histogram(data, step) {
        var histo = {},
            x,
            i,
            arr = [];

        // Group down
        for (i = 0; i < data.length; i++) {
            x = Math.floor(data[i][0] / step) * step;
            if (!histo[x]) {
                histo[x] = 0;
            }
            histo[x]++;
        }

        // Make the histo group into an array
        for (x in histo) {
            if (histo.hasOwnProperty((x))) {
                arr.push([parseFloat(x), histo[x]]);
            }
        }

        // Finally, sort the array
        arr.sort(function (a, b) {
            return a[0] - b[0];
        });

        return arr;
    }

    Highcharts.chart('aws_per_year', {
        chart: { type: 'column' },
        title: { text: 'Number of Annual Work Seminars per year' },
        xAxis: { min : 2010 },
        yAxis: [ { title: { text: 'AWS Count' } }, ],
        series: [{
            name: 'AWS this year',
            type: 'column',
            data: histogram(data, 1),
            pointPadding: 0,
            groupPadding: 0,
            pointPlacement: 'middle',
            showInLegend:false,
        },
    ] });

    Highcharts.chart('aws_speakers_pie', {
        chart: { type: 'pie' },
        title: { text: 'Size of each Subject Group' },
        series: [{ name: 'Number of AWS speakers'
            , data: speakers, },] }
    );

});

</script>


<script type="text/javascript" charset="utf-8">
$(function () {

    var data = <?php echo json_encode( $numAWSPerSpeaker ); ?>;

    /**
     * Get histogram data out of xy data
     * @param   {Array} data  Array of tuples [x, y]
     * @param   {Number} step Resolution for the histogram
     * @returns {Array}       Histogram data
     */
    function histogram(data, step) {
        var histo = {},
            x,
            i,
            arr = [];

        // Group down
        for (i = 0; i < data.length; i++) {
            x = Math.floor(data[i][0] / step) * step;
            if (!histo[x]) {
                histo[x] = 0;
            }
            histo[x]++;
        }

        // Make the histo group into an array
        for (x in histo) {
            if (histo.hasOwnProperty((x))) {
                arr.push([parseFloat(x), histo[x]]);
            }
        }

        // Finally, sort the array
        arr.sort(function (a, b) {
            return a[0] - b[0];
        });

        return arr;
    }

    Highcharts.chart('aws_chart1', {
        chart: {
            type: 'column'
        },
        title: {
            text: 'Speakers distrbution'
        },
        xAxis: { min : -0.5, title: {text: '#AWS given'} },
        yAxis: [{ title: { text: 'Speaker Count' }
        }, ],
        series: [{
            name: 'Number of speakers',
            type: 'column',
            data: histogram(data, 1),
            pointPadding: 0.1,
            groupPadding: 0,
            pointPlacement: 'middle'
        },
    ] });

});

</script>


<script>

$(function () {

    var data = <?php echo json_encode( $awsCountsBySpecPie ); ?>;
    Highcharts.chart('aws_gap_chart', {
        chart: { type: 'pie' },
        title: { text: 'AWS by Subject Area' },
        series: [{
            name: 'Number of AWS',
            data: data,
        },
    ] });

});
</script>

<h1>Academic statistics since March 01, 2017</h1>

<h3>Annual Work Seminars Distributions</h3>
<table class=chart>
<tr> <td> <div id="aws_per_year"></div> </td>
<td> <div id="aws_gap_chart"></div> </td>
</tr>
</table>

<h3>AWS Speakers distributions</h3>
<table class=chart>
<tr> <td> <div id="aws_chart1"></div> </td>
<td> <div id="aws_speakers_pie"></div> </td>
</tr> </table>

<h3>Thesis seminar distributions</h3>
<table class=chart>
<tr> <td> <div id="thesis_seminar_per_month"></div> </td>
<td> <div id="thesis_seminar_per_year"></div> </td>
</tr> </table>

<h1>Booking requests between <?php
    echo humanReadableDate( 'march 01, 2017') ?>
    and <?php echo humanReadableDate( $upto ); ?></h1>

<?php
echo $bookingTable;
?>

<h1>Venue usage between <?php
    echo humanReadableDate( 'march 01, 2017') ?>
    and <?php echo humanReadableDate( $upto ); ?></h1>

<h3></h3>
<table class="chart">
<tr>
    <td> <div id="venue_usage1"></div> </td>
    <td> <div id="venue_usage2" ></div> </td>
</tr>
</table>

<h3></h3>
<table class="chart">
<tr>
    <td> <div id="events_class1"></div> </td>
    <td> <div id="events_class2" ></div> </td>
</tr>
</table>

<h3></h3>
<table class="chart">
<tr>
    <td> <div id="venues_busy_time"></div> </td>
</tr>
</table>

<?php

echo '<h1>Community interaction via Annual Work Seminar </h1>';
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
echo "<p>
    Following graph shows the interaction among faculty since $fromD.
    Number on edges are number of AWSs between two faculty, either of them is involved
    in an AWS as co-supervisor or as a thesis committee member.
    </p>";

$awses = getAWSFromPast( $from  );
$network = array( 'nodes' => array(), 'edges' => array( ) );

echo printInfo( "Total " . count( $awses) . " AWSs found in database since $fromD" );

echo '
    <p> <strong>
    Hover over a node to see the interaction of particular faculty.
    </strong> </p>
    ';

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

// Each node must have a unique integer id. We use it to generate a distinct
// color for each node in d3. Also edges are drawn from id to id.
foreach( $awses as $aws )
{
    // How many AWSs this supervisor has.
    $pi = $aws[ 'supervisor_1' ];
    if( ! in_array( $pi, $pis ) )
        continue;

    $community[ $pi ]['count'] += 1;
    $community[ $pi ]['degree'] += 1;

    // Co-supervisor is an edge
    $super2 = __get__( $aws, 'supervisor_2', '' );
    if( $super2 )
    {
        if( in_array( $super2, $pis ) )
        {
            $community[ $pi ]['edges'][] =  $super2;
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
        if( in_array( $tcmMember, $pis ) )
        {
            $community[ $pi ][ 'edges' ][] = $tcmMember;
            $community[ $tcmMember ][ "degree" ] += 1;
            $foundAnyTcm = true;
        }
    }

    // If not TCM member is found for this TCM. Add an edge onto PI.
    if(! $foundAnyTcm )
        $community[ $pi ][ 'edges' ][] =  $pi;
}

// Now for each PI, draw edges to other PIs.
foreach( $community as $pi => $value )
{
    // If there are not edges from or onto this PI, ignore it.
    $login = explode( '@', $pi)[0];

    // This PI is not involved in any AWS for this duration.
    if( $value[ "degree" ] < 1 )
        continue;

    // Width represent AWS per month.
    $count = $value[ 'count' ];
    $width = 0.1 + 0.5 * ( $count / $howManyMonths );
    array_push(
        $network[ 'nodes' ], array( 'name' => $login, 'count' => $count, 'width' => $width )
    );

    foreach( array_count_values( $value['edges'] ) as $val => $edgeNum )
        array_push( $network[ 'edges']
            , array( 'source_email' => $pi, 'tgt_email' => $val, 'width' => $edgeNum
                    , 'count' => $edgeNum )
        );
}

// Before writing to JSON use index of node as source and target in edges.
$nodeNames = array_unique(
    array_map( function( $n ) { return $n['name']; }, $network['nodes'] )
);
$nodeIds = array();
foreach( $nodeNames as $id => $name )
    $nodeIds[ $name ] = $id;

for( $i = 0; $i < count( $network['edges']); $i++)
{
    $src = explode( '@', $network['edges'][$i][ 'source_email' ] )[0];
    $tgt = explode( '@', $network['edges'][$i][ 'tgt_email' ] )[0];
    $network['edges'][$i][ 'source' ] = $nodeIds[ $src ];
    $network['edges'][$i][ 'target' ] = $nodeIds[ $tgt ];
}

$networkJSON = json_encode( $network, JSON_PRETTY_PRINT );

// Write the network array to json array.
//$networkJSONFileName = "data/network.json";
//$handle = fopen( $networkJSONFileName, "w+" );
//fwrite( $handle, $networkJSON );
//fclose( $handle );
?>

<!-- Use d3 to draw graph -->
<div>
<script src="https://d3js.org/d3.v3.min.js"></script>
<script>

    var w = 1000;
    var h = 1000;

    var linkDistance=200;

    var colors = d3.scale.category10();

    var graph = <?php echo $networkJSON; ?>;

    var svg = d3.select("body").append("svg").attr({"width":w,"height":h});

    var toggle = 0;

    var charge = -100 * <?php echo $howManyMonths; ?>;
    var gravity = 0.04 * <?php echo $howManyMonths; ?>;

    var force = d3.layout.force()
        .nodes(graph.nodes)
        .links(graph.edges)
        .size([w,h])
        .linkDistance([linkDistance])
        .charge( [ charge ] )
        .theta(0.2)
        .gravity( gravity )
        .start();

    var edges = svg.selectAll("line")
      .data(graph.edges)
      .enter()
      .append("line")
      .attr("id",function(d,i) {return 'ede'+i})
      .attr( 'stroke-width', function(e) { return e.width; } )
      .style("stroke","#ccc")
      .style("pointer-events", "none");

    var node = svg.selectAll("circle")
      .data(graph.nodes)
      .enter()
      .append("circle")
      .attr({"r":function(d) { return 50 * d.width; } })
      .style( "opacity", 1 )
      .style("fill",function(d,i){return colors(10);})
      .call(force.drag)
      .on( 'mouseover', connectedNodes )
      .on( 'mouseout', connectedNodes )


    var nodelabels = svg.selectAll(".nodelabel")
       .data(graph.nodes)
       .enter()
       .append("text")
       .attr( {
                "x":     function(d){return d.x;},
               "y":      function(d){return d.y;},
               "class":  "nodelabel",
               "stroke": "blue"
              }
        )
        .text(function(d){return d.name;});

    var edgepaths = svg.selectAll(".edgepath")
        .data(graph.edges)
        .enter()
        .append('path')
        .attr({'d': function(d) {
                    return 'M '+d.source.x+' '+d.source.y+' L '+ d.target.x +' '+d.target.y
                },
                'class':'edgepath',
                'fill-opacity':0,
                'stroke-opacity':0,
                'fill':'blue',
                'stroke':'red',
                'id':function(d,i) {return 'edgepath'+i}}
        )
        .style("pointer-events", "none");

    var edgelabels = svg.selectAll(".edgelabel")
        .data(graph.edges)
        .enter()
        .append('text')
        .style("pointer-events", "none")
        .attr({'class':'edgelabel',
               'id':function(d,i){return 'edgelabel'+i},
               'dx':80,
               'dy':0,
               'font-size':10,
               'fill':'#acc'});

    edgelabels.append('textPath')
        .attr('xlink:href',function(d,i) {return '#edgepath'+i})
        .style("pointer-events", "none")
        .text(function(d,i){return d.count}); // Return edge level.


    svg.append('defs').append('marker')
        .attr({'id':'arrowhead',
               'viewBox':'-0 -5 10 10',
               'refX':25,
               'refY':0,
               'orient':'auto',
               'markerWidth':10,
               'markerHeight':10,
               'xoverflow':'visible'})
        .append('svg:path')
            .attr('d', 'M 0,-5 L 10 ,0 L 0,5')
            .attr('fill', '#ccc')
            .attr('stroke','#ccc');


    force.on("tick", function(){
        edges.attr( {
                      "x1": function(d){return d.source.x;},
                      "y1": function(d){return d.source.y;},
                      "x2": function(d){return d.target.x;},
                      "y2": function(d){return d.target.y;}
                   });

        node.attr({"cx":function(d){return d.x;},
                    "cy":function(d){return d.y;}
        });

        nodelabels.attr("x", function(d) { return d.x; })
                  .attr("y", function(d) { return d.y; });

        edgepaths.attr('d', function(d) {
            var path='M '+d.source.x+' '+d.source.y+' L '+ d.target.x +' '+d.target.y;
            return path
        });

        edgelabels.attr('transform',function(d,i){
            if (d.target.x<d.source.x){
                bbox = this.getBBox();
                rx = bbox.x+bbox.width/2;
                ry = bbox.y+bbox.height/2;
                return 'rotate(180 '+rx+' '+ry+')';
                }
            else {
                return 'rotate(0)';
                }
        });
    });


    var linkedByIndex = {};
    for (i = 0; i < graph.nodes.length; i++) {
        linkedByIndex[i + "," + i] = 1;
    };
    graph.edges.forEach(function (d) {
        linkedByIndex[d.source.index + "," + d.target.index] = 1;
    });


    function neighboring(a, b) {
        return linkedByIndex[a.index + "," + b.index];
    }

    function connectedNodes() {
        if (toggle == 0) {
            d = d3.select(this).node().__data__;
            node.style("opacity", function (o) {
                return neighboring(d, o) | neighboring(o, d) ? 1 : 0.1;
            });
            nodelabels.text( function (o) {
                if( o.name == d.name )
                    return 'Total AWS:' + d.count;
                return neighboring(d, o) | neighboring(o, d) ? o.name : '';
            });

            edges.style("stroke", function( e ) {
                console.log( e.source );
                if( e.source.name == d.name  || e.target.name == d.name )
                    return "#800";
                else
                    return "#ccc";
            });

            edgelabels.attr( 'fill', function(e) {
                if( e.source.name == d.name || e.target.name == d.name )
                    return "#000";
                else
                    return "#acc";
            } );
            toggle = 1;
        } else {
            node.style("opacity", 1);;
            nodelabels.text(function(d){return d.name;});
            toggle = 0;
            edges.style("stroke", "#ccc")
            edgelabels.attr( 'fill', "#acc" );
        }
    }


</script>
</div>

<a href="javascript:window.close();">Close Window</a>