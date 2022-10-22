<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

set_page_title("Results Combiner");

$output_string = "";
$error_string = "";

$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

$suppress_errors = isset($_GET["suppress_errors"]);

$base_path = get_base_path($key);

$event_list = array();
foreach (array_keys($_GET) as $is_this_an_event) {
  if ((substr($is_this_an_event, 0, 6) == "event-") && is_dir("{$base_path}/{$is_this_an_event}")) {
    $event_list[] = $is_this_an_event;
  }
}

if (count($event_list) < 2) {
  error_and_exit("<p>Only " . count($event_list) . " events selected, must be at least 2 to combine results.\n");
}

# Get the information about the NRE classes
$classification_info = get_nre_classes_info($key);

#$output_string .= "<p>Found " . count($event_list) . " events, (" . implode(",", $event_list) . ")\n";

// foreach entry in the event_list
// get its statistics
// establish the hash of stick-class to time (and keep the individual times)
// then walk through the stick-class hash keys
// put entries in the class-total_time-stick hash
// sort the keys of the class-total_time-stick hash
// print out the results by class
// Maybe print out the results by class in a nicer order???
$results_by_class_and_stick = array();
foreach ($event_list as $this_event) {
  $courses_path = get_courses_path($this_event, $key);
  if (!file_exists($courses_path)) {
    $output_string .= "<p>ERROR: No such event found {$this_event} (or bad location key {$key}).\n";
    continue;
  }
  
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));
  
  foreach ($course_list as $one_course) {
    $readable_course_name = ltrim($one_course, "0..9-");
    $possible_classification_entries_for_course = array_filter($classification_info,
	                                             function ($elt) use ($readable_course_name)
						     { return ($readable_course_name == $elt[0]); });
    $possible_classes_for_course = array_map(function ($elt) { return ($elt[5]); }, $possible_classification_entries_for_course);
    $course_properties = get_course_properties("{$courses_path}/{$one_course}");
    $controls_on_course = read_controls("{$courses_path}/{$one_course}/controls.txt");
    $number_controls = count($controls_on_course);
    $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    $max_score = 0;
    if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }
    
    $results_array = get_results_as_array($this_event, $key, $one_course, $score_course, $max_score);
    #$output_string .= "<p>Processing {$readable_course_name} with " . count($results_array) . " finishers\n";
    foreach ($results_array as $this_result) {
      // For now, only look at people orienteering with a SI unit, no QRienteering
      // Also only people registered with an OUSA class, and only good finish results (no DNFs)
      if (!isset($this_result["si_stick"])) {
        continue;
      }

      if (!isset($this_result["competitive_class"])) {
        continue;
      }

      if ($this_result["competitive_class"] == "") {
        continue;
      }

      if ($this_result["dnf"] != 0) {
        continue;
      }

      $hash_key = "{$this_result["competitive_class"]}:{$this_result["si_stick"]}";
      if (!isset($results_by_class_and_stick[$hash_key])) {
        $results_by_class_and_stick[$hash_key] = array("name" => $this_result["competitor_name"],
                                                       "course" => $readable_course_name,
                                                       "competitive_class" => $this_result["competitive_class"],
                                                       "stick" => $this_result["si_stick"],
                                                       "total_time" => 0,
						       "individual_times" => array(),
						       "num_finishes" => 0);
      }
      else {
        if ($results_by_class_and_stick[$hash_key]["name"] != $this_result["competitor_name"]) {
          $error_string .= "<p>SI user ({$this_result["si_stick"]}) has different names: \"{$results_by_class_and_stick[$hash_key]["name"]}\"" .
			   " vs " .
       	                   "\"{$this_result["competitor_name"]}\" - please validate that this is correct.\n";
 	}

        if ($results_by_class_and_stick[$hash_key]["course"] != $readable_course_name) {
          $error_string .= "<p>SI user ({$this_result["si_stick"]}) \"{$results_by_class_and_stick[$hash_key]["name"]}\"" .
			   " ran different courses, " .
       	                   "\"{$results_by_class_and_stick[$hash_key]["course"]}\" vs \"{$this_result["course"]}\" - please check this entry.\n";
        }
      }

      // Check if the competitive class is appropriate for the course
      if (!in_array($this_result["competitive_class"], $possible_classes_for_course)) {
          $error_string .= "<p>SI user ({$this_result["si_stick"]}) \"{$results_by_class_and_stick[$hash_key]["name"]}\"" .
			   " has wrong class \"{$this_result["competitive_class"]}\" for course " .
			   "\"{$readable_course_name}\" - please check this entry, valid values are: " .
			   implode(", ", $possible_classes_for_course) . "\n";
      }
      $results_by_class_and_stick[$hash_key]["total_time"] += $this_result["raw_time"];
      $results_by_class_and_stick[$hash_key]["num_finishes"]++;
      $results_by_class_and_stick[$hash_key]["individual_times"][] = $this_result["time"];
    }
  }
}

// Move to an array keyed by class and subarray keyed by time:stick
#$output_string .= "<p>Total of " . count($results_by_class_and_stick) . " unique finishers (by class and stick)\n";
$results_by_class = array();
foreach ($results_by_class_and_stick as $one_result) {
  if ($one_result["num_finishes"] == count($event_list)) {
    $competitive_class = $one_result["competitive_class"];
    $sortable_time = sprintf("%010d", $one_result["total_time"]);
    $hash_key = "{$sortable_time}:{$one_result["stick"]}";
    if (!isset($results_by_class[$competitive_class])) {
      $results_by_class[$competitive_class] = array();
    }
    $results_by_class[$competitive_class][$hash_key] = $one_result;
  }
}

// Format the results nicely for printing
$columns = array("Name", "Total time", "Course", "SI unit");
$individual_time_columns = array_map(function ($elt) { return ("Event {$elt}"); }, range(1, count($event_list)));
$header_elements = array_map(function ($elt) { return ("<th>{$elt}</th>"); }, array_merge($columns, $individual_time_columns));
$header_row = implode("", $header_elements);

#$output_string .= "<p>Total of " . count($results_by_class) . " unique classes\n";
// Get the order to print the results
$custom_class_order = get_nre_class_display_order($key);
$named_classes = array();

// Pick out the classes that are explicitly named
foreach ($custom_class_order as $this_class) {
  if (isset($results_by_class[$this_class])) {
    $named_classes[] = $this_class;
  }
}
// See if there are any classes that someone ran which are not listed in the customized order
// Just printed these sorted lexically
$extra_classes = array_diff(array_keys($results_by_class), $custom_class_order);
asort($extra_classes);

foreach (array_merge($named_classes, $extra_classes) as $this_class) {
  $output_string .= "<p>Results for {$this_class}\n";
  $output_string .= "<table border=1 style=\"border-collapse:collapse\">\n<tr>{$header_row}</tr>\n";
  #$output_string .= "<p>Total of " . count($results_by_class[$this_class]) . " unique finishers in the class\n";
  $class_results = array_keys($results_by_class[$this_class]);
  asort($class_results);
  foreach ($class_results as $this_entrant) {
    $this_result = $results_by_class[$this_class][$this_entrant];
    $printable_time = csv_formatted_time($this_result["total_time"]);
    $individual_times = implode("", array_map(function ($elt) { return ("<td>{$elt}</td>"); }, $this_result["individual_times"]));

    $output_string .= "<tr><td>{$this_result["name"]}</td><td>{$printable_time}</td><td>{$this_result["course"]}</td><td>{$this_result["stick"]}</td> ";
    $output_string .= $individual_times;
    $output_string .= "</tr>\n";
  }

  $output_string .= "</table><p><p>\n";
}

echo get_web_page_header(true, false, false);

if (($error_string != "") && !$suppress_errors) {
  echo $error_string;
}

echo $output_string;

echo get_web_page_footer();
?>
