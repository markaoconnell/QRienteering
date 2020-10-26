<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

$event = $_COOKIE["event"];
$key = $_COOKIE["key"];
$control_id = $_GET["control"];
$competitor_id = $_COOKIE["competitor_id"];

if (($event == "") || ($competitor_id == "")) {
  error_and_exit("<p>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?" . get_error_info_string());
}

$error_string = "";
$success_msg = "";

// Do an internal redirect, encoding the competitor_id and control - this is to prevent later
// replays when this device is potentially redoing the course
// A redo of the course will generate a new competitor_id, which will then be detected
if (!file_exists(get_event_path($event, $key, "..") . "/no_redirects") && ($_GET["mumble"] == "")) {
  $current_time = time();
  $redirect_encoded_info = base64_encode("{$control_id},{$competitor_id},{$current_time}");
  echo "<html><head><meta http-equiv=\"refresh\" content=\"0; URL=./reach_control.php?mumble=${redirect_encoded_info}\" /></head></html>";
  return;
}

if ($_GET["mumble"] != "") {
  $pieces = explode(",", base64_decode($_GET["mumble"]));
  $control_id = $pieces[0];
  $encoded_competitor_id = $pieces[1];
  $time_of_page_access = $pieces[2];

  if ($encoded_competitor_id != $competitor_id) {
    error_and_exit("<p>ERROR: Competitor mismatch, possible replay of earlier scan?\n");
  }
  else if ((time() - $time_of_page_access) > 30) {
    // 30 second buffer for page reloads
    error_and_exit("<p>ERROR: Time lag of > 30 seconds since scan of control {$control_id} - incorrect page reload?\n");
  }
}


// Get the submitted info
// echo "<p>\n";
$course = $_COOKIE["course"];

$competitor_path = get_competitor_path($competitor_id, $event, $key, "..");
$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($competitor_path) || !file_exists("{$courses_path}/{$course}/controls.txt")) {
  error_and_exit("Cannot find event {$event}, competitor {$competitor_id}, or course {$course}, please re-register and retry.\n");
}


$controls_found_path = "{$competitor_path}/controls_found";
$control_list = read_controls("{$courses_path}/{$course}/controls.txt");
$controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                      array_map(function ($element) { return $element[1]; }, $control_list));
// echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);

$course_properties = get_course_properties("{$courses_path}/{$course}");
$score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));

if (file_exists("${competitor_path}/si_stick")) {
  $competitor_name = file_get_contents("{$competitor_path}/name");
  error_and_exit("<p>{$competitor_name} on course {$course} registered with si unit, should not scan QR codes.\n");
}

$autostart_msg = "";
if (!file_exists("${controls_found_path}/start")) {
  $competitor_name = file_get_contents("{$competitor_path}/name");
  $time_of_registration = stat("{$competitor_path}/name")["mtime"];
  file_put_contents("{$controls_found_path}/start", $time_of_registration);
  $autostart_msg = "<p>Course " . ltrim($course, "0..9-") . " auto-started at " . strftime("%T", $time_of_registration) .
                   " for {$competitor_name}, for a more accurate time please re-register and be certain to scan the Start QR code.\n";
}

if (file_exists("${controls_found_path}/finish")) {
  $competitor_name = file_get_contents("{$competitor_path}/name");
  error_and_exit("<p>Course " . ltrim($course, "0..9-") . " already finished for {$competitor_name}, please return and re-register to restart the course.\n");
}

// See how many controls have been completed
$controls_done = scandir("./${controls_found_path}");
$controls_done = array_diff($controls_done, array(".", "..", "start", "finish")); // Remove the annoying . and .. entries
$start_time = file_get_contents("./{$controls_found_path}/start");
$time_now = time();
$time_on_course = $time_now - $start_time;
$extra_info_msg = "";
$append_finish_message = false;
// echo "<br>Controls done on the ${course} course.<br>\n";
// print_r($controls_done);

if ($score_course) {
  // Is the control on the course?
  $found_control = false;
  $points_for_control = -1;
  foreach ($control_list as $entry) {
    if ($entry[0] == $control_id) {
      $found_control = true;
      $points_for_control = $entry[1];
      break;
    }
  }

  if ($found_control) {
    $control_found_filename = "{$controls_found_path}/" . strval($time_now) . ",{$control_id}";
    file_put_contents($control_found_filename, "");
    $success_msg = "<p>Reached {$control_id} on " . ltrim($course, "0..9-") . ", earned {$points_for_control} points.\n";
  }
  else {
    $error_string .= "<p>Found wrong control, control {$control_id} not on course " . ltrim($course, "0..9-") . "\n";
    $extra_control_string = strval($time_now) . ",{$control_id}\n";
    file_put_contents($competitor_path . "/extra", $extra_control_string, FILE_APPEND);
  }

  if ($course_properties[$LIMIT_FIELD] > 0) {
    if ($time_on_course <= $course_properties[$LIMIT_FIELD]) {
      $extra_info_msg = "<p>Time remaining on course: " . formatted_time($course_properties[$LIMIT_FIELD] - $time_on_course) . "\n";
    }
    else {
      $extra_info_msg = "<p>Time limit expired by: " . formatted_time($time_on_course - $course_properties[$LIMIT_FIELD]) . "\n";
    }
  }

  // Don't forget to include the control we just found!
  $unique_controls_array = $controls_done;
  if ($found_control) {
    $unique_controls_array[] = "xxx,{$control_id}";  // The xxx (the timestamp) is about to be stripped off anyway
  }
  $unique_controls_array = array_unique(array_map(function ($elt) { return (explode(",", $elt)[1]); }, $unique_controls_array));
  $num_unique_controls_done = count($unique_controls_array);
  $extra_info_msg .= "<p>{$num_unique_controls_done} controls done, " . (count($control_list) - $num_unique_controls_done) . " possible controls remaining.\n";

  $remaining_controls_list = array_diff(array_map(function ($element) { return $element[0]; }, $control_list), $unique_controls_array);
  sort($remaining_controls_list);
  $extra_info_msg .= "<p>Controls remaining: " . join(",", array_map(function ($elt) use ($controls_points_hash)
                                                                              { return ("{$elt} => " . $controls_points_hash[$elt] . " pts"); },
                                                                     $remaining_controls_list));
  $extra_info_msg .= "<p>Controls done: " . join(",", $unique_controls_array) . "\n";
}
else {
  // Handle a linear (non-score) course
  // Are we at the right control?
  $number_controls_found = count($controls_done);
  $prior_control_repeat = false;
  // echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found][0] . "--\n";
  if ($number_controls_found >= count($control_list)) {
    // We should be finishing the course
    // Possible that we scanned the last control twice - check for that
    $append_finish_message = true;
    if ($control_id != $control_list[count($control_list) - 1][0]) {
      $error_string .= "<p>Found wrong control: {$control_id}, course " . ltrim($course, "0..9-") . ", control #" . ($number_controls_found + 1) .
                          ", expected to finish course.\n";
      $extra_control_string = strval($time_now) . ",{$control_id}\n";
      file_put_contents($competitor_path . "/extra", $extra_control_string, FILE_APPEND);
    }
    else {
      $success_msg = "<p>Control {$control_id} correct but already scanned on " . ltrim($course, "0..9-") . "\n" .
                     "<p>0 more to find, next is Finish.\n";
    }
  }
  else if ($control_id != $control_list[$number_controls_found][0]) {
    // echo "<p>This looks like the wrong control\n";
    // Not the right control, but if we're still at the prior control, the person probably just scanned the control twice - that's ok
    if ($number_controls_found == 0) {
      $prior_control = "NoPriorControl";
    }
    else {
      $prior_control = $control_list[$number_controls_found - 1][0];
    }
  
    if ($control_id != $prior_control) {
      $error_string .= "<p>Found wrong control: {$control_id}, course " . ltrim($course, "0..9-") . ", control #" . ($number_controls_found + 1) .
                          ", expected control " . $control_list[$number_controls_found][0] . "\n";
      $extra_control_string = strval($time_now) . ",{$control_id}\n";
      file_put_contents($competitor_path . "/extra", $extra_control_string, FILE_APPEND);
      // echo "<p>This looks like it also wasn't the prior control\n";
    }
    else {
      // echo"<p>This looks like a rescan of the prior control.\n";
      $remaining_controls = count($control_list) - $number_controls_found;
      if ($remaining_controls <= 0) {
        $next_control = "Finish";
        $append_finish_message = true;
      }
      else {
        $next_control = $control_list[$number_controls_found][0];
      }
    $control_number_for_printing = $number_controls_found;
    $success_msg = "<p>Control {$control_id} correct but already scanned." .
                   "<p>Control #{$control_number_for_printing} on " . ltrim($course, "0..9-") . "\n" .
                   "<p>{$remaining_controls} more to find, next is {$next_control}.\n";
    }
  }
  else {
    $control_found_filename = "{$controls_found_path}/" . strval($time_now) . ",{$control_id}";
    file_put_contents($control_found_filename, "");
    $remaining_controls = count($control_list) - $number_controls_found - 1;
    if ($remaining_controls <= 0) {
      $next_control = "Finish";
      $append_finish_message = true;
    }
    else {
      $next_control = $control_list[$number_controls_found + 1][0];
    }
    $control_number_for_printing = $number_controls_found + 1;
    $success_msg = "<p>Correct!  Reached {$control_id}, control #{$control_number_for_printing} on " . ltrim($course, "0..9-") . "\n" .
                   "<p>{$remaining_controls} more to find, next is {$next_control}.\n";
    // echo "<p>Saved to the file ${competitor_path}/${number_controls_found}.\n";
  }

  if ($append_finish_message) {
    $finish_msg .= "<p style=\"text-align:center; background: #faf60f; color: #2809db; padding: 25px;\">Remember to scan finish to indicate you are off the course</p>";
    if ($success_msg != "") {
      $success_msg .= $finish_msg;
    }

    if ($error_string != "") {
      $error_string .= $finish_msg;
    }
  }
}

if (($error_string == "") && ($autostart_msg == "")) {
  set_success_background();
}
else {
  set_error_background();
}

echo get_web_page_header(true, false, false);

if ($error_string == "") {
  echo $success_msg;
}
else {
  echo "<p>ERROR: {$error_string}\n";
}

if ($autostart_msg != "") {
  echo $autostart_msg;
}

echo "<br><p>Time on course is: " . formatted_time($time_on_course) . "\n";

if ($extra_info_msg != "") {
  echo $extra_info_msg;
}

echo get_web_page_footer();
?>
