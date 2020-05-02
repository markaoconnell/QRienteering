<?php
require 'common_routines.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$competitor_name = $_COOKIE["competitor_name"];
$course = $_COOKIE["course"];
$competitor_id = $_COOKIE["competitor_id"];
$next_control = $_COOKIE["next_control"];
$event = $_COOKIE["event"];

echo get_web_page_header(true, false, false);

// Validate that all looks good
$error = false;
if ($event == "") {
  echo "<p>ERROR No event specified in cookie.\n";
  $error = true;
}

if ($competitor_name == "") {
  echo "<p>ERROR No competitor name specified in cookie.\n";
  $error = true;
}

if ($course == "") {
  echo "<p>ERROR No course specified in cookie.\n";
  $error = true;
}

if ($competitor_id == "") {
  echo "<p>ERROR No competitor id found in cookie.\n";
  $error = true;
}

if ($next_control == "" ) {
  echo "<p>ERROR No next control found in cookie.\n";
  $error = true;
}


$courses_array = scandir('./' . $event . '/Courses');
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
// print_r($courses_array);
// echo "<p>\n";

// Validate the info
if (!in_array($course, $courses_array)) {
  echo "<p>ERROR: Course ${course} not in list of courses for this event.\n";
  $error = true;
}

// Input information is all valid, check the saved competitor information
$competitor_path = "./" . $event . "/Competitors/" . $competitor_id;
if (!file_exists($competitor_path)) {
  echo "<p>ERROR no competitor entry found \"${competitor_path}\"\n";
  $error = true;
}

if (file_exists($competitor_path . "/name")) {
  $file_name = file_get_contents($competitor_path . "/name");
  if ($file_name != $competitor_name) {
    echo "<p>ERROR name mismatch in cookie \"${competitor_name}\" and registered name \"${file_name}\".\n";
    $error = true;
  }
}
else {
  echo "<p>ERROR No competitor name information saved.\n";
  $error = true;
}

if (file_exists($competitor_path . "/course")) {
  $file_course = file_get_contents($competitor_path . "/course");
  if ($file_course != $course) {
    echo "<p>ERROR course mismatch in cookie \"${course}\" and registered course\"${file_course}\".\n";
    $error = true;
  }
}
else {
  echo "<p>ERROR No competitor course information saved.\n";
  $error = true;
}

if (file_exists("./${competitor_path}/start")) {
  echo "<p>ERROR start file exists for ${competitor_name}, have you started the course already?\n";
  $error = true;
}


if (!$error) {
  echo "<p>All good ${competitor_name}, you are ready to start on " . ltrim($course, "0..9-") . "!\n";
}

echo get_web_page_footer();
