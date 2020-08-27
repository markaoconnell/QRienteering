<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

function non_empty($string_value) {
  return(strlen(trim($string_value)) > 0);
}

function get_competitor_info($competitor_base_path, $competitor_id, $status, $registration_info, $si_stick) {
  global $include_competitor_id, $key, $event; 
  $competitor_string = "<tr>";
  $competitor_name = file_get_contents("{$competitor_base_path}/{$competitor_id}/name");
  $competitor_course = ltrim(file_get_contents("{$competitor_base_path}/{$competitor_id}/course"), "0..9-");

  $competitor_string .= "<td>{$competitor_course}</td>";
  $competitor_string .= "<td>{$competitor_name}";
  if ($include_competitor_id) {
    $competitor_string .= " ({$competitor_id})";
  }
  $competitor_string .= "</td><td>{$status}</td><td><a href=\"./update_stick.php?key={$key}&event={$event}&competitor={$competitor_id}\">$si_stick</a></td>";
  if (count($registration_info) > 0) {
    $registration_info_strings = array_map(function ($key) use ($registration_info) { return("{$key} = {$registration_info[$key]}"); }, array_keys($registration_info));
    $competitor_string .= "<td>" . implode("<br>", $registration_info_strings)  . "</td>";
  }
  else {
    $competitor_string .= "<td></td>";
  }
  $competitor_string .= "</tr>";
  
  return($competitor_string);
}

// Get the submitted info
// echo "<p>\n";
if ($_GET["TIME_LIMIT"] == "") {
  $TIME_LIMIT = 86400;  // One day in seconds
}
else {
  $TIME_LIMIT = intval($_GET["TIME_LIMIT"]);
}

$event = $_GET["event"];
$key = $_GET["key"];
$include_competitor_id = ($_GET["include_competitor_id"] != "");

if (($event == "") || (!key_is_valid($key))) {
  error_and_exit("Empty event \"{$event}\" or bad location key \"{$key}\", is this an unauthorized link?\n");
}

if (!file_exists(get_event_path($event, $key, ".."))) {
  error_and_exit("No such event \"{$event}\", is this an authorized link?\n");
}

$results_string = "";
$competitor_directory = get_competitor_directory($event, $key, "..");
$competitor_list = scandir("${competitor_directory}");
$competitor_list = array_diff($competitor_list, array(".", ".."));

$courses_path = get_courses_path($event, $key, "..");
$courses_array = scandir($courses_path);
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries

$current_time = time();


$competitor_outputs = array();
foreach ($competitor_list as $competitor) {
  $course = file_get_contents("${competitor_directory}/${competitor}/course");
  if (!file_exists("${competitor_directory}/${competitor}/controls_found/finish")) {
      if (file_exists("{$competitor_directory}/{$competitor}/registration_info")) {
        $registration_info = parse_registration_info(file_get_contents("{$competitor_directory}/{$competitor}/registration_info"));
      }
      else {
        $registration_info = array();
      }

      if (file_exists("{$competitor_directory}/{$competitor}/si_stick")) {
        $si_stick = file_get_contents("{$competitor_directory}/{$competitor}/si_stick");
      }
      else {
        $si_stick = "none";
      }

    if (!file_exists("${competitor_directory}/${competitor}/controls_found/start")) {
      $file_info = stat("{$competitor_directory}/{$competitor}");
      // Weed out people who's registration time is too old (one day in seconds)
      if (($current_time - $file_info["mtime"]) < $TIME_LIMIT) {
        $competitor_outputs[] = get_competitor_info($competitor_directory, $competitor, "unstarted", $registration_info, $si_stick);
      }
    }
    else {
      $start_time = file_get_contents("{$competitor_directory}/${competitor}/controls_found/start");
      // Weed out people who started more than one day ago
      if (($current_time - $start_time) < $TIME_LIMIT) {
        $competitor_outputs[] = get_competitor_info($competitor_directory, $competitor, "on course", $registration_info, $si_stick);
      }
    }
  }
}

$results_string = "\n<table><tr><th>Course</th><th>Competitor</th><th>Status</th><th>Si Stick</th><th>Info</th></tr>\n";
$results_string .= implode("\n", $competitor_outputs);
$results_sting .= "\n</table>\n";




echo get_web_page_header(true, true, false);

echo $results_string;

echo get_web_page_footer();
?>