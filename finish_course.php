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

$competitor_path = "./" . $event . "/Competitors/" . $competitor_id;
$controls_found_path = "{$competitor_path}/controls_found";

if (!file_exists($competitor_path) || !file_exists("./{$event}/Courses/{$course}/controls.txt")) {
  error_and_exit("<p>ERROR: Event \"{$event}\" or competitor \"{$competitor}\" appears to be no longer appears valid, please re-register and try again.\n");
}

$control_list = file("./${event}/Courses/${course}/controls.txt");
$control_list = array_map('trim', $control_list);
//echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);
$error_string = "";

if (!file_exists("${controls_found_path}/start")) {
  error_and_exit("<p>Course " . ltrim($course, "0..9-") . " not yet started.\n<br>Please scan the start QR code to start a course.\n");
}

if (!file_exists("{$controls_found_path}/finish")) {
  // See how many controls have been completed
  $controls_done = scandir("./${controls_found_path}");
  $controls_done = array_diff($controls_done, array(".", "..", "start", "finish")); // Remove the annoying . and .. entries
  // echo "<br>Controls done on the ${course} course.<br>\n";
  // print_r($controls_done);
  
  // Are we at the right control?
  $number_controls_found = count($controls_done);
  $number_controls_on_course = count($control_list);
  // echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found] . "--\n";
  if ($number_controls_found != $number_controls_on_course) {
      $error_string .= "<p>Not all controls found, found ${number_controls_found} controls, expected ${number_controls_on_course} controls.\n";
      file_put_contents("{$competitor_path}/dnf", $error_string, FILE_APPEND);
  }
  
  $now = time();
  file_put_contents("{$controls_found_path}/finish", strval($now));
  $course_started_at = file_get_contents("{$controls_found_path}/start");
  $time_taken = $now - $course_started_at;
  if (!file_exists("./${event}/Results/${course}")) {
    mkdir("./${event}/Results/${course}");
  }
  $result_filename = sprintf("%06d,%s", $time_taken, $competitor_id);
  file_put_contents("./${event}/Results/${course}/${result_filename}", "");
}
else {
  $error_string .= "<p>Second scan of finish?  Finish time not updated.\n";
  $course_started_at = file_get_contents("{$controls_found_path}/start");
  $course_finished_at = file_get_contents("{$controls_found_path}/finish");
  $time_taken = $course_finished_at - $course_started_at;
}


// Clear the cookies, ready for another course registration
// Set them as expired a day ago
setcookie("competitor_id", $competitor_id, $now - 86400);
setcookie("course", $course, $now - 86400);
setcookie("next_control", "start", $now - 86400);

$results_list = scandir("./${event}/Results/${course}");
$results_list = array_diff($results_list, array(".", ".."));

echo get_web_page_header(true, true, false);

if ($error_string != "") {
  echo "<p>ERROR: ${error_string}\n";
}

if (file_exists("${competitor_path}/dnf")) {
  echo "<p>ERROR: DNF status.\n";
}

echo "<p class=\"title\">Course complete, time taken " . formatted_time($time_taken) . "<p><p>";

echo show_results($event, $course);
echo get_all_course_result_links($event);

// echo "<p>Course started at ${course_started_at}, course finished at ${now}, difference is ${time_taken}.\n";

echo get_web_page_footer();
?>
