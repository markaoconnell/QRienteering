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

$results_string = "";
$competitor_directory = get_competitor_directory($event, $key, "..");
$removed_competitor_path = get_event_path($event, $key, "..") . "/removed_competitors";
if (!file_exists($removed_competitor_path)) {
  mkdir($removed_competitor_path);
}


$current_time = time();

// Even though the parameters are passed in as Remove-all or Remove-{id},
// we are really restoring the entries in this script
if (isset($_GET["Remove-all"])) {
  $competitor_list = scandir("${removed_competitor_path}");
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

$competitor_outputs = array();
$output_array = array();
foreach ($competitor_list as $competitor) {
  if (!is_dir("{$removed_competitor_path}/{$competitor}")) {
    if (is_dir("{$competitor_directory}/{$competitor}")) {
      $removed_competitor_name = file_get_contents("{$competitor_directory}/{$competitor}/name");
      $output_array[] = "{$removed_competitor_name} was already restored.";
    }
    else {
      $output_array[] = "Unknown competitor {$competitor}, manually manipulated?";
    }
  }
  else {
    $course = file_get_contents("{$removed_competitor_path}/{$competitor}/course");
    $competitor_name = file_get_contents("{$removed_competitor_path}/{$competitor}/name");
    $entry_output = "{$competitor_name} to " . ltrim($course, "0..9-");
    if (file_exists("{$removed_competitor_path}/{$competitor}/controls_found/finish") ||
        file_exists("{$removed_competitor_path}/{$competitor}/self_reported")) {
      # Add the completed entry to the Results directory
      $competitor_entries = scandir("{$removed_competitor_path}/{$competitor}");
      $competitor_entries = array_diff($competitor_entries, array(".", ".."));
      $results_path = get_results_path($event, $key, "..") . "/{$course}";

      $prefix_length = strlen("ResultEntry-");
      foreach ($competitor_entries as $this_entry) {
        if (substr($this_entry, 0, $prefix_length) == "ResultEntry-") {
          $result_entry = substr($this_entry, $prefix_length); 
	  rename("{$removed_competitor_path}/{$competitor}/{$this_entry}", "{$results_path}/{$result_entry}");
	  break;
	}
      }
    }
  
    $output_array[] = $entry_output;
    rename("{$removed_competitor_path}/{$competitor}", "{$competitor_directory}/{$competitor}");
  }
}

$event_description = file_get_contents(get_event_path($event, $key, "..") . "/description");

echo get_web_page_header(true, true, false);

echo "<p>Restored from {$event_description}\n";
echo "<ul>\n";
echo implode("\n", array_map(function ($elt) { return ("<li>{$elt}"); }, $output_array));
echo "</ul>\n";

echo get_web_page_footer();
?>
