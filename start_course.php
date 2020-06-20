<?php
require 'common_routines.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$course = $_COOKIE["course"];
$competitor_id = $_COOKIE["competitor_id"];
$event = $_COOKIE["event"];

if (($event == "") || ($competitor_id == "")) {
  error_and_exit("<p>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?" . get_error_info_string());
}

$competitor_path = "./{$event}/Competitors/{$competitor_id}";
$controls_found_path = "{$competitor_path}/controls_found";
// $control_list = file("./${event}/Courses/${course}");

if (file_exists("{$competitor_path}/name")) {
  $competitor_name = file_get_contents("{$competitor_path}/name");
}
else {
  error_and_exit("<p>ERROR: Bad registration for event \"{$event}\" and competitor \"{$competitor_id}\", please reregister and try again?");
}

if (file_exists("{$competitor_path}/si_stick")) {
  error_and_exit("<p>ERROR: {$competitor_name} registered for {$event} with si_stick, should not scan start QR code.");
}

if (file_exists("${controls_found_path}/start")) {
  $error_string = "Course " . ltrim($course, "0..9-") . " already started for {$competitor_name}.";
}
else {
  file_put_contents("${controls_found_path}/start", strval(time()));
}

echo get_web_page_header(true, false, false);

if ($error_string == "") {
  echo "<p>" . ltrim($course, "0..9-") . " course started for ${competitor_name}.\n";
}
else {
  echo "<p>ERROR: ${error_string}\n<br>Course not started.";
}

echo get_web_page_footer();
?>
