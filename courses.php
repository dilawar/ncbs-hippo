<?php
include_once 'header.php';
include_once 'database.php';
include_once 'tohtml.php';
include_once 'html2text.php';
include_once 'methods.php';
include_once './check_access_permissions.php';

if( ! (isIntranet() || isAuthenticated( ) ) )
{
    echo loginOrIntranet( );
    exit;
}

?>

<!-- Sweet alert -->
<script src="./node_modules/sweetalert2/dist/sweetalert2.all.min.js"></script>
<link rel="stylesheet" type="text/css" href="./node_modules/sweetalert2/dist/sweetalert.css">


<?php
/* get this semester and next semester courses */

$year = getCurrentYear( );
$sem = getCurrentSemester( );
$slotCourses = array( );
$tileCourses = array( );
$runningCourses = getSemesterCourses( $year, $sem );

// Collect both metadata and other information in slotCourse array.
foreach( $runningCourses as $c )
{
    $cid = $c[ 'course_id' ];
    $course = getTableEntry( 'courses_metadata', 'id' , array('id' => $cid) );
    if( $course )
    {
        $slotId = $c[ 'slot' ];
        $tiles = getTableEntries( 'slots', 'groupid', "groupid='$slotId'" );
        $slotCourses[ $slotId ][ ] = array_merge( $c, $course );
        foreach( $tiles as $tile )
        {
            if( strpos( $c['ignore_tiles'], $tile[ 'id' ]) !== 0 )
            {
                $tileCourses[ $tile['id']][ ] = array_merge( $c, $course );
            }
        }
    }
}

$slotUpcomingCourses = array( );
$nextSem = getNextSemester( );
$upcomingCourses = getSemesterCourses( $nextSem[ 'year' ], $nextSem['semester'] );
foreach( $upcomingCourses as $c )
{
    $cid = $c[ 'course_id' ];
    $course = getTableEntry( 'courses_metadata', 'id' , array('id' => $cid) );
    if( $course )
    {
        $slotId = $c[ 'slot' ];
        $tiles = getTableEntries( 'slots', 'groupid', "groupid='$slotId'" );
        $slotUpcomingCourses[ $slotId ][ ] = array_merge( $c, $course );
        foreach( $tiles as $tile )
        {
            if( strpos( $c['ignore_tiles'], $tile[ 'id' ]) !== 0 )
            {
                $tileCourses[ $tile['id']][ ] = array_merge( $c, $course );
            }
        }
    }
}


$tileCoursesJSON = json_encode( $tileCourses );

?>

<script type="text/javascript" charset="utf-8">
function showCourseInfo( x )
{
    swal({
        title : x.title
        , html : "<div align=\"left\">" + x.value + "</div>"
    });
}


function showRunningCourse( x )
{
    var slotId = x.value;
    var courses = <?php echo $tileCoursesJSON; ?>;
    var runningCourses = courses[ slotId ];
    var title;
    var runningCoursesTxt;

    if( runningCourses && runningCourses.length > 0 )
    {
        runningCoursesTxt = runningCourses.map(
            function(x, index) { return (1 + index) + '. ' + x.name
            + ' at ' + x.venue ; }
        ).join( "<br>");

        title = "Following courses are running in slot " + slotId;
    }
    else
    {
        title = "No course is running on slot " + slotId;
        runningCoursesTxt = "";
    }

    swal({
        title : title
        , html : runningCoursesTxt
        , type : "info"
        });
}
</script>



<?php


echo '<h1>Slots </h1>';

echo printInfo(
    "Some courses may modify these slot timings. In case of any discrepency
    please notify " . mailto( 'acadoffice@ncbs.res.in', 'Academic Office' ) . "."
);


echo printInfo(
    "Click on tile <button class=\"invisible\" disabled>1A</button> etc to see the
    list of courses running at this time.
    ");
$table = slotTable(  );
echo $table;

/* Enrollment table. */
echo "<h1>Running courses in " . __ucwords__( $sem) . ", $year semester</h1>";

$showEnrollText = 'Show Enrollement';
echo alertUser(
    '<table class="show_info">
    <tr>
        <td> <i class="fa fa-flag-o fa-2x"></i>
        To enroll, visit <a class="clickable" href="user_manages_courses.php">My Courses</a>
        link in your home page after login. </td>
    </tr>
    <tr>
        <td>
            <i class="fa fa-flag-o fa-2x"></i>
            To see enrolled students, click on <button disabled> ' . $showEnrollText . '</button>
            in front of the course and scroll down to the bottom of the page.
        </td>
    </tr>
    <tr>
        <td>
            <i class="fa fa-flag-checkered fa-2x"></i>
            Registration on <tt>Hippo</tt> is mandatory;
            <a href="https://moodle.ncbs.res.in" target="_blank">MOODLE</a> registration
            does not qualify as  official registration.
        </td>
    </tr>
    </table>'
    );

/**
    * @name Show the courses.
    * @{ */
/**  @} */

$table = '<table class="info">';
$table .= '<tr><th>Course <br> Instructors</th><th>Schedule</th><th>Slot Tiles</th><th>Venue</th>
    <th>Enrollments</th><th>URL</th> </tr>';

// Go over courses and populate the entrollment array.
$enrollments = array( );
ksort( $slotCourses );
foreach( $slotCourses as $slot => $courses )
{
    foreach( $courses as $c )
    {
        $cid = $c[ 'course_id' ];
        $table .= '<tr>';
        $table .= courseToHTMLRow( $c, $slot, $sem, $year, $enrollments );
        $table .= '<form method="post" action="#">';
        $table .= '<td> <button name="response" value="show_enrollment">
                  <small>' . $showEnrollText . '</small></button></td>';
        $table .= '<input type="hidden" name="course_id" value="' . $cid . '">';
        $table .= '</form>';
        $table .= '</tr>';

        $data = getEnrollmentTableAndEmails( $cid, $enrollments );
        $enTable = $data[ 'html_table'];
        $allEmails = $data[ 'enrolled_emails' ];

        //$table .= '<div class="HideAndShow">';
        //$table .= "<tr><td colspan=\"7\"> $enTable </td> </tr>";
        //$table .= "</div>";
    }
}

$table .= '</table><br/>';

echo '<div style="font-size:small">';
echo $table;
echo '</div>';

/**
    * @name Show enrollment.
    * @{ */
/**  @} */

if( __get__( $_POST, 'response', '' ) == 'show_enrollment' )
{
    $cid = $_POST[ 'course_id'];
    $courseName = getCourseName( $cid );
    $rows = [ ];
    $allEmails = array( );

    echo '<h2>Enrollment list for <tt>' . $courseName .'</tt></h2>';

    $data = getEnrollmentTableAndEmails( $cid, $enrollments );
    $table = $data[ 'html_table'];
    $allEmails = $data[ 'enrolled_emails' ];

    // Display it.
    echo '<div style="font-size:small">';
    echo $table;
    echo '</div>';

    // Put a link to email to all.
    if( count( $allEmails ) > 0 )
    {
        $mailtext = implode( ",", $allEmails );
        echo '<div>' .  mailto( $mailtext, 'Send email to all students' ) . "</div>";
    }
}


/*******************************************************************************
 * Upcoming courses.
 *******************************************************************************/
// Collect both metadata and other information in slotCourse array.


$newTab = '<table id="upcoming_courses" class="info">';
$newTab .= '<tr><th>Course <br> Instructors</th><th>Schedule</th><th>Slot Tiles</th><th>Venue</th>
    <th>Enrollments</th><th>URL</th> </tr>';

foreach( $slotUpcomingCourses as $slot => $ucs )
{
    foreach( $ucs as $uc )
    {
        $newTab .= '<tr>';
        $slot = $uc[ 'slot' ];
        $sem = getSemester( $uc[ 'end_date' ] );
        $year = getYear( $uc[ 'end_date' ] );

        $newTab .= courseToHTMLRow( $uc, $slot, $sem, $year, $upcomingEnrollments);

        $newTab .= '</tr>';
    }
}
$newTab .= '</table>';

// Show table.
if( count( $slotUpcomingCourses ) > 0 )
{
    echo '<h1>Upcoming courses</h1>';
    echo '<div style="font-size:small">';
    echo $newTab;
    echo '</div>';
}

echo '<br>';
echo closePage( );

?>
