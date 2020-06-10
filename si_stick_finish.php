<?php

// Assume that this is already included - otherwise bad things will happen
//require "common_routines.php";

function record_finish_by_si_stick($event, $si_results_string) {
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
  $competitor_directory = "./{$event}/Competitors";
  $competitor_list = scandir("{$competitor_directory}");
  $competitor_list = array_diff($competitor_list, array(".", ".."));

  foreach ($competitor_list as $competitor) {
    if (file_exists("{$competitor_directory}/{$competitor}/si_stick")) {
      $competitor_stick = file_get_contents("{$competitor_directory}/{$competitor}/si_stick");
      if ($competitor_stick == $si_stick) {
        if (!file_exists("{$competitor_directory}/{$competitor}/controls_found/start")) {
          $save_error = validate_and_save_results($event, $competitor, $si_stick, $start_pieces, $finish_pieces, $result_pieces);
          if ($save_error == "") {
            $return_info["competitor_id"] = $competitor;
            $return_info["course"] = file_get_contents("{$competitor_directory}/{$competitor}/course");
          }
          else {
            $return_info["error"] = $save_error;
          }
          return ($return_info);
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

  $return_info["error"] = "No registered competitor found with si_stick \"{$si_stick}\"";
  return($return_info);
}

function validate_and_save_results($event, $competitor, $si_stick, $start_pieces, $finish_pieces, $result_pieces) {
  global $TYPE_FIELD, $SCORE_O_COURSE;

  $competitor_path = "./{$event}/Competitors/{$competitor}";
  if (!file_exists($competitor_path) || file_exists("{$competitor_path}/controls_found/start") || ($si_stick != file_get_contents("{$competitor_path}/si_stick"))) {
    // Houston, we have a problem
    return ("Competitor {$competitor} does not seem to match stick {$si_stick}");
  }

  $course = file_get_contents("{$competitor_path}/course");
  $controls_info = read_controls("./{$event}/Courses/{$course}/controls.txt");
  $controls_on_course = array_map(function ($elt) { return($elt[0]); }, $controls_info);
  $course_properties = get_course_properties("./{$event}/Courses/{$course}");
  $is_score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));


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
      file_put_contents("{$competitor_path}/controls_found/{$control_pieces[1]},{$control_pieces[0]}", "");
    }
    else {
      $extra_controls_string .= "{$control_pieces[1]},{$control_pieces[0]}\n";
    }
  }

  if ($extra_controls_string != "") {
    file_put_contents("{$competitor_path}/extra", $extra_controls_string);
  }

  // Do this last so that it can be used to determine if the results were saved properly
  // Do NOT save the finish time, as the finish_course will do that
  file_put_contents("{$competitor_path}/controls_found/start", $start_pieces[1]);

  return("");
}

?>
