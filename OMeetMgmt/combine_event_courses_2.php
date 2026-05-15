<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/results_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

set_page_title("Combine results within an event");

$output_string = "";
$error_string = "";
$incomplete_entry_string = "";
$incomplete_entry_hash = array();
$dnf_hash = array();

$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

$event = isset($_GET["event"]) ? $_GET["event"] : "";
if ($event == "") {
  error_and_exit("No such access key \"$key\" and event \"{$event}\", are you using an authorized link?\n");
}


$base_path = get_base_path($key);
$event_path = get_event_path($event, $key);
$results_path = get_results_path($event, $key);
$courses_path = get_courses_path($event, $key);

$course_list = array();
foreach (array_keys($_GET) as $is_this_a_course) {
  if ((substr($is_this_a_course, 0, 7) == "course-")) {
    $course_list[] = substr($is_this_a_course, 7);
  }
}


$output_string .= "<p>Combined results for <strong>" . file_get_contents("{$event_path}/description") . "</strong>\n<p><p>";
#$output_string .= "<p>Found " . count($course_list) . " courses, (" . implode(",", $course_list) . ")\n";

// foreach entry in the course_list
// get the results on the course
// establish the hash of stick to total time (and keep the individual times)
// then walk through the stick hash keys
// put entries in the hash of total_finishes, subentrants keyed by <total_time>:<stick>
// sort the keys of the hash of entrants with the same number of total finishes
// print out the results for entrants who ran the same number of courses
$results_by_class_and_stick = array();
foreach ($course_list as $one_course) {
  
    $readable_course_name = ltrim($one_course, "0..9-");
    $course_properties = get_course_properties("{$courses_path}/{$one_course}");
    if (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $COMBO_COURSE)) {
      continue;  // Motalas and the like aren't currently handled, maybe change this later
    }
    $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    $max_score = 0;
    if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }
    
    $results_array = get_course_results_as_array($event, $key, $one_course, $score_course, $max_score, array());
    #$output_string .= "<p>Processing {$readable_course_name} with " . count($results_array) . " finishers\n";
    foreach ($results_array as $this_result) {
      // For now, only look at people orienteering with a SI unit, no QRienteering
      // Also only people registered with an OUSA class, and only good finish results (no DNFs)
      if (!isset($this_result["si_stick"])) {
        continue;
      }

      if ($this_result["dnf"] != 0) {
	$dnf_hash["{$this_result["competitor_name"]}:{$this_result["si_stick"]}"] = 1;
        continue;
      }

      $hash_key = "{$this_result["si_stick"]}";
      if (!isset($results_by_class_and_stick[$hash_key])) {
        $results_by_class_and_stick[$hash_key] = array("name" => $this_result["competitor_name"],
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
      }

      $results_by_class_and_stick[$hash_key]["total_time"] += $this_result["raw_time"];
      $results_by_class_and_stick[$hash_key]["num_finishes"]++;
      $results_by_class_and_stick[$hash_key]["individual_times"][] = "{$readable_course_name} => {$this_result["time"]}";
    }
}

// Move to an array keyed by the number of courses completed and a subarray keyed by time:stick
#$output_string .= "<p>Total of " . count($results_by_class_and_stick) . " unique finishers (by class and stick)\n";
$results_by_finishes = array();
foreach ($results_by_class_and_stick as $one_result) {
    $num_finishes = $one_result["num_finishes"];
    $sortable_time = sprintf("%010d", $one_result["total_time"]);
    $hash_key = "{$sortable_time}:{$one_result["stick"]}";
    if (!isset($results_by_finishes[$num_finishes])) {
      $results_by_finishes[$num_finishes] = array();
    }
    $results_by_finishes[$num_finishes][$hash_key] = $one_result;
}


#$output_string .= "<p>Total of " . count($results_by_class) . " unique classes\n";

$finish_options = array_keys($results_by_finishes);
rsort($finish_options, SORT_NUMERIC);
foreach ($finish_options as $this_finish_count) {
  // Format the results nicely for printing
  $columns = array("Name", "Total time", "Time behind", "SI unit");
  $course_columns = array_map(function ($elt) { return ("Course {$elt}"); }, range(1, $this_finish_count));
  $header_elements = array_map(function ($elt) { return ("<th>{$elt}</th>"); }, array_merge($columns, $course_columns));
  $header_row = implode("", $header_elements);

  $output_string .= "<p>Results for running {$this_finish_count} courses\n";
  $output_string .= "<table border=1 style=\"border-collapse:collapse\">\n<tr>{$header_row}</tr>\n";
  #$output_string .= "<p>Total of " . count($results_by_class[$this_class]) . " unique finishers in the class\n";
  $results_to_show = array_keys($results_by_finishes[$this_finish_count]);
  sort($results_to_show);
  $best_time = $results_by_finishes[$this_finish_count][$results_to_show[0]]["total_time"];
  foreach ($results_to_show as $this_entrant) {
    $this_result = $results_by_finishes[$this_finish_count][$this_entrant];
    $printable_time = csv_formatted_time($this_result["total_time"]);
    $delta_time = csv_formatted_time($this_result["total_time"] - $best_time);
    $individual_times = implode("", array_map(function ($elt) { return ("<td>{$elt}</td>"); }, $this_result["individual_times"]));

    $output_string .= "<tr><td>{$this_result["name"]}</td><td>{$printable_time}</td><td>{$delta_time}</td><td>{$this_result["stick"]}</td> ";
    $output_string .= $individual_times;
    $output_string .= "</tr>\n";
  }

  $output_string .= "</table><p><p>\n";
}


echo get_web_page_header(true, false, false, true);

if (($error_string != "") && !$suppress_errors) {
  echo $error_string;
}

if (($incomplete_entry_string != "") && !$suppress_errors) {
  echo $incomplete_entry_string;
}

echo $output_string;

#echo "<p><p><p>\n";
#print_r($results_by_class_and_stick);
#echo "<p><p><p>\n";
#print_r($results_by_finishes);

echo get_web_page_footer();
?>
