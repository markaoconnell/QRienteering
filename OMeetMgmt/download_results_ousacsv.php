<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = isset($_GET["event"]) ? $_GET["event"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
$download_csv = !isset($_GET["show_as_html"]);

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

set_timezone($key);

// Do the header line
$output = "LastName,FirstName,BirthYear,Block,NC,Time,Classifier,ClubName,OUSA_Class,Course,km (len),m (climb),num controls\n";

$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));

foreach ($course_list as $one_course) {
  $readable_course_name = ltrim($one_course, "0..9-");
  $course_properties = get_course_properties("{$courses_path}/{$one_course}");
  $controls_on_course = read_controls("{$courses_path}/{$one_course}/controls.txt");
  $number_controls = count($controls_on_course);
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  if ($score_course) {
    $max_score = $course_properties[$MAX_SCORE_FIELD];
  }
  
  $results_array = get_results_as_array($event, $key, $one_course, $score_course, $max_score, "..");
  $place = 1;
  foreach ($results_array as $this_result) {
    // If the splits array is empty, there is an error - most likely a self reported result with
    // no splits available, so just skip it.
    $splits_array = get_splits_for_download($this_result["competitor_id"], $event, $key);
    if (count($splits_array) == 0) {
      continue;
    }

    // For OUSA, don't report results for recreational runners (i.e. those without an
    // assigned OUSA competitive course)
    if ($this_result["competitive_class"] == "") {
      continue;
    }

    $first_space_pos = strpos($this_result["competitor_name"], " ");
    if ($first_space_pos !== false) {
      $first_name = substr($this_result["competitor_name"], 0, $first_space_pos);
      $last_name = substr($this_result["competitor_name"], $first_space_pos + 1);
    }
    else {
      $first_name = "no-first-name";
      $last_name = $this_result["competitor_name"];
    }

    $csv_array = array();
    $csv_array[] = "\"{$last_name}\"";  // Surname
    $csv_array[] = "\"{$first_name}\"";  // First name
    $csv_array[] = $this_result["birth_year"]; // Year of Birth
    $csv_array[] = ""; // Block
    $csv_array[] = ($this_result["competitive_class"] != "") ? "0" : "1"; // NC
    $csv_array[] = trim($this_result["time"]);
    $csv_array[] = $this_result["dnf"] ? "2" : "1"; // 2 = DNF, 1 = good, 3 = MP - Classifier
    $csv_array[] = $this_result["club_name"];  // Club Name
    $csv_array[] = $this_result["competitive_class"];  // OUSA class
    $csv_array[] = $readable_course_name; // Short course name
    $csv_array[] = "";  // km - length
    $csv_array[] = "";  // m - climb
    $csv_array[] = $number_controls;
    $winsplits_csv_line = implode(",", $csv_array);
    $output .= "{$winsplits_csv_line}\n";
  }
}

if ($download_csv) {
  header('Content-disposition: attachment; filename=splits.csv');
  header('Content-type: application/octet-stream');
}
else {
  echo get_web_page_header(true, true, false);
  echo "<pre>\n";
}

echo $output;

if (!$download_csv) {
  echo "</pre>\n";
  echo get_web_page_footer();
}

?>
