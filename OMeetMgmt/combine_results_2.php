<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

set_page_title("Results Combiner");

$output_string = "";

$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key);

$event_list = array();
foreach (array_keys($_GET) as $is_this_an_event) {
  if ((substr($is_this_an_event, 0, 6) == "event-") && is_dir("{$base_path}/{$is_this_an_event}")) {
    $event_list[] = $is_this_an_event;
  }
}

$output_string .= "<p>Found " . count($event_list) . " events, (" . implode(",", $event_list) . ")\n";

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
    $course_properties = get_course_properties("{$courses_path}/{$one_course}");
    $controls_on_course = read_controls("{$courses_path}/{$one_course}/controls.txt");
    $number_controls = count($controls_on_course);
    $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    $max_score = 0;
    if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }
    
    $results_array = get_results_as_array($this_event, $key, $one_course, $score_course, $max_score);
    $output_string .= "<p>Processing {$readable_course_name} with " . count($results_array) . " finishers\n";
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
        }

        if ($results_by_class_and_stick[$hash_key]["course"] != $readable_course_name) {
        }
      }

      // Check if the competitive class is appropriate for the course
      $results_by_class_and_stick[$hash_key]["total_time"] += $this_result["raw_time"];
      $results_by_class_and_stick[$hash_key]["num_finishes"]++;
      $results_by_class_and_stick[$hash_key]["individual_times"][] = $this_result["time"];
    }
  }
}

// Move to an array keyed by class and subarray keyed by time:stick
$output_string .= "<p>Total of " . count($results_by_class_and_stick) . " unique finishers (by class and stick)\n";
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
$output_string .= "<p>Total of " . count($results_by_class) . " unique classes\n";
$class_keys = array_keys($results_by_class);
asort($class_keys);
foreach ($class_keys as $this_class) {
  $output_string .= "<p>Results for {$this_class}\n";
  $output_string .= "<p>Total of " . count($results_by_class[$this_class]) . " unique finishers in the class\n";
  $class_results = array_keys($results_by_class[$this_class]);
  asort($class_results);
  foreach ($class_results as $this_entrant) {
    $this_result = $results_by_class[$this_class][$this_entrant];
    $printable_time = csv_formatted_time($this_result["total_time"]);
    $individual_times = implode(", ", $this_result["individual_times"]);

    $output_string .= "<p>{$this_result["name"]}, {$printable_time}, {$this_result["course"]}, {$this_result["stick"]}, ";
    $output_string .= $individual_times;
    $output_string .= "\n";
  }

  $output_string .= "<p><p>\n";
}

echo get_web_page_header(true, false, false);

echo $output_string;

echo "<p>Showing results by class and stick\n";
print_r($results_by_class_and_stick);
echo "<p><p><p>Showing results by class\n";
print_r($results_by_class);

echo get_web_page_footer();
?>
