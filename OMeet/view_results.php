<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/results_routines.php';
require '../OMeetCommon/course_properties.php';

function is_event($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && (!file_exists("{$base_path}/{$filename}/done") || stat("{$base_path}/{$filename}/done")["mtime"] > (time() - 86400 * 2)));  // Should be configurable rather than hardcoded two days
}

function name_to_link($event_id) {
  global $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./view_results.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
}

ck_testing();

// Get the submitted info
// echo "<p>\n";
$course = isset($_GET["course"]) ? $_GET["course"] : "";
$event = isset($_GET["event"]) ? $_GET["event"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
// Only translate the key if no event is specified - otherwise the key should be correct already
if ($event == "") {
    $key = translate_key($key);
}
$download_csv_flag = isset($_GET["download_csv"]) ? $_GET["download_csv"] : "";
$download_csv = ($download_csv_flag != "");

$only_course_list = isset($_GET["only_course_list"]) ? $_GET["only_course_list"] : "";
$only_return_course_list = ($only_course_list != "");

$show_school_and_club_flag = isset($_GET["show_school_and_club"]) ? $_GET["show_school_and_club"] : "";
$show_school_and_club = ($show_school_and_club_flag != "");

if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}
$base_path = get_base_path($key, "..");

if ($event == "") {
  // No event specified - show a list
  // If there is only one, then auto-choose it
  $event_list = scandir($base_path);
  $event_list = array_filter($event_list, "is_event");
  if (count($event_list) == 1) {
    $event = basename(current($event_list));
  }
  else if (count($event_list) > 1) {

    echo get_web_page_header(true, true, false);
    $event_output_array = array_map("name_to_link", $event_list);
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

$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

$results_path = get_results_path($event, $key);

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

$courses_for_parsing = array();

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

  // For the external registration program, only show the courses that actually allow registration
  // So no removed courses and no combination courses
  if (!file_exists("{$courses_path}/{$one_course}/removed") && !file_exists("{$courses_path}/{$one_course}/no_registrations")) {
    $courses_for_parsing[] = $one_course;
  }

  if (($show_course || isset($_GET["show_all_courses"])) && !$only_return_course_list) {
    $course_properties = get_course_properties("{$courses_path}/{$one_course}");
    $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    $max_score = 0;
    if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }

    $is_combo_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $COMBO_COURSE));
    if ($is_combo_course) {
      $all_courses = scandir($courses_path);
      $all_courses = array_diff($all_courses, array(".", ".."));
      $specified_courses = explode(",", $course_properties[$COMBO_COURSE_LIST]);
      // Convert the specified courses list, which is just the human readable name, into the full name which is used as the unique identifier
      $base_course_list = array();
      foreach ($all_courses as $course_unique_name) {
	foreach ($specified_courses as $base_course) {
          if (ltrim($course_unique_name, "0..9-") == $base_course) {
            $base_course_list[] = $course_unique_name;
	  }
	}
      }
    }
    else {
      $base_course_list = array();
    }

    if ($download_csv) {
      $results_string .= get_csv_results($event, $key, $one_course, "", $score_course, $max_score, $base_course_list);
    }
    else {
      $results_string .= get_results_as_string($event, $key, $one_course, "", $score_course, $max_score, $base_course_list, $show_school_and_club);
    }
  }
}

if ($download_csv) {
  $results_string .= "</pre>\n";
}

echo get_web_page_header(true, true, false);
echo "<p>Results for: <strong>{$event_name}</strong>\n";

// If the result list is long, then show the links at the top, to make it easier to jump to the course of interest
if (substr_count($results_string, "\n") > 50) {
  echo get_all_course_result_links($event, $key);
}
echo $results_string;
echo get_all_course_result_links($event, $key);

echo "<!--\n";
echo "####,Event,{$event}," . base64_encode($event_name) . "\n";
echo "####,CourseList," . implode(",", $courses_for_parsing) . "\n";
echo "-->\n";

echo get_web_page_footer();
?>
