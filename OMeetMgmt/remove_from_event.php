<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();



// Get the submitted info
// echo "<p>\n";

$event = $_GET["event"];
$key = $_GET["key"];

if (($event == "") || (!key_is_valid($key))) {
  error_and_exit("Empty event \"{$event}\" or bad location key \"{$key}\", is this an unauthorized link?\n");
}

if (!file_exists(get_event_path($event, $key, ".."))) {
  error_and_exit("No such event \"{$event}\", is this an authorized link?\n");
}

$is_confirmed = isset($_GET["confirmed"]) && ($_GET["confirmed"] == "true");

$results_string = "";
$competitor_directory = get_competitor_directory($event, $key, "..");

$current_time = time();

if (isset($_GET["Remove-all"])) {
  $competitor_list = scandir("${competitor_directory}");
  $competitor_list = array_diff($competitor_list, array(".", ".."));
}
else {
  $competitor_list = array();
  foreach (array_keys($_GET) as $passed_in_arg) {
    if (substr($passed_in_arg, 0, 7) == "Remove-") {
      $arg_pieces = explode("-", $passed_in_arg);
      $competitor_list[] = $arg_pieces[1];
    }
  }
}

$removed_competitor_path = get_event_path($event, $key, "..") . "/removed_competitors";
if (!file_exists($removed_competitor_path)) {
  mkdir($removed_competitor_path);
}

$competitor_outputs = array();
$output_array = array();
$errors_array = array();
foreach ($competitor_list as $competitor) {
  if (!is_dir("{$competitor_directory}/{$competitor}")) {
    if (is_dir("{$removed_competitor_path}/{$competitor}")) {
      $removed_competitor_name = file_get_contents("{$removed_competitor_path}/{$competitor}/name");
      $errors_array[] = "{$removed_competitor_name} was already removed.";
    }
    else {
      $errors_array[] = "Unknown competitor {$competitor}, manually removed?";
    }
  }
  else {
    $course = file_get_contents("{$competitor_directory}/{$competitor}/course");
    $competitor_name = file_get_contents("{$competitor_directory}/{$competitor}/name");
    $entry_output = "{$competitor_name} from " . ltrim($course, "0..9-");
    if (file_exists("{$competitor_directory}/{$competitor}/controls_found/finish") ||
        file_exists("{$competitor_directory}/{$competitor}/self_reported")) {
      # Remove the completed entry
      $results_path = get_results_path($event, $key, "..") . "/{$course}";
      $results_listing = scandir($results_path);
      $results_listing = array_diff($results_listing, array(".", ".."));
  
      $competitor_id_len = strlen($competitor);
      foreach ($results_listing as $this_result) {
        if (substr($this_result, 0 - $competitor_id_len - 1) == ",{$competitor}") {
          # remove this entry
          if ($is_confirmed) {
            unlink("{$results_path}/{$this_result}");
          }
          $entry_output .= ", finish marker {$this_result}";
          break;
        }
      }
    }
  
    $output_array[] = array($competitor, $entry_output);
    if ($is_confirmed) {
      rename("{$competitor_directory}/{$competitor}", "{$removed_competitor_path}/{$competitor}");
    }
  }
}

$event_description = file_get_contents(get_event_path($event, $key, "..") . "/description");

echo get_web_page_header(true, true, false);

if ($is_confirmed) {
  echo "<p>Removed from {$event_description}\n";
}
else {
  echo "<p>Confirm removal from {$event_description}\n";
  echo "<form action=\"../OMeetMgmt/remove_from_event.php\">\n<input type=hidden name=\"key\" value=\"${key}\">\n";
  echo "<input type=hidden name=\"event\" value=\"${event}\">\n";
  echo "<input type=hidden name=\"confirmed\" value=\"true\">\n";
}
echo "<ul>\n";
if ($is_confirmed) {
  echo implode("\n", array_map(function ($elt) { return ("<li>{$elt[1]}"); }, $output_array));
}
else {
  echo implode("\n", array_map(function ($elt) { return ("<li><input type=checkbox name=\"Remove-{$elt[0]}\">{$elt[1]}"); }, $output_array));
}
echo "</ul>\n";
if (!$is_confirmed) {
  echo "<input type=submit value=\"Confirm removals\">\n";
  echo "</form>\n";
}

if (count($errors_array) > 0) {
  echo "<p><p>Errors found:\n<ul>";
  echo implode("\n", array_map(function ($elt) { return ("<li>{$elt}"); }, $errors_array));
  echo "</ul>\n";
}

echo get_web_page_footer();
?>
