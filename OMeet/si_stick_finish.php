<?php

// Assume that this is already included - otherwise bad things will happen
//require "common_routines.php";

function record_finish_by_si_stick($event, $key, $si_results_string) {
  //echo "Looking at {$si_results_string}\n";
  $return_info = array();

  // Format is <si_stick>;<start_timestamp>,start:<start_timestamp>,finish:<finish_timestamp>,<control>:<control_timestamp>:...
  $result_pieces = explode(",", $si_results_string);
  if (count($result_pieces) < 3) {
    $return_info["error"] = "Malformatted results - no finish time found.";
    return ($return_info);
  }

  // Do some initial validation
  $identifier_pieces = explode(";", $result_pieces[0]);
  $si_stick = $identifier_pieces[0];
  $start_timestamp = $identifier_pieces[1];
  $start_pieces = explode(":", $result_pieces[1]);
  $finish_pieces = explode(":", $result_pieces[2]);
  //print_r($result_pieces);
  if (($start_pieces[0] != "start") || ($finish_pieces[0] != "finish") || ($start_pieces[1] != $start_timestamp) || ($finish_pieces[1] <= $start_timestamp)) {
    $return_info["error"] = "Malformatted results - start and finish don't make sense.";
    return ($return_info);
  }

  
  $return_info["finish_time"] = $finish_pieces[1];
  $return_info["si_stick"] = $si_stick;

  // Find the competitor with this si_stick
  // Try the optimized stick lookup first
  $found_competitor = False;
  $competitor = get_stick_xlation($event, $key, $si_stick);
  if ($competitor != "") {
    $found_competitor = True;
    clear_stick_xlation($event, $key, $si_stick);  // Clear the xlation entry, no longer needed
  }
  else {
    // we need to go the slow route and look through all the competitors
    $competitor_directory = get_competitor_directory($event, $key, ".."); 
    $competitor_list = scandir("{$competitor_directory}", SCANDIR_SORT_DESCENDING);
    $competitor_list = array_diff($competitor_list, array(".", ".."));

    foreach ($competitor_list as $competitor) {
      if (file_exists("{$competitor_directory}/{$competitor}/si_stick")) {
        $competitor_stick = file_get_contents("{$competitor_directory}/{$competitor}/si_stick");
        if ($competitor_stick == $si_stick) {
          if (!file_exists("{$competitor_directory}/{$competitor}/controls_found/start")) {
            // We found the correct entry!
            $found_competitor = True;
            break;
          }
          else {
            $competitor_start = file_get_contents("{$competitor_directory}/{$competitor}/controls_found/start");
            if ($competitor_start != $start_timestamp) {
              // This si stick must have been reused and this is the earlier competitor, just move on
            }
            else {
              // We've processed this result already, we're done
              $return_info["competitor_id"] = $competitor;
              $return_info["course"] = file_get_contents("{$competitor_directory}/{$competitor}/course");
              return($return_info);
            }
          }
        }
      }
    }
  }

  if ($found_competitor) {
    $save_error = validate_and_save_results($event, $key, $competitor, $si_stick, $start_pieces, $finish_pieces, $result_pieces);
    if ($save_error == "") {
      $competitor_directory = get_competitor_directory($event, $key); 
      $return_info["competitor_id"] = $competitor;
      $return_info["course"] = file_get_contents("{$competitor_directory}/{$competitor}/course");
    }
    else {
      $return_info["error"] = $save_error;
    }
  }
  else {
    $return_info["error"] = "No registered competitor found with SI unit \"{$si_stick}\"";
  }

  return($return_info);
}

function validate_and_save_results($event, $key, $competitor, $si_stick, $start_pieces, $finish_pieces, $result_pieces) {
  global $TYPE_FIELD, $SCORE_O_COURSE, $COMBO_COURSE, $COMBO_COURSE_CONTROL_OPTIONS;

  $competitor_path = get_competitor_path($competitor, $event, $key, "..");
  if (!file_exists($competitor_path) || file_exists("{$competitor_path}/controls_found/start") || ($si_stick != file_get_contents("{$competitor_path}/si_stick"))) {
    // Houston, we have a problem
    return ("Competitor {$competitor} does not seem to match SI unit {$si_stick}");
  }

  $course = file_get_contents("{$competitor_path}/course");
  $courses_path = get_courses_path($event, $key, "..");
  $course_properties = get_course_properties("{$courses_path}/{$course}");
  $is_score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $is_combo_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $COMBO_COURSE));
  if ($is_combo_course) {
    // If the person is registered on a combo course, then it must be enabled for auto-placement.  Try and see
    // which course (of the possibilities) was actually run
    $course_options = explode("#", $course_properties[$COMBO_COURSE_CONTROL_OPTIONS]);
    // Format the controls found by the competitor as just a comma separated list of controls, in order, with no timestamps
    $controls_found_by_competitor_string = implode(",", array_map(function ($elt) { return (explode(":", $elt) [0]); }, array_slice($result_pieces, 3)));

    // See if the controls found match any of the options
    // Format of each entry is <course_full_name>;<control>,<control>,<control>...
    $course_run_list = array_filter($course_options, function ($elt) use ($controls_found_by_competitor_string) { return ($controls_found_by_competitor_string == explode(";", $elt)[1]); } );
    $num_courses_found = count($course_run_list);
    if ($num_courses_found == 0) {
      // No exact match - look for a match by checking manually
      // Set a default so we have something if no better match is found
      $course_run = explode(";", $course_options[0])[0];
      $found_matching_course = false;
      $controls_found_by_competitor = array_map(function ($elt) { return (explode(":", $elt) [0]); }, array_slice($result_pieces, 3));
      $num_controls_found_by_competitor = count($controls_found_by_competitor);
      foreach ($course_options as $this_course_option) {
        $this_course_info = explode(";", $this_course_option);
	$controls_on_this_course_option = explode(",", $this_course_info[1]);
	$num_controls_on_this_course_option = count($controls_on_this_course_option);
	// If the competitor found fewer controls than are on this course, no point in checking further
        if ($num_controls_found_by_competitor >= $num_controls_on_this_course_option) {
	  $course_control_index = 0;
	  foreach ($controls_found_by_competitor as $this_found_control) {
            if ($this_found_control == $controls_on_this_course_option[$course_control_index]) {
              // Found the right control, in the right order, move on to the next
	      $course_control_index++;
	      // If we've found all the controls, we're good!  Doesn't matter if we have extra controls we found, and no need to check further
	      if ($course_control_index >= $num_controls_on_this_course_option) {
                $course_run = $this_course_info[0];
                $found_matching_course = true;
		break;
	      }
	    }
	  }
	  if ($found_matching_course) {
            break;
	  }
        }
      }
    }
    else {
      // Ideally in this case there is exactly one course which matched the controls punched, so get the name of that course.
      // It is possible that multiple courses matched, in which case just choose the first - this should really never happen but
      // better to have thought about it and handle it somehow
      $course_run = explode(";", array_values($course_run_list)[0] ) [0];
    }

    // Update the information with the course found
    $course = $course_run;
    file_put_contents("{$competitor_path}/course", $course_run);

  }

  $controls_info = read_controls("{$courses_path}/{$course}/controls.txt");
  $controls_on_course = array_map(function ($elt) { return($elt[0]); }, $controls_info);


  // Skip the si_stick entry, the start entry, and the finish entry
  // Format of $result_pieces is <si_stick>;<start_timestamp>,start:<start_timestamp>,finish:<finish_timestamp>,<control>:<control_timestamp>:...
  // already split by ,
  $extra_controls_string = "";
  $next_control_number = 0;
  for ($i = 3; $i < count($result_pieces); $i++) {
    $control_pieces = explode(":", $result_pieces[$i]);
    $control_is_valid = false;

    if ($is_score_course) {
       if (in_array($control_pieces[0], $controls_on_course)) {
         $control_is_valid = true;
       }
    }
    else if ($controls_on_course[$next_control_number] == $control_pieces[0]) {
      $control_is_valid = true;
      $next_control_number++;
    }

    if ($control_is_valid) {
      // Save the control in the expected format
      $time_6_digits = sprintf("%06d", $control_pieces[1]);
      file_put_contents("{$competitor_path}/controls_found/{$time_6_digits},{$control_pieces[0]}", "");
    }
    else {
      $time_6_digits = sprintf("%06d", $control_pieces[1]);
      $extra_controls_string .= "{$time_6_digits},{$control_pieces[0]}\n";
    }
  }

  if ($extra_controls_string != "") {
    file_put_contents("{$competitor_path}/extra", $extra_controls_string);
  }

  // Do this last so that it can be used to determine if the results were saved properly
  // Do NOT save the finish time, as the finish_course will do that
  if (file_exists("{$competitor_path}/mass_si_stick_start")) {
    if ($start_pieces[1] != 0) {
      // The person was mass started, but has a start punch - assume that they started the course early
      // and use their existing start punch.  If this is wrong it can be fixed later.
      file_put_contents("{$competitor_path}/controls_found/start", $start_pieces[1]);
    }
    else {
      file_put_contents("{$competitor_path}/controls_found/start", file_get_contents("{$competitor_path}/mass_si_stick_start"));
    }
  }
  else {
    file_put_contents("{$competitor_path}/controls_found/start", $start_pieces[1]);
  }

  return("");
}

?>
