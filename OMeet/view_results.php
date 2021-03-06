<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

function is_event($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("${base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function name_to_link($event_id) {
  global $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./view_results.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
}

ck_testing();

// Get the submitted info
// echo "<p>\n";
$course = $_GET["course"];
$event = $_GET["event"];
$key = $_GET["key"];
$download_csv_flag = $_GET["download_csv"];
$download_csv = ($download_csv_flag != "");


if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}
$base_path = get_base_path($key, "..");

if ($event == "") {
  // No event specified - show a list
  // If there is only one, then auto-choose it
  $event_list = scandir($base_path);
  $event_list = array_filter($event_list, is_event);
  if (count($event_list) == 1) {
    $event = basename(current($event_list));
  }
  else if (count($event_list) > 1) {

    echo get_web_page_header(true, true, false);
    $event_output_array = array_map(name_to_link, $event_list);
    echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
    echo get_web_page_footer();

    return;
  }
  else {
    echo get_web_page_header(true, true, false);
    echo "<p>No available events.\n";
    echo get_web_page_footer();
    return;
  }
}

$results_path = get_results_path($event, $key);
$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

set_timezone($key);
$event_name = file_get_contents(get_event_path($event, $key) . "/description");

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
  $show_course = true;
  if (file_exists("{$courses_path}/{$one_course}/removed")) {
    // Show a removed course if there are finishers
    if (file_exists("{$results_path}/{$one_course}")) {
      $results_list = scandir("{$results_path}/{$one_course}");
      $results_list = array_diff($results_list, array(".", ".."));
      $show_course = (count($results_list) > 0);
    }
    else {
      $show_course = false;
    }
  }

  if ($show_course || isset($_GET["show_all_courses"])) {
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
}

if ($download_csv) {
  $results_string .= "</pre>\n";
}

$results_string .= get_all_course_result_links($event, $key, "..");


echo get_web_page_header(true, true, false);
echo "<p>Results for: <strong>{$event_name}</strong>\n";

echo $results_string;

echo get_web_page_footer();
?>
