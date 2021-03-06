<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$course = $_GET["course"];
$event = $_GET["event"];
$key = $_GET["key"];
$download_csv_flag = $_GET["download_csv"];
$download_csv = ($download_csv_flag != "");

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

set_timezone($key);

$results_string = "";
if ($download_csv) {
  $results_string = "<pre>\n";
}


if ($course != "") {
  $course_list = array($course);
}
else {
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));
}

foreach ($course_list as $one_course) {
  $course_properties = get_course_properties("{$courses_path}/{$one_course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  if ($score_course) {
    $max_score = $course_properties[$MAX_SCORE_FIELD];
  }
  
  if ($download_csv) {
    $results_string .= get_csv_results($event, $key, $one_course, $score_course, $max_score, "..");
  }
  else {
    $results_string .= show_results($event, $key, $one_course, $score_course, $max_score, "..");
  }
}

if ($download_csv) {
  $results_string .= "</pre>\n";
}

$results_string .= get_all_course_result_links($event, $key, "..");


echo get_web_page_header(true, true, false);

echo $results_string;

echo get_web_page_footer();
?>
