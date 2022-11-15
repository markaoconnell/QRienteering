<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$competitor = $_GET["competitor"];
$allow_editing = isset($_GET["allow_editing"]);
$start_time_adjustment = $_GET["start_time_adjustment"];

$new_name = isset($_GET["new_name"]) ? $_GET["new_name"] : "";
$new_course = isset($_GET["new_course"]) ? $_GET["new_course"] : "";

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, no results can be shown.\n");
}

$courses_path = get_courses_path($event, $key);
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

$competitor_path = get_competitor_path($competitor, $event, $key);
if (!is_dir($competitor_path)) {
  error_and_exit("<p>ERROR: No such competitor found {$competitor} (possibly already edited or removed?).\n");
}


$competitor_name = file_get_contents("{$competitor_path}/name");
$course = file_get_contents("{$competitor_path}/course");
$start_time = file_get_contents("{$competitor_path}/controls_found/start") + $start_time_adjustment;

// Get the list of the new timestamps
$submitted_punches_array = array();
foreach (array_keys($_GET) as $new_punch) {
  if (substr($new_punch, 0, 8) == "Control-") {
    // Format is Control-control_id-sequence_number with a value of the timestamp
    $punch_pieces = explode("-", $new_punch);
    if ($_GET[$new_punch] == 0) {
      if (!is_numeric($_GET[$new_punch])) {
        // This is an error - keep the entry for now and we'll deal with it later
        $submitted_punches_array[$punch_pieces[2]] = array("timestamp" => $_GET[$new_punch], "control_id" => $punch_pieces[1]);
      }
    }
    else {
      $submitted_punches_array[$punch_pieces[2]] = array("timestamp" => $_GET[$new_punch], "control_id" => $punch_pieces[1]);
    }
  }
}

// Make sure the keys are sequential
$sorted_keys = array_keys($submitted_punches_array);
sort($sorted_keys);
$new_punch_array = array_map(function ($elt) use ($submitted_punches_array) { return ($submitted_punches_array[$elt]); }, $sorted_keys);

// Flesh out the new timestamps - allow relative times
$final_punch_array = array();
$error_string = "";
for ($new_punch_iterator = 0; $new_punch_iterator < count($new_punch_array); $new_punch_iterator++) {
  $new_timestamp = $new_punch_array[$new_punch_iterator]["timestamp"];
  if (preg_match("/^\+[0-9]+$/", $new_timestamp)) {
    // Time is relative to the previous entry
    if ($new_punch_iterator > 0) {
      $final_punch_array[$new_punch_iterator] = array("timestamp" => $final_punch_array[$new_punch_iterator - 1]["timestamp"] + $new_timestamp,
                                                      "control_id" => $new_punch_array[$new_punch_iterator]["control_id"]);
    }
    else {
      $final_punch_array[$new_punch_iterator] = array("timestamp" => $start_time + $new_timestamp,
                                                      "control_id" => $new_punch_array[$new_punch_iterator]["control_id"]);
    }
  }
  else if (preg_match("/^[0-9]+$/", $new_timestamp)) {
    // Time is absolute
    $final_punch_array[$new_punch_iterator] = array("timestamp" =>  $new_timestamp + $start_time,
                                                    "control_id" => $new_punch_array[$new_punch_iterator]["control_id"]);
  }
  else {
    $error_string .= "<p>Incorrect timestamp \"{$new_timestamp}\" specified for control {$new_punch_array[$new_punch_iterator]["control_id"]}\n";
    $final_punch_array[$new_punch_iterator] = array("timestamp" =>  0,
                                                    "control_id" => $new_punch_array[$new_punch_iterator]["control_id"]);
  }
}

if ($_GET["additional"] != "") {
  if (preg_match("/^[0-9]+,[0-9]+$/", $_GET["additional"])) {
    $additional_pieces = explode(",", $_GET["additional"]);
    $final_punch_array[$new_punch_iterator] = array("timestamp" => $additional_pieces[1] + $start_time, "control_id" => $additional_pieces[0]);
  }
  else {
    $error_string .= "<p>Additional control ignored, incorrectly formatted, was: {$_GET["additional"]}, should be timestamp,control, all numeric.\n";
  }
}


$final_punch_entries = array_map(function ($elt) { return (sprintf("%010d,%d", $elt["timestamp"], $elt["control_id"])); }, $final_punch_array);
sort($final_punch_entries);

//$finish_time = file_get_contents("{$competitor_path}/controls_found/finish");
$finish_time = 0;
// Get the time of the last control punched - the finish time must come after this
$final_entry = end($final_punch_entries);
$final_control_pieces = explode(",", $final_entry);
$finish_offset = $_GET["finish_offset"];
if ($finish_offset == "please specify") {
  error_and_exit("<p>ERROR: Adjusted finish time must be set - \"{$finish_offset}\" was specified.\n");
}
else if (preg_match("/^\+[0-9]+$/", $finish_offset)) {
  // Time is relative to the last entry
  $finish_time = $finish_offset + $final_control_pieces[0];
}
else if (preg_match("/^[0-9]+$/", $finish_offset)) {
  $finish_time = $finish_offset + $start_time;
  if ($finish_time <= $final_control_pieces[0]) {
    error_and_exit("<p>ERROR: Adjusted finish time cannot come before last control punched - {$finish_time} vs last control punched at {$final_control_pieces[0]}\n");
  }
}
else {
  error_and_exit("<p>ERROR: Cannot determine finish time based on entered value of \"{$finish_offset}\".\n");
}

$output_string = "<p>New punches for {$competitor_name} on " . ltrim($course, "0..9-") . "\n";

$output_string .= "<p>Start at: {$start_time}\n";
$output_string .= "<ul>\n<li>\n";
$output_string .= implode("\n<li>", $final_punch_entries);
$output_string .= "</ul>\n";
$output_string .= "<p>Finish at: {$finish_time}\n";

// Make sure that the newly specified course, if any, is valid
if ($new_course != "") {
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  $matching_courses_list = array_filter($course_list, function ($elt) use ($new_course) { return (ltrim($elt, "0..9-") == $new_course); });
  if (count($matching_courses_list) == 1) {
    // It feels like there should be a more efficient way to do this
    // This should be rare enough that I don't care, but something to think about
    $found_course = array_values($matching_courses_list)[0];
    if (file_exists("{$courses_path}/{$found_course}/removed") || file_exists("{$courses_path}/{$found_course}/no_registrations")) {
      $error_string .= "<p>{$new_course} is no longer accepting registrations.\n";
    }
    $new_course = $found_course;
  }
  else {
    if (count($matching_courses_list) == 0) {
      $error_string .= "<p>No matching course found for {$new_course}, check for exact match, remember is it case sensitive.\n";
    }
    else {
      $error_string .= "<p>Too many matching courses for {$new_course}, name should be unique\n";
    }
  }
}
else {
  $new_course = $course;
}

if ($error_string != "") {
  $output_string .= $error_string;
}


// ###########################################
// New punch information all seems ok, save the competitor information
// Save it as a new competitor (a clone of this one) for safety
// Let the user delete this competitor after making sure all is ok.
if ($error_string == "") {
  // Generate the competitor_id and make sure it is truly unique
  if ($new_name != "") {
    $new_competitor_name = "{$new_name}";
  }
  else {
    $new_competitor_name = "{$competitor_name}";
  }
  $tries = 0;
  while ($tries < 5) {
    $new_competitor_id = uniqid();
    $new_competitor_path = get_competitor_path($new_competitor_id, $event, $key, "..");
    mkdir ($new_competitor_path, 0777);
    $new_competitor_file = fopen($new_competitor_path . "/name", "x");
    if ($new_competitor_file !== false) {
      break;
    }
    $tries++;
  }

  if ($tries === 5) {
    $output_string .= "ERROR Cannot register " . $new_competitor_name . " with id: " . $new_competitor_id . "\n";
    $error = true;
  }
  else {
    $output_string .= "<p>New entry created: " . $new_competitor_name . " on " . ltrim($new_course, "0..9-");

    // Save the information about the competitor
    fwrite($new_competitor_file, $new_competitor_name);
    fclose($new_competitor_file);
    file_put_contents("{$new_competitor_path}/course", $new_course);
    mkdir("./{$new_competitor_path}/controls_found");

    if (file_exists("{$competitor_path}/registration_info")) {
      $raw_registration_info = file_get_contents("{$competitor_path}/registration_info");
      file_put_contents("{$new_competitor_path}/registration_info", $raw_registration_info);
      if (file_exists("{$competitor_path}/si_stick")) {
        $si_stick = file_get_contents("{$competitor_path}/si_stick");
        file_put_contents("{$new_competitor_path}/si_stick", $si_stick);
      }

      // Preserve the NRE classification info, if it is present
      if (event_is_using_nre_classes($event, $key) && competitor_has_class($competitor_path)) {
        set_class_for_competitor($new_competitor_path, get_class_for_competitor($competitor_path));
      }
    }

    global $TYPE_FIELD, $SCORE_O_COURSE;

    $error_string = "";
    $result_filename = "";

    $control_list = read_controls("{$courses_path}/{$new_course}/controls.txt");
    $controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                          array_map(function ($element) { return $element[1]; }, $control_list));
  
    $results_path = get_results_path($event, $key, "..");
    $course_properties = get_course_properties("{$courses_path}/{$new_course}");
    $is_score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    if ($is_score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }
    else {
      // For a non-ScoreO, each control is 1 point
      $max_score = count($control_list);
    }
  
    $extra_controls_string = "";
    $next_control_number = 0;
    $controls_done = array();
    // Save the punches in the controls_found directory
    // Check to see that they are valid along the way
    foreach ($final_punch_entries as $edited_punch) {
      $control_pieces = explode(",", $edited_punch);
      $control_is_valid = false;
  
      if ($is_score_course) {
         if (isset($controls_points_hash[$control_pieces[1]])) {
           $control_is_valid = true;
         }
      }
      else if ($control_list[$next_control_number][0] == $control_pieces[1]) {
        $control_is_valid = true;
        $next_control_number++;
      }
  
      if ($control_is_valid) {
        // Save the control in the expected format
        file_put_contents("{$new_competitor_path}/controls_found/{$edited_punch}", "");
        $controls_done[] = $edited_punch;
      }
      else {
        $extra_controls_string .= "{$edited_punch}\n";
      }
    }
  
    if ($extra_controls_string != "") {
      file_put_contents("{$new_competitor_path}/extra", $extra_controls_string);
    }
  
    file_put_contents("{$new_competitor_path}/controls_found/start", $start_time);
    file_put_contents("{$new_competitor_path}/controls_found/finish", $finish_time);
    $time_taken = $finish_time - $start_time;


    // Check the punches - is the course complete?
    // If a scoreO, what is the score?
  
    // Just pluck off the controls found (ignore the timestamp for now
    $controls_found = array_map(function ($item) { return (explode(",", $item)[1]); }, $controls_done);
    $dnf_string = "";

    // For each control, look up its point value in the associative array and sum the total points
    // TODO: Must de-dup the controls found - Don't doublecount the points!!
    if ($is_score_course) {
      $unique_controls = array_unique($controls_found);
      //$total_score = calculate_score($unique_controls, $controls_points_hash);
      $total_score = array_reduce($unique_controls, function ($carry, $elt) use ($controls_points_hash) { return($carry + $controls_points_hash[$elt]); }, 0);
      // Reduce the total_score if over time
      if (($course_properties[$LIMIT_FIELD] > 0) && ($time_taken > $course_properties[$LIMIT_FIELD])) {
        $time_over = $time_taken - $course_properties[$LIMIT_FIELD];
        $minutes_over = floor(($time_over + 59) / 60);
        $penalty = $minutes_over * $course_properties[$PENALTY_FIELD];

        $output_string .= "<p>Exceeded time limit of " . formatted_time($course_properties[$LIMIT_FIELD]) . " by " . formatted_time($time_over) . "\n" .
                           "<p>Penalty is {$course_properties[$PENALTY_FIELD]} pts/minute, total penalty of $penalty points.\n" .
                           "<p>Control score was $total_score -> " . ($total_score - $penalty) . " after penalty.\n";

        $total_score -= $penalty;
      }
    }
    else {
      $total_score = count($controls_found);
      $number_controls_found = $next_control_number;

      // With the edited punches, was the course completed?
      $number_controls_on_course = count($control_list);
      // echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found][0] . "--\n";
      if ($number_controls_found != $number_controls_on_course) {
        $output_string .= "<p>Not all controls found, found ${number_controls_found} controls, expected ${number_controls_on_course} controls.\n";
        file_put_contents("{$new_competitor_path}/dnf", $error_string, FILE_APPEND);
        $dnf_string = " - DNF";
      }
    }

    $result_filename = sprintf("%04d,%06d,%s", $max_score - $total_score, $time_taken, $new_competitor_id);
    file_put_contents("{$results_path}/{$new_course}/{$result_filename}", "");

    $readable_course_name = ltrim($new_course, "0..9-");
    $output_string .= "<p class=\"title\">Results for: {$new_competitor_name}, course complete ({$readable_course_name}{$dnf_string}), time taken " . formatted_time($time_taken) . "<p><p>";

    $output_string .= "<form action=\"./remove_from_event.php\">\n";
    $output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
    $output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
    $output_string .= "<input type=hidden name=Remove-{$competitor} value=\"1\">\n";
    $output_string .= "<input type=submit value=\"Remove prior entry for {$competitor_name}\">\n";
    $output_string .= "</form>\n\n";
    // Update the existing competitor name as having been overridden by the edits
    // if (substr($competitor_name, -4) != " (*)") {
    //   $updated_competitor_name = "{$competitor_name} (*)";
    //   file_put_contents("{$competitor_path}/name", $updated_competitor_name);
    // }
  }
}

// ###################################

echo get_web_page_header(true, true, false);

echo $output_string;

echo "<p><p><a href=\"./competitor_info.php?key={$key}&event={$event}\">Back to meet director information page</a>\n";

echo get_web_page_footer();
?>
