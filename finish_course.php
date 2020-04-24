<?php
require 'common_routines.php';

// Get the submitted info
// echo "<p>\n";
$competitor_name = $_COOKIE["competitor_name"];
$course = $_COOKIE["course"];
$competitor_id = $_COOKIE["competitor_id"];
$next_control = $_COOKIE["next_control"];
$event = $_COOKIE["event"];

if (($event == "") || ($competitor_id == "")) {
  echo "<h1>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?";
  echo "<br><h1>This is a BYOM (Bring Your Own Map) Orienteering control.  For more information on orienteering, \n";
  echo "type \"orienteering new england\" into Google to learn about the sport and to find events in your area.\n";
  echo "If this is hanging in the woods, please leave it alone so as not to ruin an existing orienteering course that\n";
  echo "others may be currently enjoying.";
  exit(1);
}

$competitor_path = "./" . $event . "/Competitors/" . $competitor_id;
$control_list = file("./${event}/Courses/${course}/controls.txt");
$control_list = array_map('trim', $control_list);
//echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);
$error_string = "";

if (!file_exists("${competitor_path}/start")) {
  echo "<h1>Course " . ltrim($course, "0..9-") . " not yet started.\n";
  echo "<br>Please scan the start QR code to start a course.\n";
  exit(1);
}

// See how many controls have been completed
$controls_done = scandir("./${competitor_path}");
$controls_done = array_diff($controls_done, array(".", "..", "course", "name", "next", "start", "finish", "extra", "dnf")); // Remove the annoying . and .. entries
// echo "<br>Controls done on the ${course} course.<br>\n";
// print_r($controls_done);

// Are we at the right control?
$number_controls_found = count($controls_done);
$number_controls_on_course = count($control_list);
// echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found] . "--\n";
if ($number_controls_found != $number_controls_on_course) {
    $error_string .= "<p>Not all controls found, found ${number_controls_found} controls, expected ${number_controls_on_course} controls.\n";
    file_put_contents($competitor_path . "/dnf", $error_string, FILE_APPEND);
}

$now = time();
file_put_contents($competitor_path . "/finish", strval($now));
$course_started_at = file_get_contents($competitor_path . "/start");
$time_taken = $now - $course_started_at;
if (!file_exists("./${event}/Results/${course}")) {
  mkdir("./${event}/Results/${course}");
}
$result_filename = sprintf("%06d,%s", $time_taken, $competitor_id);
file_put_contents("./${event}/Results/${course}/${result_filename}", "");

// Don't forget to update the results file

// Clear the cookies, ready for another course registration
setcookie("competitor_id", $competitor_id, 1);
setcookie("course", $course, 1);
setcookie("next_control", "start", 1);

$results_list = scandir("./${event}/Results/${course}");
$results_list = array_diff($results_list, array(".", ".."));
?>


<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>Orienteering Event Management</title>
  <meta content="Mark O'Connell" name="author">
<?php 
echo get_table_style_header();
?>
<?php
echo get_paragraph_style_header();
?>
</head>
<body>
<br>


<?php
if ($error_string != "") {
  echo "<p>ERROR: ${error_string}\n";
}

if (file_exists("${competitor_path}/dnf")) {
  echo "<p>ERROR: DNF status.\n";
}

echo "<p class=\"title\">Course complete, time taken " . formatted_time($time_taken) . "<p><p>";

echo show_results($event, $course);

// echo "<p>Course started at ${course_started_at}, course finished at ${now}, difference is ${time_taken}.\n";
?>

</body>
</html>
