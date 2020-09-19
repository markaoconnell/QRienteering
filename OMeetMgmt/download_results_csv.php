<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$download_csv = true;

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

echo get_web_page_header(true, true, false);

if ($download_csv) {
  echo "<pre>\n";
}


$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));

$start_number = 1;
foreach ($course_list as $one_course) {
  $readable_course_name = ltrim($one_course, "0..9-");
  $course_properties = get_course_properties("{$courses_path}/{$one_course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  if ($score_course) {
    $max_score = $course_properties[$MAX_SCORE_FIELD];
  }
  
  $results_array = get_results_as_array($event, $key, $one_course, $score_course, $max_score, "..");
  $place = 1;
  foreach ($results_array as $this_result) {
    $splits_array = get_splits_as_array($this_result["competitor_id"], $event, $key);
    $si_stick = "-";
    $csv_array = array();
    $csv_array[] = $start_number;
    $csv_array[] = $si_stick;
    $csv_array[] = "-"; // Database ID
    $csv_array[] = $this_result["competitor_name"];
    $csv_array[] = "-"; // YB
    $csv_array[] = "-"; // Block
    $csv_array[] = "-"; // NC
    $csv_array[] = trim(format_time_as_minutes_since_midnight($splits_array["start"]));
    $csv_array[] = trim(format_time_as_minutes_since_midnight($splits_array["finish"]));
    $csv_array[] = trim($this_result["time"]);
    $csv_array[] = "0"; // Classifier
    $csv_array[] = "0"; // Club number
    $csv_array[] = "";  // Club name
    $csv_array[] = "-"; // City
    $csv_array[] = "";  // Nationality
    $csv_array[] = "-"; // Class number
    $csv_array[] = $readable_course_name; // Short course name
    $csv_array[] = $readable_course_name; // Long course name
    $csv_array[] = "";  // NuC1
    $csv_array[] = "";  // NuC2
    $csv_array[] = "";  // NuC3
    $csv_array[] = "";  // Text1
    $csv_array[] = "";  // Text2
    $csv_array[] = "";  // Text3
    $csv_array[] = "";  // Address name
    $csv_array[] = "";  // Street
    $csv_array[] = "";  // Line 2
    $csv_array[] = "";  // Zip
    $csv_array[] = "";  // City
    $csv_array[] = "";  // Phone
    $csv_array[] = "";  // Fax
    $csv_array[] = "";  // Email
    $csv_array[] = "";  // Id/Club
    $csv_array[] = "";  // Rented
    $csv_array[] = "";  // start fee
    $csv_array[] = "";  // paid
    $csv_array[] = "";  // course no
    $csv_array[] = $readable_course_name;  // course
    $csv_array[] = ""; // course km
    $csv_array[] = ""; // course m
    $csv_array[] = count($splits_array["controls"]); // course controls
    $csv_array[] = $place; // place
    $csv_array[] = trim(format_time_as_minutes_since_midnight($splits_array["start"]));  // start punch
    $csv_array[] = trim(format_time_as_minutes_since_midnight($splits_array["finish"])); // finish punch
    $winsplits_csv_line = implode(";", $csv_array);
    $winsplits_csv_line .= ";" . implode(";", array_map(function($elt) { return ($elt["control_id"] . ";" . trim(csv_formatted_time($elt["cumulative_time"]))); },
                                                        $splits_array["controls"]));
    echo "{$winsplits_csv_line}\n";
    $start_number++;
    $place++;
  }
}

if ($download_csv) {
  echo "</pre>\n";
}


echo get_web_page_footer();
?>
