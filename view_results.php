<?php
require 'common_routines.php';

// Get the submitted info
// echo "<p>\n";
$course = $_GET["course"];
$event = $_GET["event"];
$download_csv_flag = $_GET["download_csv"];
$download_csv = ($download_csv_flag != "");

if ($event == "") {
  $event = $_COOKIE["event"];
}

if ($event == "") {
  echo "<h1>ERROR: Event not specified, no results can be shown.\n";
  exit(1);
}

$results_string = "";
if ($download_csv) {
  $results_string = "<pre>\n";
}

$course_list = scandir("./${event}/Courses");
$course_list = array_diff($course_list, array(".", ".."));

if ($course == "") {
  foreach ($course_list as $one_course) {
    if ($download_csv) {
      $results_string .= get_csv_results($event, $one_course);
    }
    else {
      $results_string .= show_results($event, $one_course);
    }
  }
}
else {
  if ($download_csv) {
    $results_string .= get_csv_results($event, $course);
  }
  else {
    $results_string .= show_results($event, $course);
  }
}

if ($download_csv) {
  $results_string .= "</pre>\n";
}

$results_string .= "<p>Show results for ";
foreach ($course_list as $one_course) {
  $results_string .= "<a href=\"./view_results?event=${event}&course=$one_course\">" . ltrim($one_course, "0..9-") . "</a> \n";
}
$results_string .= "<a href=\"./view_results?event=${event}\">All</a> \n";



?>


<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>Orienteering Event Management</title>
  <meta content="Mark O'Connell" name="author">
<?php
echo get_paragraph_style_header();
?>
<?php
echo get_table_style_header();
?>
</head>
<body>
<br>


<?php
echo $results_string;
?>

</body>
</html>
