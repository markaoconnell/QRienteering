<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

set_page_title("Edit Single Orienteering Result");

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$competitor = $_GET["competitor"];
$allow_editing = isset($_GET["allow_editing"]);

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, cannot edit punches.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, cannot edit punches.\n");
}

$courses_path = get_courses_path($event, $key);
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

$competitor_path = get_competitor_path($competitor, $event, $key);
if (!is_dir($competitor_path)) {
  error_and_exit("<p>ERROR: No such competitor found {$competitor} (possibly already removed or edited?).\n");
}

if (file_exists("{$competitor_path}/self_reported")) {
  error_and_exit("<p>ERROR: Self reported result, no splits available to edit.\n");
}

$using_si_timing = file_exists("{$competitor_path}/si_stick");
set_timezone($key);

$splits_array = get_splits_as_array($competitor, $event, $key, true);

$start_time = $splits_array["start"];
if ($start_time == "-") {
  $start_time = 0;
}
if (isset($_GET["new_start_time"])) {
  $entered_start_time = trim($_GET["new_start_time"]);
  if (preg_match("/^-[0-9]+$/", $entered_start_time)) {
    if (!isset($splits_array["controls"][0])) {
      error_and_exit("<p>ERROR: Cannot use a negative time \"{$entered_start_time}\" when no controls have been punched.\n");
    }

    // Negative values are relative to the first control punched    
    $new_start_time = $splits_array["controls"][0]["raw_time"] + $entered_start_time;
    if ($new_start_time < 0) {
      error_and_exit("<p>ERROR: Start time adjustment \"{$entered_start_time}\" too large, start time would be negative.\n<br>" .
                     "Maximum negative adjustment is \"{$splits_array["controls"][0]["raw_time"]}\".\n");
    }
  }
  else if (preg_match("/^[0-9]+$/", $entered_start_time)) {
    // Absolute time in seconds
    $new_start_time = $entered_start_time;
  }
  else if (preg_match("/^[0-9]+:[0-9]+:[0-9]+$/", $entered_start_time)) {
//    if ($start_time == 0) {
//        error_and_exit("<p>ERROR: \"{$entered_start_time}\" should be just a timestamp (positive or negative) when start was not punched.\n");
//    }

    $new_start_hms = explode(":", $_GET["new_start_time"]);
    if (($new_start_hms[0] < 0) || ($new_start_hms[0] > 23) || ($new_start_hms[1] < 0) || ($new_start_hms[1] > 59)
                                                            || ($new_start_hms[2] < 0) || ($new_start_hms[2] > 59)) {
      error_and_exit("<p>ERROR: \"{$entered_start_time}\" is malformatted, should be hh:mm:ss, hour between 0 and 23, minute and second between 0 and 60.\n");
    }

    // For si timing, don't both with timezones - just use the time raw
    if ($using_si_timing) {
      $new_start_time = (3600 * $new_start_hms[0]) + (60 * $new_start_hms[1]) + $new_start_hms[2];
    }
    else {
      $localtime_array = localtime($start_time, true);
      $new_start_time = mktime($new_start_hms[0], $new_start_hms[1], $new_start_hms[2],
	    $localtime_array["tm_mon"] + 1, $localtime_array["tm_mday"], $localtime_array["tm_year"] + 1900);
      }
  }
  else {
    error_and_exit("<p>ERROR: Cannot parse start time \"{$entered_start_time}\", re-read format instructions for the time.\n");
  }

  $start_time_adjustment = $new_start_time - $start_time;
}
else {
  $start_time_adjustment = 0;
}

$unpunched_qr_start = ((($start_time + $start_time_adjustment) == 0) && !$using_si_timing);

$competitor_name = file_get_contents("{$competitor_path}/name");

$course = file_get_contents("{$competitor_path}/course");
$control_list = read_controls("{$courses_path}/{$course}/controls.txt");

$course_properties = get_course_properties("{$courses_path}/{$course}");
$number_controls = count($control_list);
$score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
$max_score = 0;
if ($score_course) {
  $max_score = $course_properties[$MAX_SCORE_FIELD];
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
// so an entry in the $missed_controls_positions_hash means that the control at position 5 may have been missed
$missed_controls_positions_hash = array();
foreach ($missed_control_positions_array as $possible_missed_positions) {
  foreach ($possible_missed_positions as $missed_position) {
    $missed_controls_positions_hash[$missed_position] = 1;
  }
}

$course_properties = get_course_properties("{$courses_path}/{$course}");
$score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
$error_string = "";
  
$output_string = "<p>Punches for {$competitor_name} on " . ltrim($course, "0..9-") . ($score_course ? " (ScoreO)" : "") . "\n";
if ($allow_editing) {
  $output_string .= "<p><form action=../OMeetRegistration/self_report_1.php>\n";
  $output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
  $output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
  $output_string .= "<input type=hidden name=competitor value=\"{$competitor}\">\n";
  $output_string .= "<input type=hidden name=course value=\"{$course}\">\n";
  $output_string .= "<input type=submit value=\"Enter Elapsed Time (no splits)\">\n";
  $output_string .= "</form>\n";
  $output_string .= "<br><br>\n";
  if ($unpunched_qr_start) {
    $output_string .= "Current start time: Not Yet Started\n";
    $allow_editing = false;
  }
  else {
    $output_string .= "<p><form action=./edit_punches.php>\n";
    $output_string .= "Current start time: <input type=text name=new_start_time value=\"" . format_split_time($start_time + $start_time_adjustment, $using_si_timing, false) . "\">\n";
    $output_string .= "<br>Enter time as hh:mm:ss or value, where value is an absolute time in seconds (if negative, then relative time to first control).<br>\n";
    $output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
    $output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
    $output_string .= "<input type=hidden name=competitor value=\"{$competitor}\">\n";
    $output_string .= "<input type=hidden name=allow_editing value=\"1\">\n";
    $output_string .= "<input type=submit value=\"Edit start time\">\n";
    $output_string .= "</form>\n";
    $output_string .= "<p>Instructions:\n<ul>\n";
    $output_string .= "<li>Enter the time (in seconds) for a control that is listed to add that control as punched.\n";
    $output_string .= "<li>Enter the time preceded by a + (e.g. \"+123\") to add that many seconds to the prior time.\n";
    $output_string .= "<li>Enter a 0 for the time to remove that control.\n";
    $output_string .= "</ul>\n";
    $output_string .= "<form action=./submit_edited_punches.php>\n";
    $output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
    $output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
    $output_string .= "<input type=hidden name=competitor value=\"{$competitor}\">\n";
    $output_string .= "<input type=hidden name=start_time_adjustment value=\"{$start_time_adjustment}\">\n";
  }
}
else {
  if ($unpunched_qr_start) {
    $output_string .= "<p>Current start time: Not Yet Started\n";
  }
  else {
    $output_string .= "<p>Current start time: " . format_split_time($start_time + $start_time_adjustment, $using_si_timing, false) . "\n";
  }
}

$output_string .= "<p><table><tr><th>&nbsp&nbsp#&nbsp&nbsp</th><th>Control</th><th>Actual time</th><th>Relative time<br>(seconds)</th></tr>\n";

$control_num_on_course = 0;
$control_unique_counter = 0;
foreach ($controls_found as $this_control) {
  $control_unique_counter++;
  $control_id = $this_control["control_id"];

  // The runner may have missed this control - let it be edited
  while (isset($missed_controls_positions_hash[$control_num_on_course]) && ($control_num_on_course < count($control_list))) {
    $output_string .= "<tr><td>" . ($control_num_on_course + 1) . "</td><td>" . $control_list[$control_num_on_course][0] . "</td>";
    $output_string .="<td>-</td>";  // No time at the control - it wasn't visited
    if ($allow_editing) {
      $output_string .= "<td><input type=text name=\"Control-{$control_list[$control_num_on_course][0]}-{$control_unique_counter}\" value=\"0\"></td></tr>\n";
    }
    else {
      $output_string .= "<td>-</td></tr>\n";
    }

    $control_unique_counter++;

    // Only print the editing position once - there may be multiple wrong controls in a row
    unset($missed_controls_positions_hash[$control_num_on_course]);
    $control_num_on_course++;
  }

  // Use the raw_time here (raw_time - $start_time - $start_time_adjustment) because the cumulative time
  // is already adjusted for the forgiven time for an untimed leg - and adjusting the cumulative_time for the
  // start_time_adjustment confuses things when we recalculate the untimed leg again
  //
  // is the punched control on the course at all?
  if (isset($controls_hash[$control_id])) {
    $output_string .= "<tr><td>" . implode(",", array_map(function ($elt) { return ($elt + 1); }, $controls_hash[$control_id])) . "</td><td>" . $control_id . "</td>";
    $output_string .="<td>" . format_split_time($this_control["raw_time"], $using_si_timing, false) . "</td>";
    if ($allow_editing) {
      $output_string .= "<td><input type=text name=\"Control-{$control_id}-{$control_unique_counter}\" value=\"" .
                                                      ($this_control["raw_time"] - $start_time - $start_time_adjustment) . "\"></td></tr>\n";
    }
    else {
      $output_string .= "<td>" . ($this_control["raw_time"] - $start_time - $start_time_adjustment) . "</td></tr>\n";
    }

    $control_num_on_course++;
 }
 else {
    $output_string .= "<tr><td>-</td><td>" . $control_id . "</td>";
    $output_string .="<td>" . format_split_time($this_control["raw_time"], $using_si_timing, false) . "</td>";
    if ($allow_editing) {
      $output_string .= "<td><input type=text name=\"Control-{$control_id}-{$control_unique_counter}\" value=\"" .
                                                     ($this_control["raw_time"] - $start_time - $start_time_adjustment) . "\"></td></tr>\n";
    }
    else {
      $output_string .= "<td>" . ($this_control["raw_time"] - $start_time - $start_time_adjustment) . "</td></tr>\n";
    }
  }
}

// Handle unpunched controls at the end
for ( ; $control_num_on_course < count($control_list); $control_num_on_course++) {
  $control_unique_counter++;
  if (isset($missed_controls_positions_hash[$control_num_on_course])) {
    $output_string .= "<tr><td>" . ($control_num_on_course + 1) . "</td><td>" . $control_list[$control_num_on_course][0] . "</td>";
    $output_string .="<td>-</td>";
    if ($allow_editing) {
      $output_string .= "<td><input type=text name=\"Control-{$control_list[$control_num_on_course][0]}-{$control_unique_counter}\" value=\"0\"></td></tr>\n";
    }
    else {
      $output_string .= "<td>-</td></tr>\n";
    }
  unset($missed_controls_positions_hash[$control_num_on_course]);
  }
}

$output_string .= "</table>\n";
  
if ($splits_array["finish"] != -1) {
  $finish_time = $splits_array["finish"] - $start_time - $start_time_adjustment;
  if ($allow_editing) {
    $output_string .= "<p>Finish at <input type=text name=\"finish_offset\" value=\"{$finish_time}\"> (seconds).\n";
  }
  else {
    $output_string .= "<p>Finish at $finish_time (seconds).\n";
  }
}
else {
  if ($allow_editing) {
    $output_string .= "<p>Finish at <input type=text name=\"finish_offset\" value=\"please specify\"> (seconds).\n";
  }
  else {
    $output_string .= "<p>Not yet finished.\n";
  }
}

if ($allow_editing) {
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  $course_list = array_filter($course_list, function ($elt) use ($courses_path) { return (!file_exists("{$courses_path}/{$elt}/removed") &&
	                                                                                  !file_exists("{$courses_path}/{$elt}/no_registrations")); });

  $change_course_string = implode("\n", array_map(function ($elt) { return "<li><input type=radio name=new_course value=\"{$elt}\"> " . ltrim($elt, "0..9-"); }, $course_list));
  $output_string .= "<p>Add additional control - format is control_id, time (seconds since start): <input type=text name=\"additional\">\n";
  $output_string .= "<p><p><p>Change name (leave blank to keep original name): <input type=text name=\"new_name\">\n";
  $output_string .= "<p>Change course (leave all unchecked to keep original course): <ul>{$change_course_string}</ul>\n";
  $output_string .= "<p><input type=submit value=\"Submit changes\">\n";
  $output_string .= "</form>\n";
}

echo get_web_page_header(true, true, false);

echo $output_string;

echo "<p><p><a href=\"./competitor_info.php?key={$key}&event={$event}\">Back to meet director information page</a>\n";

echo get_web_page_footer();
?>
