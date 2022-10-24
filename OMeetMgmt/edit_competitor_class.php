<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

set_page_title("Edit Competitor Class");

// Get the submitted info
// echo "<p>\n";
$event = isset($_GET["event"]) ? $_GET["event"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
$competitor = isset($_GET["competitor"]) ? $_GET["competitor"] : "";

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, cannot edit competitor class.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, cannot edit class.\n");
}

$courses_path = get_courses_path($event, $key);
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

$competitor_path = get_competitor_path($competitor, $event, $key);
if (!is_dir($competitor_path)) {
  error_and_exit("<p>ERROR: No such competitor found {$competitor} (possibly already removed or edited?).\n");
}

if (!event_is_using_nre_classes($event, $key)) {
  error_and_exit("<p>ERROR: Event is not using NRE classes.\n");
}

$course = file_get_contents("{$competitor_path}/course");

$output_string = "";
$finish_entry_marker = "";

if (isset($_GET["new_competitor_class"])) {
  $is_self_reported = file_exists("{$competitor_path}/self_reported");
  $finish_file_exists = file_exists("{$competitor_path}/controls_found/finish");
  $has_finished = $finish_file_exists || $is_self_reported;

  $original_class = get_class_for_competitor($competitor_path);
  $new_class = $_GET["new_competitor_class"];

  if ($has_finished) {
    # Find the completion entry
    # and move it from the "old" class to the "new" class
    $results_path = get_results_path($event, $key) . "/{$course}";
    $results_listing = scandir($results_path);
    $results_listing = array_diff($results_listing, array(".", ".."));

    $competitor_id_len = strlen($competitor);
    foreach ($results_listing as $this_result) {
      if (substr($this_result, 0 - $competitor_id_len - 1) == ",{$competitor}") {
	$finish_entry_marker = $this_result; 
        break;
      }
    }

    # Not really sure that we should be able to get here without a valid finish_entry, but for safety...
    if ($finish_entry_marker != "") {
      $results_per_class_path = get_results_per_class_path($event, $key);
      if ($original_class != "") {
        unlink("{$results_per_class_path}/{$original_class}/{$finish_entry_marker}");
      }

      if ($new_class != "Remove classification") {
        if (!file_exists("{$results_per_class_path}")) {
          mkdir("{$results_per_class_path}");
        }
        if (!file_exists("{$results_per_class_path}/{$new_class}")) {
          mkdir("{$results_per_class_path}/{$new_class}");
        }
        file_put_contents("{$results_per_class_path}/{$new_class}/{$finish_entry_marker}", "");
        file_put_contents("{$results_per_class_path}/{$new_class}/{$finish_entry_marker}", "");
      }
    }
  }

  if ($new_class == "Remove classification") {
    remove_class_for_competitor($competitor_path);
    $new_class = "Recreational (unranked)";
  }
  else {
    set_class_for_competitor($competitor_path, $new_class);
  }

  $output_string .= "<p>New ranking class for: " . file_get_contents("{$competitor_path}/name") . "\n";
  $output_string .= "<p>Current class is now: {$new_class}\n";
}
else {
  $course_for_classification = ltrim($course, "0..9-");

  $current_class = get_class_for_competitor($competitor_path);
  
  $classification_info = get_nre_classes_info($event, $key);
  $possible_classes_for_course = array_filter($classification_info,
  	                                    function ($elt) use ($course_for_classification) { return ($course_for_classification == $elt[0]); });
  
  
  $output_string .= "<p>Edit ranking class for: " . file_get_contents("{$competitor_path}/name") . "\n";
  $output_string .= "<p>Current class is: " . (($current_class != "") ? $current_class : "Recreational (unranked)") . "\n";
  $output_string .= "<form action=\"./edit_competitor_class.php\">";
  $output_string .= "<input type=hidden name=\"key\" value=\"{$key}\">\n";
  $output_string .= "<input type=hidden name=\"event\" value=\"{$event}\">\n";
  $output_string .= "<input type=hidden name=\"competitor\" value=\"{$competitor}\">\n";
  $output_string .= "<select name=\"new_competitor_class\" id=\"select_class\">\n";
  
  if ($current_class != "") {
    $output_string .= "<optgroup label=\"Move to recreational\">\n";
    $output_string .= "<option value=\"Remove classification\">Move to unranked</option>\n";
    $output_string .= "</optgroup>\n";
  }
  
  if (count($possible_classes_for_course) > 0) {
    $output_string .= "<optgroup label=\"Classes appropriate for {$course_for_classification}\">\n";
    $elements = array_map(function ($elt) { return ("<option value=\"{$elt[5]}\">{$elt[5]}</option>\n"); }, $possible_classes_for_course);
    $output_string .= implode("\n", $elements);
    $output_string .= "</optgroup>\n";
  }
  
  $output_string .= "<optgroup label=\"All classes\">\n";
  $elements = array_map(function ($elt) { return ("<option value=\"{$elt[5]}\">{$elt[5]}</option>\n"); }, $classification_info);
  $output_string .= implode("\n", $elements);
  $output_string .= "</optgroup>\n";
  $output_string .= "<br/><input type=submit value=\"Save new class\">\n";
  $output_string .= "</form>\n";
}

echo get_web_page_header(true, true, false);

echo $output_string;

echo "<p><p><p><a href=\"../OMeetMgmt/competitor_info.php?key={$key}&event={$event}\">Return to main competitor info page</a>\n";

echo get_web_page_footer();
?>
