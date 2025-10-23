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
$event = isset($_GET["event"]) ? $_GET["event"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
// Only translate the key if no event is specified - otherwise the key should be correct already
if ($event == "") {
    $key = translate_key($key);
}
$download_csv_flag = isset($_GET["download_csv"]) ? $_GET["download_csv"] : "";
$download_csv = ($download_csv_flag != "");

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

if (!event_is_using_nre_classes($event, $key)) {
  error_and_exit("<p>ERROR: classes are not enabled, please use the normal <a href=\"./view_results.php?key={$key}\">view_results</a> page.");
}

$results_path = get_results_per_class_path($event, $key);
$classification_info = get_nre_classes_info($event, $key);
$class_to_show = isset($_GET["class"]) ? $_GET["class"] : "";
$classes_to_display = get_nre_class_display_order($event, $key);

$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));

// For safety, should really strip out removed courses here, being lazy for the moment
$readable_course_hash = array();
array_map(function ($elt) use (&$readable_course_hash) { $readable_course_hash[ltrim($elt, "0..9-")] = $elt; }, $course_list);

if ($class_to_show != "") {
  $classes_to_show = array($class_to_show);
}
else {
  $classes_to_show = $classes_to_display;
}

set_timezone($key);
$event_name = file_get_contents(get_event_path($event, $key) . "/description");

$results_string = "";
if ($download_csv) {
  $results_string = "<pre>\n";
}


$courses_for_parsing = array();

foreach ($classes_to_show as $one_class) {
  
  $class_entry_to_show = array_values(array_filter($classification_info, function ($elt) use ($one_class) { return ($one_class == $elt[5]); }))[0];
  $readable_course_name = $class_entry_to_show[0];
  $one_course = isset($readable_course_hash[$readable_course_name]) ? $readable_course_hash[$readable_course_name] : "";

  $course_properties = get_course_properties("{$courses_path}/{$one_course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  if ($score_course) {
    $max_score = $course_properties[$MAX_SCORE_FIELD];
  }

  $is_combo_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $COMBO_COURSE));
  if ($is_combo_course) {
    $specified_courses = explode(",", $course_properties[$COMBO_COURSE_LIST]);
    // Convert the specified courses list, which is just the human readable name, into the full name which is used as the unique identifier
    $base_course_list = array_map(function ($elt) use ($readable_course_hash) { return ($readable_course_hash[$elt]); }, $specified_courses);
  }
  else {
    $base_course_list = array();
  }

  if ($download_csv) {
    $results_string .= get_csv_results($event, $key, $one_course, $one_class, $score_course, $max_score, $base_course_list);
  }
  else {
    $results_string .= get_results_as_string($event, $key, $one_course, $one_class, $score_course, $max_score, $base_course_list, $show_school_and_club);
  }
}

if ($download_csv) {
  $results_string .= "</pre>\n";
}


echo get_web_page_header(true, true, false, true);
echo "<p>Results for: <strong>{$event_name}</strong>\n";

// Show the links at the top if the list is long, to make it easier to jump to just the results of interest
if (substr_count($results_string, "\n") > 50) {
  echo get_all_class_result_links($event, $key, $classification_info, $classes_to_display, $readable_course_hash);
}

echo $results_string;
echo get_all_class_result_links($event, $key, $classification_info, $classes_to_display, $readable_course_hash);

echo get_web_page_footer();
?>
