<?php

include_once 'header.php';
include_once 'database.php';
include_once 'tohtml.php';
include_once 'html2text.php';
include_once 'methods.php';
include_once './check_access_permissions.php';

if( ! (isIntranet() || isAuthenticated( ) ) )
{
    echo printWarning( "You must either log-in OR intranet to access this page" );
    exit;
}

$year = getCurrentYear( );
$sem = getCurrentSemester( );
$slotCourses = array( );
$runningCourses = getSemesterCourses( $year, $sem );

// Collect both metadata and other information in slotCourse array.
foreach( $runningCourses as $c )
{
    $cid = $c[ 'course_id' ];
    $course = getTableEntry( 'courses_metadata', 'id' , array('id' => $cid) ); 
    $slotCourses[ $c[ 'slot' ] ][ ] = array_merge( $c, $course );
}

$slotCourseJSON = json_encode( $slotCourses );

echo alertUser( 
    "NOTICE: If you are reading this then this page does not contain officially
    approved information. Any information provided on this page may change or 
    disappear.
    ");

?>
<script type="text/javascript" charset="utf-8">
function showCourseInfo( x )
{
    alert( "Course description:\n" +  x.value );
}

function showRunningCourse( x )
{
    var slotId = x.value;
    var courses = <?php echo $slotCourseJSON; ?>;
    var runningCourses = courses[ slotId ];

    if( runningCourses && runningCourses.length > 0 )
    {
        var runningCoursesTxt = runningCourses.map( 
            function(x, index) { return (1 + index) + '. ' + x.name 
            + ' at ' + x.venue ; } 
        ).join( "\n");

        var msg = "Following courses are running in slot " + slotId 
            + "\n" + runningCoursesTxt;
    }
    else
    {
        msg = "No course is running on slot " + slotId;
    }

    alert( msg );
}
</script>

 

<?php


echo '<h1>Slots </h1>';


echo printInfo( "
    <ul>
    <li> If a course is running in slot 1, then its time is 
    represented by tiles 1A, 1B and 1C.  </li>
    <li> No course should overlap with any other course's slot tiles.  </li>
    <li> No course can run on red color tiles. These are reserved tiles. </li>
    </ul>" 
);
    
echo alertUser( 'Click on <button class="invisible" disabled>1A</button> etc to see the list of courses 
    running on this slot this semester' );
echo slotTable(  );


echo "<h1>Enrollement table for this semester courses</h1>";



echo alertUser(
    "Click on the button <button disabled>Show list</button>to see the 
    list of enrolled students" 
    );


$enrollments = array( );

/**
    * @name Show the courses.
    * @{ */
/**  @} */

echo '<table class="info">';
echo '<tr><th>Course <br> Instructors</th><th>Credit</th><th>Slot</th><th>Venue</th></tr>';
foreach( $slotCourses as $slot => $courses )
{
    foreach( $courses as $c )
    {
        $cid = $c[ 'id' ];
        $whereExpr = "year='$year' AND semester='$sem' AND course_id='$cid'";
        $registrations = getTableEntries(
            'course_registration', 'student_id', $whereExpr 
        );

        $enrollments[ $cid ] = $registrations;

        $cinfo = html2Markdown( $c[ 'description' ] );

        $slotInfo = getSlotInfo( $slot );

        echo '<tr>
            <td> <button onclick="showCourseInfo(this)" class="courseInfo" 
            value="' . $cinfo . '" >Details</button> ' . $c[ 'name' ] . '</td>
            <form method="post" action="#">
            <input type="hidden" name="course_id" value="' . $cid . '">
            <td>' . $c[ 'credits' ] . '</td>
            <td>' . $slotInfo . '</td><td>' .  $c[ 'venue' ] . '</td>
            <td>' . count( $registrations ) . '</td><td>
            <button name="response" value="show_enrollment">
            <small>Show Enrollments</small></button></td>
            </form>';
        echo '</tr>';
    }
}
echo '</table><br/>';

echo closePage( );

/**
    * @name Show enrollment.
    * @{ */
/**  @} */
if( $_POST )
{

    $cid = $_POST[ 'course_id'];
    $courseName = getCourseName( $cid );

    echo '<h3>Enrollment for course ' . $courseName .'</h3>';

    $table = '<table class="show_events">';
    $count = 0;
    foreach( $enrollments[$cid]  as $r )
    {
        $count += 1;
        $studentId = $r[ 'student_id' ];
        $login = loginToText( $studentId );
        $table .= '<tr>';
        $table .= '<td>' . $count . '</td><td>' . $login . '</td>';
        $table .= '<td>' . $r[ 'type' ] . "</td>";
        $table .= '</tr>';
    }

    $table .= '</table>';

    echo '<div style="font-size:small">';
    echo $table;
    echo '</div>';

    echo '<br>';
    echo closePage( );
}

echo '</div>';


?>
