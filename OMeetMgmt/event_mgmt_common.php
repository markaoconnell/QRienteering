<?php

$MAX_COURSE_NAME_LEN = 40;
$MAX_CONTROL_CODE_LEN = 40;
$MAX_CONTROL_VALUE = 100;
$MAX_COURSES = 20;
$MAX_CONTROLS = 50;

// Fields in the array returned from validate_and_parse_course
$ERRORS = "errors";
$OUTPUT = "output";
$VERBOSE_OUTPUT = "verbose output";


// Utility functions
function ck_valid_chars($string_to_check) {
  return (preg_match("/^[a-zA-Z0-9_-]+$/", $string_to_check));
}

function ck_linear_control_entry($string_to_check) {
  global $MAX_CONTROL_CODE_LEN;
  return((ctype_alnum($string_to_check) && (strlen($string_to_check) < $MAX_CONTROL_CODE_LEN)) ? 1 : 0);
}

function ck_score_control_entry($string_to_check) {
  global $MAX_CONTROL_CODE_LEN, $MAX_CONTROL_VALUE;
  if (!preg_match("/^[a-zA-Z0-9]+:[0-9]+$/", $string_to_check)) {
    return(0);
  }

  $pieces = explode(":", $string_to_check);
  if ((strlen($pieces[0]) > $MAX_CONTROL_CODE_LEN) || ($pieces[1] > $MAX_CONTROL_VALUE)) {
    return(0);
  }

  return(1);
}


function parse_course_name($course_name) {
  global $NAME_FIELD, $TYPE_FIELD, $LIMIT_FIELD, $PENALTY_FIELD, $SCORE_O_COURSE, $LINEAR_COURSE, $ERROR_FIELD;
  global $SCORE_COURSE_ID, $LINEAR_COURSE_ID;

  $info = explode(":", $course_name);
  $return_info = array();
  if (strlen($info[0]) == 1) {
    if ($info[0] == $SCORE_COURSE_ID) {
      $return_info[$NAME_FIELD] = $info[1];
      $return_info[$TYPE_FIELD] = $SCORE_O_COURSE;
      $return_info[$LIMIT_FIELD] = time_limit_to_seconds($info[2]);
      $return_info[$PENALTY_FIELD] = $info[3];
      $expected_fields = 4; 

      if ($return_info[$LIMIT_FIELD] == -1) {
        $return_info[$ERROR_FIELD] = "Time limit field not understandable, {$info[2]} not in format XXhYYmZZs.\n";
      }
    }
    else if ($info[0] == $LINEAR_COURSE_ID) {
      $return_info[$NAME_FIELD] = $info[1];
      $return_info[$TYPE_FIELD] = $LINEAR_COURSE;
      $expected_fields = 2;
    }
    else {
      // This case really shouldn't happen, but to be safe
      $return_info[$NAME_FIELD] = $info[0];
      $return_info[$TYPE_FIELD] = $LINEAR_COURSE;
      $expected_fields = 1;
    }
  }
  else {
    // This case really shouldn't happen, but to be safe
    $return_info[$NAME_FIELD] = $info[0];
    $return_info[$TYPE_FIELD] = $LINEAR_COURSE;
    $expected_fields = 1;
  }

  if (count($info) != $expected_fields) {
    $return_info[$ERROR_FIELD] = "Unexpected number entries: {$course_name}, {$expected_fields} expected, found " . count($info) . "\n";
  }

  return($return_info);
}


function validate_and_parse_course($course_description) {
  global $NAME_FIELD, $TYPE_FIELD, $LIMIT_FIELD, $PENALTY_FIELD, $SCORE_O_COURSE, $LINEAR_COURSE, $ERROR_FIELD, $CONTROLS;
  global $SCORE_COURSE_ID, $LINEAR_COURSE_ID, $MAX_SCORE_FIELD;
  global $ERRORS, $OUTPUT, $VERBOSE_OUTPUT;
  global $MAX_COURSE_NAME_LEN, $MAX_CONTROLS;

  // Course name must begin with a letter and may only contain [a-zA-Z0-9-_]
  // controls may only contain [a-zA-Z0-9]
  // The Course name may have extra, : separated information, especially for a scoreO
  $course_name_and_controls = explode(",", $course_description);
  $course_name_entry = trim($course_name_and_controls[0]);
  $course_info = parse_course_name($course_name_entry);
  $course_name = $course_info[$NAME_FIELD];

  $error_string = "";
  $output_string = "";
  $verbose_output_string = "";

  if ($course_info[$ERROR_FIELD] != "") {
    $error_string .= "<p>ERROR: Course entry {$this_course} looks wrong: {$course_info[$ERROR_FIELD]}\n";
    $found_error = true;
  }

  if ((ctype_alpha(substr($course_name, 0, 1))) && (ck_valid_chars($course_name)) && 
        (strlen($course_name) < $MAX_COURSE_NAME_LEN)) {
    $verbose_output_string .= "<p>Course name {$course_name} passes the checks.\n";
  }
  else {
    $error_string .= "<p>ERROR: Course name \"{$course_name}\" fails the checks, only letters, numbers, and - allowed.\n";
  }

  $control_list = array_map('trim', array_slice($course_name_and_controls, 1));

  if ($course_info[$TYPE_FIELD] == $LINEAR_COURSE) {
    $check_controls = array_map('ck_linear_control_entry', $control_list);
  }
  else if ($course_info[$TYPE_FIELD] == $SCORE_O_COURSE) {
    $check_controls = array_map('ck_score_control_entry', $control_list);
  }
  else {
    $error_string .= "<p>ERROR: Unknown course type {$course_info[$TYPE_FIELD]}.\n";
    $check_controls = array();
  }

  if (array_search(0, $check_controls) === false) {
    $verbose_output_string .= "<p>Control list all seems to be correctly formatted and not too long.\n";
  }
  else {
    $error_string .= "<p>ERROR: Control list for \"{$course_name}\" contains either non-alphanumeric characters or too long.\n";
    $error_control_entries = array_filter($check_controls, function ($elt) { return ($elt == 0); });
    $controls_with_an_error = array_map(function ($elt) use ($control_list) { return ($control_list[$elt]); }, array_keys($error_control_entries));
    $error_string .= "<p>Incorrect controls: " . join(",", $controls_with_an_error) . "\n";
  }

  if (count($control_list) > $MAX_CONTROLS) {
    $error_string .= "<p>ERROR: Too many controls found - " . count($control_list) . "\n";
  }

  // Validate that if there are duplicate entries, that at least the point values are the same
  if ($course_info[$TYPE_FIELD] == $SCORE_O_COURSE) {
    $control_dedup_hash = array();
    foreach ($control_list as $control_entry) {
      $pieces = explode(":", $control_entry);
      if (!isset($control_dedup_hash[$pieces[0]])) {
        $control_dedup_hash[$pieces[0]] = $pieces[1];
      }
      else if ($control_dedup_hash[$pieces[0]] != $pieces[1]) {
        $error_string .= "<p>ERROR: Control {$pieces[0]} duplicated with different point values {$control_dedup_hash[$pieces[0]]} and {$pieces[1]}.\n";
      }
      else {
        $output_string .= "<p>INFO: Control {$pieces[0]} duplicated, ignoring second entry.\n";
      }
    }

    $control_list = array_map(function ($elt) use ($control_dedup_hash) { return (implode(":", array($elt, $control_dedup_hash[$elt]))); },
                              array_keys($control_dedup_hash));
  }

  if (count($course_name_and_controls) > 1) {
    if ($course_info[$TYPE_FIELD] == $LINEAR_COURSE) {
       // For a linear course, the controls are all worth one point. TBD if this is the right
       // place to do this.
       $control_list = array_map(function ($control) { return("{$control}:1"); }, $control_list);
    }
    else if ($course_info[$TYPE_FIELD] == $SCORE_O_COURSE) {
      // Get the maximum score for the course
      $points_array = array_map(function ($control) { $pieces = explode(":", $control); return ($pieces[1]); }, $control_list);
      $max_score = array_sum($points_array);
      $course_info[$MAX_SCORE_FIELD] = $max_score;
    }

    $verbose_output_string .= "<p>Found controls for course {$course_name}: " . implode("--", $control_list) . "\n";
  }
  else {
    $error_string .= "<p>ERROR: No controls for course {$course_name}.\n";
  }

  $course_info[$CONTROLS] = $control_list;
  $results[$ERRORS] = $error_string;
  $results[$OUTPUT] = $output_string;
  $results[$VERBOSE_OUTPUT] = $verbose_output_string;

  return(array($course_info, $results));
}


function create_event($key, $event_description) {
  if (!is_dir(get_base_path($key, ".."))) {
    mkdir(get_base_path($key, ".."), 0755, true);  // Create the intermediate directories as necessary
  }

  $event_name_attempts = 0;
  while ($event_name_attempts < 100) {
    $event_name = uniqid("event-");
    $event_path = get_event_path($event_name, $key, "..");

    if (!file_exists($event_path)) {
      break;
    }

    $event_name_attempts++;
  }
  if (($event_name_attempts >= 100)  || file_exists($event_path)) {
    return("ERROR: Internal error creating event, please wait and retry.");
  }

  mkdir($event_path);
  mkdir("{$event_path}/Competitors");
  mkdir("{$event_path}/Courses");
  mkdir("{$event_path}/Results");
  file_put_contents("{$event_path}/description", $event_description);

  return($event_name);
}

function create_course_in_event($course_info, $key, $event) {
  global $NAME_FIELD, $TYPE_FIELD, $LIMIT_FIELD, $PENALTY_FIELD, $SCORE_O_COURSE, $LINEAR_COURSE, $ERROR_FIELD, $CONTROLS;
  global $SCORE_COURSE_ID, $LINEAR_COURSE_ID;

  $event_path = get_event_path($event, $key, "..");
  
  // How many existing courses are there?
  $existing_courses_array = scandir("{$event_path}/Courses");
  $existing_courses_array = array_diff($existing_courses_array, array(".", ".."));
  if (count($existing_courses_array) > 0) {
    $existing_course_numbers = array_map(function ($elt) { return explode("-", $elt)[0]; }, $existing_courses_array);
    $highest_course_number = max($existing_course_numbers);
    $this_course_number = $highest_course_number + 1;
  }
  else {
    $this_course_number = 0;
  }
  
  $prefix = sprintf("%02d", $this_course_number);
  mkdir("{$event_path}/Courses/{$prefix}-{$course_info[$NAME_FIELD]}");
  mkdir("{$event_path}/Results/{$prefix}-{$course_info[$NAME_FIELD]}");
  file_put_contents("${event_path}/Courses/{$prefix}-{$course_info[$NAME_FIELD]}/controls.txt", implode("\n", $course_info[$CONTROLS]));

  if ($course_info[$TYPE_FIELD] == $SCORE_O_COURSE) {
    $properties_string = "";
    foreach ($course_info as $props_key => $props_value) {
      if ($props_key != $CONTROLS) {
        $properties_string .= $props_key . ":" . $props_value . "\n";
      }
    }
    file_put_contents("{$event_path}/Courses/{$prefix}-{$course_info[$NAME_FIELD]}/properties.txt", $properties_string);
  }
}

?>
