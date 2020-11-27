<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

$event = $_COOKIE["event"];
$key = $_COOKIE["key"];
$control_id = $_GET["control"];
$competitor_id = $_COOKIE["competitor_id"];
$course = $_COOKIE["course"];
$time_now = time();
$skip_adding_control_as_extra = false;

if (($event == "") || ($competitor_id == "")) {
  error_and_exit("<p>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?" . get_error_info_string());
}

if ($_GET["skipped_controls"] != "") {
  $get_key = $_GET["key"];
  $get_event = $_GET["event"];
  $get_control_id = $_GET["control"];
  $get_competitor_id = $_GET["competitor_id"];
  $get_course = $_GET["course"];
  $get_skipped_controls = $_GET["skipped_controls"];
  $get_prior_skipped_controls = $_GET["prior_skipped_controls"];

  // Validate the parameters - guard against a later replay
  // The control_id test is kind of pointless, as the control_id always comes in via a GET and never the cookies
  // but the symmetry of all the tests is nice and the pointless test is harmless
  if (($key != $get_key) || ($event != $get_event) || ($get_control_id != $control_id) || ($get_course != $course) ||
      ($get_competitor_id != $competitor_id)) {
    error_and_exit("<p>ERROR: Possible replay of skip controls page, one of key: {$key}, event: {$event}, competitor {$competitor} doesn't match.\n");
  }

  if ($get_prior_skipped_controls != $_COOKIE["{$competitor_id}_skipped_controls"]) {
    error_and_exit("<p>ERROR: Possible replay of skip controls page, skip control list mismatch:<ul>\n" .
                   "<li>Was: {$get_prior_skipped_controls}\n" .
                   "<li>Now: {$_COOKIE["{$competitor_id}_skipped_controls"]}\n</ul>\n");
  }

  if ($get_prior_skipped_controls == "") {
    $_COOKIE["${competitor_id}_skipped_controls"] = $get_skipped_controls;
  }
  else {
    $_COOKIE["{$competitor_id}_skipped_controls"] = "{$_COOKIE["{$competitor_id}_skipped_controls"]},{$get_skipped_controls}";
  }
  setcookie("{$competitor_id}_skipped_controls", $_COOKIE["{$competitor_id}_skipped_controls"], $time_now + 3600 * 6);
  $skip_adding_control_as_extra = true;
}

$error_string = "";
$success_msg = "";
$skip_controls_form = "";

// Do an internal redirect, encoding the competitor_id and control - this is to prevent later
// replays when this device is potentially redoing the course
// A redo of the course will generate a new competitor_id, which will then be detected
if (!file_exists(get_event_path($event, $key, "..") . "/no_redirects") && ($_GET["mumble"] == "")) {
  $current_time = time();
  $extra_mumble_field = "";
  if ($skip_adding_control_as_extra) {
    $extra_mumble_field = ",redo";
  }
  $redirect_encoded_info = base64_encode("{$control_id},{$competitor_id},{$current_time}{$extra_mumble_field}");
  echo "<html><head><meta http-equiv=\"refresh\" content=\"0; URL=./reach_control.php?mumble=${redirect_encoded_info}\" /></head></html>";
  return;
}

if ($_GET["mumble"] != "") {
  $pieces = explode(",", base64_decode($_GET["mumble"]));
  $control_id = $pieces[0];
  $encoded_competitor_id = $pieces[1];
  $time_of_page_access = $pieces[2];
  $skip_adding_control_as_extra = ($pieces[3] == "redo");

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
    $control_found_filename = "{$controls_found_path}/" . sprintf("%010d", $time_now) . ",{$control_id}";
    file_put_contents($control_found_filename, "");
    $success_msg = "<p>Reached {$control_id} on " . ltrim($course, "0..9-") . ", earned {$points_for_control} points.\n";
  }
  else {
    $error_string .= "<p>Found wrong control, control {$control_id} not on course " . ltrim($course, "0..9-") . "\n";
    $extra_control_string = sprintf("%010d", $time_now) . ",{$control_id}\n";
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
  $has_skipped_controls = false;
  if (isset($_COOKIE["{$competitor_id}_skipped_controls"])) {
    $number_controls_found += count(explode(",", $_COOKIE["{$competitor_id}_skipped_controls"]));
    $has_skipped_controls = true;
  }

  $prior_control_repeat = false;
  // echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found][0] . "--\n";
  if ($number_controls_found >= count($control_list)) {
    // We should be finishing the course
    // Possible that we scanned the last control twice - check for that
    $append_finish_message = true;
    if ($control_id != $control_list[count($control_list) - 1][0]) {
      $error_string .= "<p>Found wrong control: {$control_id}, course " . ltrim($course, "0..9-") . ", control #" . ($number_controls_found + 1) .
                          ", expected to finish course.\n";
      $extra_control_string = sprintf("%010d", $time_now) . ",{$control_id}\n";
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
      $extra_control_string = sprintf("%010d", $time_now) . ",{$control_id}\n";
      file_put_contents($competitor_path . "/extra", $extra_control_string, FILE_APPEND);
      // echo "<p>This looks like it also wasn't the prior control\n";

      // Check to see if this control appears later
      // if so, give an option to skip ahead
      $possible_controls_to_skip = array();
      $control_is_on_course = false;
      for ($index = $number_controls_found; $index < count($control_list); $index++) {
        if ($control_list[$index][0] == $control_id) {
          $control_is_on_course = true;
          break;
        }
        else {
          $possible_controls_to_skip[] = "{$control_list[$index][0]}";
        }
      }

      // if $control_is_on_course is true, then there should ALWAYS be at least
      // one entry in $possible_controls_to_skip, but doublecheck for safety
      if ($control_is_on_course && (count($possible_controls_to_skip) > 0)) {
        $skipped_controls_csv = implode(",", array_map( function ($elt) { return ("skip-{$elt}"); }, $possible_controls_to_skip));
        $printable_skipped_controls_csv = implode(", ", $possible_controls_to_skip);
        $skip_controls_form = "<p>You are at a later control on the course - " .
                                      "would you like to skip the missed controls (will be a DNF?) " .
                                      "or continue to try and find all the controls?\n" .
                               "<p>Click on the button below to skip the missed controls, " .
                                      "do nothing (or close this page) to continue to look for all the controls.\n";
        $skip_controls_form .= "<p><form action=./reach_control.php>\n";
        $skip_controls_form .= "<input type=hidden name=\"key\" value=\"{$key}\">\n";
        $skip_controls_form .= "<input type=hidden name=\"event\" value=\"{$event}\">\n";
        $skip_controls_form .= "<input type=hidden name=\"control\" value=\"{$control_id}\">\n";
        $skip_controls_form .= "<input type=hidden name=\"competitor_id\" value=\"{$competitor_id}\">\n";
        $skip_controls_form .= "<input type=hidden name=\"course\" value=\"{$course}\">\n";
        $skip_controls_form .= "<input type=hidden name=\"skipped_controls\" value=\"{$skipped_controls_csv}\">\n";
        $skip_controls_form .= "<input type=hidden name=\"prior_skipped_controls\" value=\"{$_COOKIE["{$competitor_id}_skipped_controls"]}\">\n";
        $skip_controls_form .= "<input type=submit value=\"Skip controls: {$printable_skipped_controls_csv}\">\n";
        $skip_controls_form .= "</form>\n";
      }
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
    $control_entry = sprintf("%010d", $time_now) . ",{$control_id}";
    if ($has_skipped_controls) {
      // Count this as a skipped control
      $new_cookie_entry = "{$_COOKIE["{$competitor_id}_skipped_controls"]},ok-{$control_id}";
      setcookie("{$competitor_id}_skipped_controls", $new_cookie_entry, $time_now + 3600 * 6);
      if (!$skip_adding_control_as_extra) {
        file_put_contents("{$competitor_path}/extra", "${control_entry}\n", FILE_APPEND);
      }
    }
    else {
      $control_found_filename = "{$controls_found_path}/{$control_entry}";
      file_put_contents($control_found_filename, "");
    }
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

echo get_web_page_header(true, false, true);

if ($error_string == "") {
  echo $success_msg;
}
else {
  echo "<p>ERROR: {$error_string}\n";
}

if ($autostart_msg != "") {
  echo $autostart_msg;
}

if ($skip_controls_form != "") {
  echo $skip_controls_form;
}

echo "<br><p>Time on course is: " . formatted_time($time_on_course) . "\n";

if ($extra_info_msg != "") {
  echo $extra_info_msg;
}

echo get_web_page_footer();
?>
