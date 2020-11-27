<?php
// Must already have common_routines.php included


function get_splits_output($competitor_id, $event, $key, $final_results_line) {
  global $TYPE_FIELD, $SCORE_O_COURSE, $LIMIT_FIELD, $PENALTY_FIELD, $MAX_SCORE_FIELD;

  $competitor_path = get_competitor_path($competitor_id, $event, $key, ".."); 
  
  if (!is_dir($competitor_path)) {
    return("No splits available for \"{$competitor_id}\" for {$event} and {$key}, please check that this usage is authorized.\n");
  }
  
  $result_pieces = explode(",", $final_results_line);
  $competitor_name = file_get_contents("{$competitor_path}/name");
  $controls_found_path = "{$competitor_path}/controls_found";
  $course = file_get_contents("{$competitor_path}/course");
  
  $courses_path = get_courses_path($event, $key, "..");
  $control_list = read_controls("{$courses_path}/{$course}/controls.txt");
  $controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                        array_map(function ($element) { return $element[1]; }, $control_list));
  $course_properties = get_course_properties("{$courses_path}/{$course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  //echo "Controls on the ${course} course.<br>\n";
  // print_r($control_list);
  $error_string = "";
  
  if (!file_exists("{$controls_found_path}/start")) {
    $error_string = "<p>Course not started\n";
  }
  
  $start_time = file_get_contents("{$controls_found_path}/start");
  
  // See how many controls have been completed
  $controls_done = scandir("./{$controls_found_path}");
  $controls_done = array_diff($controls_done, array(".", "..", "start", "finish")); // Remove the annoying . and .. entries
  $number_controls_found = count($controls_done);
  
  //echo "Controls done is: <p>";
  //print_r($controls_done);
  
  $split_times = array();
  $cumulative_time = array();
  $controls_found = array();
  $prior_control_time = $start_time;
  $total_score = 0;
  $i = 0;
  foreach ($controls_done as $entry) {
    $control_info_array = explode(",", $entry);  // format is <time>,<control_id>
    $controls_found[$i] = $control_info_array[1];
    $time_at_control[$i] = $control_info_array[0];
    $split_times[$i] = $time_at_control[$i] - $prior_control_time;
    $cumulative_time[$i] = $time_at_control[$i] - $start_time;
    $prior_control_time = $time_at_control[$i];
    $i++;
  }
  $finish_time = file_get_contents("{$controls_found_path}/finish");
  $time_at_control[$i] = $finish_time;
  $split_times[$i] = $time_at_control[$i] - $prior_control_time;
  $cumulative_time[$i] = $time_at_control[$i] - $start_time;
  
  $extra_controls_string="";
  if (file_exists("{$competitor_path}/extra")) {
    $extra_controls = explode("\n", file_get_contents("{$competitor_path}/extra"));
    $extra_controls_string = "<tr></tr><tr><td colspan=4>Wrong controls punched (not on course)</td></tr>\n";
    foreach ($extra_controls as $extra_one) {
      if ($extra_one != "") {
        $extra_control_info = explode(",", $extra_one);  // Format of each entry is <time>,<control_id>
        $extra_controls_string .= "<tr><td></td><td>{$extra_control_info[1]}</td><td></td><td></td><td>" . strftime("%T", $extra_control_info[0]) . "</td>\n";
      }
    }
  }
  
  $table_string = "";
  $table_string .= "<p class=\"title\">Splits for ${competitor_name} on " . ltrim($course, "0..9-") . "\n";
  $table_string .= "<table border=1><tr><th>Control Num</th><th>Control Id</th><th>Split Time</th><th>Cumulative Time</th><th>Time of Day</th></tr>\n";
  $table_string .= "<tr><td>Start</td><td></td><td></td><td></td><td>" . strftime("%T (%a - %d)", $start_time) . "</td></tr>\n";
  $controls_found_list = array();  // De-dup controls found if on a scoreO
  for ($i = 0; $i < $number_controls_found; $i++){
    if ($score_course) {
      $control_found = $controls_found[$i];
      $control_points = $controls_points_hash[$control_found];
      if (!isset($controls_found_list[$control_found])) {
        $controls_found_list[$control_found] = 1;
        $control_string_for_table = "{$controls_found[$i]} ({$control_points} pts)";
        $total_score += $control_points;
      }
      else {
        $control_string_for_table = "{$controls_found[$i]} (<strike>{$control_points} pts</strike>)";
      }
    }
    else {
      $control_string_for_table = $controls_found[$i];
    }
    $table_string .= "<tr><td>" . ($i + 1) . "</td><td>{$control_string_for_table}</td><td>" . formatted_time($split_times[$i]) . "</td>" .
                                             "<td>" . formatted_time($cumulative_time[$i]) . "</td><td>" . strftime("%T", $time_at_control[$i]) . "</td></tr>\n";
  }
  $table_string .= "<tr><td>Finish</td><td></td><td>" . formatted_time($split_times[$i]) . "</td>" .
                                           "<td>" . formatted_time($cumulative_time[$i]) . "</td>" .
                                           "<td>" . strftime("%T (%a - %d)", $time_at_control[$i]) . "</td></tr>\n{$extra_controls_string}\n</table>\n";
  
  
  $splits_string = "";
  if ($error_string != "") {
    $splits_string .= "<p>ERROR: ${error_string}\n";
  }
  
  $splits_string .= $table_string;
  $splits_string .= "<p>Total Time: " . formatted_time($finish_time - $start_time) . "\n";
  if ($score_course) {
    $splits_string .= "<p>Final Score: " . ($course_properties[$MAX_SCORE_FIELD] - $result_pieces[0]) . "\n";
    if (($course_properties[$LIMIT_FIELD] > 0) && ($result_pieces[1] > $course_properties[$LIMIT_FIELD])) {
      $time_over = $result_pieces[1] - $course_properties[$LIMIT_FIELD];
      $minutes_over = floor(($time_over + 59) / 60);
      $penalty = $minutes_over * $course_properties[$PENALTY_FIELD];
  
      $score_penalty_msg = "<p>Exceeded time limit of " . formatted_time($course_properties[$LIMIT_FIELD]) . " by " . formatted_time($time_over) . "\n" .
                           "<p>Penalty is {$course_properties[$PENALTY_FIELD]} pts/minute, total penalty of {$penalty} points.\n" .
                           "<p>Control score was $total_score -> " . ($total_score - $penalty) . " after penalty.\n";
      $splits_string .= $score_penalty_msg;
    }
  }
  if (file_exists("${competitor_path}/dnf")) {
    $splits_string .= "<p>DNF\n";
  }
  
  return $splits_string;
}


function get_splits_dnf($competitor, $event, $key) {

  $error_string = "";
  $output_string = "";

  $courses_path = get_courses_path($event, $key);
  if (!file_exists($courses_path)) {
    return (array("error" => "<p>ERROR: No such event found {$event} (or bad location key {$key}).\n"));
  }
  
  $competitor_path = get_competitor_path($competitor, $event, $key);
  if (!is_dir($competitor_path)) {
    return (array("error" => "<p>ERROR: No such competitor found {$competitor} (possibly already removed or edited?).\n"));
  }
  
  $splits_array = get_splits_as_array($competitor, $event, $key, true);
  
  $competitor_name = file_get_contents("{$competitor_path}/name");
  
  $course = file_get_contents("{$competitor_path}/course");
  $control_list = read_controls("{$courses_path}/{$course}/controls.txt");
  
  $course_properties = get_course_properties("{$courses_path}/{$course}");
  $number_controls = count($control_list);
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  if ($score_course) {
    $error_string = "<p>Score course marked as DNF - should not be possible";
  }
  
  
  // For each of the controls on the course, create a hash from the control number
  // to its possible offset into the course.  Normally each control would only have one
  // possible offset, except for things like butterfly courses, motalas, relays, etc.
  $controls_hash = array();
  for ($control_number = 0; $control_number < count($control_list); $control_number++) {
    $this_control_id = $control_list[$control_number][0];
    if (!isset($controls_hash[$this_control_id])) {
      $controls_hash[$this_control_id] = array($control_number);
    }
    else {
      $controls_hash[$this_control_id][] = $control_number;
    }
  }
  
  $controls_found = $splits_array["controls"];
  // What control ids appear in the list of controls to find but not the list of controls actually found?
  $missed_control_ids = array_diff(array_keys($controls_hash), array_map(function ($elt) { return ($elt["control_id"]); }, $controls_found));
  
  // For the missing controls, get their position within the course (i.e. control 301 wasn't punched, it is the 5th control)
  $missed_control_positions_array = array_map(function ($elt) use ($controls_hash) { return ($controls_hash[$elt]); }, $missed_control_ids);
  
  // Because a missing control on a butterfly course may appear in multiple places, flatten the list into a hash
  // so entry 5 in the $missed_controls_positions_hash means that the control at position 5 may have been missed
  $missed_controls_positions_hash = array();
  foreach ($missed_control_positions_array as $possible_missed_positions) {
    foreach ($possible_missed_positions as $missed_position) {
      $missed_controls_positions_hash[$missed_position] = 1;
    }
  }
  
    
  $output_string = "<p class=\"title\">Splits for {$competitor_name} on " . ltrim($course, "0..9-") . ($score_course ? " (ScoreO)" : "") . "\n";
  $output_string .= "<p><table><tr><th>Control Num</th><th>Control Id</th><th>Split Time</th><th>Cumulative Time</th><th>Time of day</th></tr>\n";
  $output_string .= "<tr><td>Start</td><td></td><td></td><td></td><td>" . strftime("%T (%a - %d)", $splits_array["start"]) . "</td></tr>\n";
  
  $control_num_on_course = 0;
  $control_unique_counter = 0;
  foreach ($controls_found as $this_control) {
    $control_unique_counter++;
    $control_id = $this_control["control_id"];
  
    // The runner may have missed this control - let it be edited
    while (isset($missed_controls_positions_hash[$control_num_on_course]) && ($control_num_on_course < count($control_list))) {
      $output_string .= "<tr><td>" . ($control_num_on_course + 1) . "</td><td>" . $control_list[$control_num_on_course][0] . "</td>";
      $output_string .="<td>-</td><td>-</td><td>-</td></tr>\n"; // No time at the control - it wasn't visited
  
      $control_unique_counter++;
  
      // Only print the editing position once - there may be multiple wrong controls in a row
      unset($missed_controls_positions_hash[$control_num_on_course]);
      $control_num_on_course++;
    }
  
    // is the punched control on the course at all?
    if (isset($controls_hash[$control_id])) {
      // For a butterfly course, the control may appear at multiple places - show them all
      $output_string .= "<tr><td>" . implode(",", array_map(function ($elt) { return ($elt + 1); }, $controls_hash[$control_id])) .
                                                                                      "</td><td>" . $control_id . "</td>";
      $output_string .= "<td>" . formatted_time($this_control["split_time"]) . "</td>\n";
      $output_string .= "<td>" . formatted_time($this_control["cumulative_time"]) . "</td>\n";
      $output_string .="<td>" . strftime("%T", $this_control["raw_time"]) . "</td></tr>\n";
  
      $control_num_on_course++;
   }
   else {
      $output_string .= "<tr><td>-</td><td>" . $control_id . "</td>";
      $output_string .= "<td>" . formatted_time($this_control["split_time"]) . "</td>\n";
      $output_string .= "<td>" . formatted_time($this_control["cumulative_time"]) . "</td>\n";
      $output_string .="<td>" . strftime("%T", $this_control["raw_time"]) . "</td></tr>\n";
    }
  }
  
  // Handle unpunched controls at the end
  for ( ; $control_num_on_course < count($control_list); $control_num_on_course++) {
    $control_unique_counter++;
    if (isset($missed_controls_positions_hash[$control_num_on_course])) {
      $output_string .= "<tr><td>" . ($control_num_on_course + 1) . "</td><td>" . $control_list[$control_num_on_course][0] . "</td>";
      $output_string .="<td>-</td><td>-</td><td>-</td></tr>\n"; // No time at the control - it wasn't visited
      unset($missed_controls_positions_hash[$control_num_on_course]);
    }
  }
  
  $output_string .= "<tr><td>Finish</td><td></td><td></td><td></td><td>" . strftime("%T (%a - %d)", $splits_array["finish"]) . "</td></tr>\n";
  $output_string .= "</table>\n";
    
  $finish_time = $splits_array["finish"] - $splits_array["start"];
  $output_string .= "<p>Total Time: " . formatted_time($finish_time) . "\n";

  return (array("error" => $error_string, "output" => $output_string));
}


function get_splits_as_array($competitor_id, $event, $key, $include_all = 'false') {

  $splits_array = array();
  $control_times_array = array();
  $competitor_path = get_competitor_path($competitor_id, $event, $key, ".."); 
  
  if (!is_dir($competitor_path)) {
    return($splits_array());
  }
  
  $controls_found_path = "{$competitor_path}/controls_found";
  $course = file_get_contents("{$competitor_path}/course");
  
  $courses_path = get_courses_path($event, $key, "..");
  $control_list = read_controls("{$courses_path}/{$course}/controls.txt");
  $controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                        array_map(function ($element) { return $element[1]; }, $control_list));
  $course_properties = get_course_properties("{$courses_path}/{$course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  //echo "Controls on the ${course} course.<br>\n";
  // print_r($control_list);
  $error_string = "";
  
  if (file_exists("{$controls_found_path}/start")) {
    $start_time = file_get_contents("{$controls_found_path}/start");
    $splits_array["start"] = $start_time;
  }
  else {
    $start_time = 0;
    $splits_array["start"] = "-";
  }
  
  
  // See how many controls have been completed
  $controls_done = scandir("./{$controls_found_path}");
  $controls_done = array_diff($controls_done, array(".", "..", "start", "finish")); // Remove the annoying . and .. entries
  $number_controls_found = count($controls_done);

  if ($include_all && file_exists("{$competitor_path}/extra")) {
    $extra_controls = file("{$competitor_path}/extra", FILE_IGNORE_NEW_LINES);
    $controls_done = array_merge($controls_done, $extra_controls);
    sort($controls_done);
  }
  
  //echo "Controls done is: <p>";
  //print_r($controls_done);
  
  $split_times = array();
  $cumulative_time = array();
  $controls_found = array();
  $prior_control_time = $start_time;
  $total_score = 0;
  $i = 0;
  foreach ($controls_done as $entry) {
    $control_info_array = explode(",", $entry);  // format is <time>,<control_id>
    $controls_found[$i] = $control_info_array[1];
    $time_at_control[$i] = $control_info_array[0];
    $split_times[$i] = $time_at_control[$i] - $prior_control_time;
    $cumulative_time[$i] = $time_at_control[$i] - $start_time;

    $control_entry = array();
    $control_entry["control_id"] = $control_info_array[1];
    $control_entry["raw_time"] = $control_info_array[0];
    $control_entry["split_time"] = $split_times[$i];
    $control_entry["cumulative_time"] = $cumulative_time[$i];
    $control_times_array[] = $control_entry;

    $prior_control_time = $time_at_control[$i];
    $i++;
  }
  if (file_exists("{$controls_found_path}/finish")) {
    $finish_time = file_get_contents("{$controls_found_path}/finish");
  }
  else {
    $finish_time = -1;
  }

  $splits_array["finish"] = $finish_time;
  $splits_array["controls"] = $control_times_array;
  
  return $splits_array;
}
?>
