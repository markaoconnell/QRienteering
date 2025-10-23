<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/results_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

set_page_title("Results Predictor");

$output_string = "";
$error_string = "";
$incomplete_entry_string = "";
$incomplete_entry_hash = array();
$dnf_hash = array();

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

//if (count($event_list) < 2) {
//  error_and_exit("<p>Only " . count($event_list) . " events selected, must be at least 2 to combine results.\n");
//}

// Get the information about the NRE classes
// This is a little incorrect - this assumes that all the events being combined
// are using the same set of classes - a reasonable assumption for practical purposes
// but this should really be verified somehow
$classification_info = get_nre_classes_info($event_list[0], $key);

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
    if (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $COMBO_COURSE)) {
      continue;  // Motalas and the like aren't currently handled for an NRE, maybe change this later
    }
    $controls_on_course = read_controls("{$courses_path}/{$one_course}/controls.txt");
    $number_controls = count($controls_on_course);
    $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    $max_score = 0;
    if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }
    
    $results_array = get_course_results_as_array($this_event, $key, $one_course, $score_course, $max_score);
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
	$dnf_hash["{$this_result["competitor_name"]}:{$this_result["si_stick"]}"] = 1;
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

  // Look to see if this is an unfinished event where we need to account for outstanding runners
  //$error_string .= "<p>Event {$this_event} in tss GET is {$_GET["time_since_start-{$this_event}"]}\n";
  if (isset($_GET["${this_event}_in_progress"]) && ($_GET["${this_event}_in_progress"] == "1")) {
    $award_time = isset($_GET["{$this_event}_award_time"]) ? $_GET["{$this_event}_award_time"] : "";
    $last_start_time = isset($_GET["{$this_event}_last_start"]) ? $_GET["{$this_event}_last_start"] : "";

    $award_time_seconds = simple_time_to_seconds($award_time);
    $last_start_seconds = simple_time_to_seconds($last_start_time);
    // $error_string .= "<p> Using {$award_time_seconds} for awards and {$last_start_seconds} as last start.\n";

    if (($award_time_seconds != -1) && ($last_start_seconds != -1)) {
      //$error_string .= "<p>Event {$this_event} has default elapsed time seconds of {$default_elapsed_time_seconds}\n";
      $competitor_directory = get_competitor_directory($this_event, $key, "..");
      if (is_dir($competitor_directory)) {  // shouldn't really need to test for this...
        $competitor_list = scandir("{$competitor_directory}");
	$competitor_list = array_diff($competitor_list, array(".", ".."));

	foreach ($competitor_list as $possible_unfinished_competitor) {
	  // Skip anyone with a finish marker or who self reported (shouldn't happen at an NRE...)
	  // Skip anyone not using a SI stick - ineligible at most NREs
          if (file_exists("{$competitor_directory}/{$possible_unfinished_competitor}/controls_found/finish")) {
	    continue;
	  } 
          if (file_exists("{$competitor_directory}/{$possible_unfinished_competitor}/self_reported")) {
	    continue;
	  } 
          if (file_exists("{$competitor_directory}/{$possible_unfinished_competitor}/si_stick")) {
	    $unfinished_competitor_stick = file_get_contents("{$competitor_directory}/{$possible_unfinished_competitor}/si_stick");
	  }
	  else {
	    continue;
	  } 

	  // Truly have an unfinished competitor, but we only care if in a competitive class for the NRE
	  $unfinished_competitor_path = "{$competitor_directory}/{$possible_unfinished_competitor}";
	  $competitor_class = get_class_for_competitor($unfinished_competitor_path);
	  if ($competitor_class == "") {
	    continue;
	  }
	  $course = file_get_contents("{$unfinished_competitor_path}/course");
          $readable_course_name = ltrim($course, "0..9-");
	  $unfinished_competitor_name = file_get_contents("{$unfinished_competitor_path}/name");


	  // Should really check a few things here but I'm being lazy for the moment
	  // Should check if the name matches if the entry exists already
	  // Should check if the course matches if the entry exists already
	  // Should check if the competitor_class is appropriate for the course
	  // These are checked for true finishers, so any error we will find when the person ACTUALLY finishes, but would be better to know now
	  $hash_key = "{$competitor_class}:{$unfinished_competitor_stick}";
          if (!isset($results_by_class_and_stick[$hash_key])) {
            $results_by_class_and_stick[$hash_key] = array("name" => $unfinished_competitor_name,
                                                           "course" => $readable_course_name,
                                                           "competitive_class" => $competitor_class,
                                                           "stick" => $unfinished_competitor_stick,
                                                           "total_time" => 0,
						           "individual_times" => array(),
							   "num_finishes" => 0);
	  }

	  // See if the person has a known start time - if so, use it, otherwise use the time
	  // since the last start
	  $unfinished_competitor_time = $award_time_seconds - $last_start_seconds;
	  $start_time_output = $last_start_time;
	  if (file_exists("{$unfinished_competitor_path}/registration_info")) {
            $registration_info = parse_registration_info(file_get_contents("{$unfinished_competitor_path}/registration_info"));
	    if (isset($registration_info["start_time"])) {
              // There's a quirk that the start_time uses a . to separate hours vs minutes, as : is used as a separator in the registration info
              $start_time_for_parsing = str_replace(".", ":", $registration_info["start_time"]);
              $unfinished_competitor_start_seconds = simple_time_to_seconds($start_time_for_parsing);
	      // $error_string .= "<p>{$unfinished_competitor_name} has start of {$unfinished_competitor_start_seconds}\n";
	      if ($unfinished_competitor_start_seconds != -1) {
                $unfinished_competitor_time = $award_time_seconds - $unfinished_competitor_start_seconds;
		$start_time_output = $start_time_for_parsing;
	      }
	    }
	  }

          $results_by_class_and_stick[$hash_key]["total_time"] += $unfinished_competitor_time;
          $reported_elapsed_time = csv_formatted_time($unfinished_competitor_time);
          $results_by_class_and_stick[$hash_key]["num_finishes"]++;
	  $results_by_class_and_stick[$hash_key]["individual_times"][] = "<mark>{$reported_elapsed_time}</mark> ({$start_time_output})";
	  $results_by_class_and_stick[$hash_key]["provisional"] = 1;
	}
      }
    }
    else {
      $error_string .= "<p>Time limit \"{$award_time}\" or \"{$last_start_time}\" is incorrectly formatted, should be hh:mm, not adding unfinished competitors from that event.\n";
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
  else {
    $hash_key = "{$one_result["name"]}:{$one_result["stick"]}";
    $incomplete_entry_hash[$hash_key] = $one_result;
  }
}

// Format the results nicely for printing
$columns = array("Name", "Total time", "Course", "SI unit");
$individual_time_columns = array_map(function ($elt) { return ("Event {$elt}"); }, range(1, count($event_list)));
$header_elements = array_map(function ($elt) { return ("<th>{$elt}</th>"); }, array_merge($columns, $individual_time_columns));
$header_row = implode("", $header_elements);

#$output_string .= "<p>Total of " . count($results_by_class) . " unique classes\n";
// Get the order to print the results
// Again, this assumes that all the events being combined will print results in the
// same order - this really should be validated somehow
$custom_class_order = get_nre_class_display_order($event_list[0], $key);
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

    if (isset($this_result["provisional"])) {
      $output_string .= "<tr><td><del>{$this_result["name"]}</del><mark>Still on course</mark></td><td>{$printable_time}</td><td>{$this_result["course"]}</td><td>{$this_result["stick"]}</td> ";
    }
    else {
      $output_string .= "<tr><td>{$this_result["name"]}</td><td>{$printable_time}</td><td>{$this_result["course"]}</td><td>{$this_result["stick"]}</td> ";
    }
    $output_string .= $individual_times;
    $output_string .= "</tr>\n";
  }

  $output_string .= "</table><p><p>\n";
}

if (!$suppress_errors && (count($incomplete_entry_hash) > 0)) {
  $incomplete_entry_string .= "<p>Results that are not counted for various reasons\n";
  $incomplete_entry_string .= "<table border=1 style=\"border-collapse:collapse\">\n<tr>{$header_row}</tr>\n";
  $incomplete_entry_hash_keys = array_keys($incomplete_entry_hash);
  asort($incomplete_entry_hash_keys);
  $last_seen_name="";
  $last_seen_stick="";
  foreach ($incomplete_entry_hash_keys as $incomplete_entry_key) {
    $this_result = $incomplete_entry_hash[$incomplete_entry_key];
    $printable_time = csv_formatted_time($this_result["total_time"]);
    $individual_times = implode("", array_map(function ($elt) { return ("<td>{$elt}</td>"); }, $this_result["individual_times"]));

    $provisional_text = "";
    if (isset($this_result["provisional"])) {
      $provisional_text = "<strong> STILL ON COURSE</strong>";
    }
    if (isset($dnf_hash[$incomplete_entry_key])) {
      $incomplete_entry_string .= "<tr bgcolor=gray><td>{$this_result["name"]} - has dnf{$provisional_text}</td><td>{$printable_time}</td><td>{$this_result["course"]}</td><td>{$this_result["stick"]}</td> ";
      $incomplete_entry_string .= $individual_times;
      $incomplete_entry_string .= "</tr>\n";
    }
    else {
      // Most like cause of a true error is someone who switched sticks during the event, so flag these clearly
      if (($last_seen_name == $this_result["name"]) && ($last_seen_stick != $this_result["stick"])) {
        $incomplete_entry_string .= "<tr bgcolor=red><td>{$this_result["name"]}{$provisional_text}</td><td>{$printable_time}</td><td>{$this_result["course"]}</td><td>{$this_result["stick"]}</td> ";
      }
      else {
        $incomplete_entry_string .= "<tr><td>{$this_result["name"]}{$provisional_text}</td><td>{$printable_time}</td><td>{$this_result["course"]}</td><td>{$this_result["stick"]}</td> ";
      }
      $incomplete_entry_string .= $individual_times;
      $incomplete_entry_string .= "</tr>\n";
    }

    $last_seen_name = $this_result["name"];
    $last_seen_stick = $this_result["stick"];
  }
  $incomplete_entry_string .= "</table><p><p>\n";
}

echo get_web_page_header(true, false, false, true);

if (($error_string != "") && !$suppress_errors) {
  echo $error_string;
}

if (($incomplete_entry_string != "") && !$suppress_errors) {
  echo $incomplete_entry_string;
}

echo $output_string;

echo get_web_page_footer();
?>
