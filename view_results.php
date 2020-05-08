<?php
require 'common_routines.php';
require 'course_properties.php';

ck_testing();

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
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

$results_string = "";
if ($download_csv) {
  $results_string = "<pre>\n";
}

$course_list = scandir("./${event}/Courses");
$course_list = array_diff($course_list, array(".", ".."));

if ($course == "") {
  foreach ($course_list as $one_course) {
    $course_properties = get_course_properties("./{$event}/Courses/{$one_course}");
    $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    $max_score = 0;
    if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }

    if ($download_csv) {
      $results_string .= get_csv_results($event, $one_course, $score_course, $max_score);
    }
    else {
      $results_string .= show_results($event, $one_course, $score_course, $max_score);
    }
  }
}
else {
  $course_properties = get_course_properties("./{$event}/Courses/{$course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
  }

  if ($download_csv) {
    $results_string .= get_csv_results($event, $course, $score_course, $max_score);
  }
  else {
    $results_string .= show_results($event, $course, $score_course, $max_score);
  }
}

if ($download_csv) {
  $results_string .= "</pre>\n";
}

$results_string .= get_all_course_result_links($event);


echo get_web_page_header(true, true, false);

echo $results_string;

echo get_web_page_footer();
?>
