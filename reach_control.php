<?php
require 'common_routines.php';

ck_testing();

$event = $_COOKIE["event"];
$control_id = $_GET["control"];
$competitor_id = $_COOKIE["competitor_id"];

if (($event == "") || ($competitor_id == "")) {
  error_and_exit("<p>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?" . get_error_info_string());
}

$error_string = "";

// Do an internal redirect, encoding the competitor_id and control - this is to prevent later
// replays when this device is potentially redoing the course
// A redo of the course will generate a new competitor_id, which will then be detected
if (!file_exists("./${event}/no_redirects") && ($_GET["mumble"] == "")) {
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
    $error_string .= "<p>ERROR: Competitor mismatch, possible replay of earlier scan?\n";
    $control_id = "ERROR";
  }
  else if ((time() - $time_of_page_access) > 30) {
    // 30 second buffer for page reloads
    $error_string .= "<p>ERROR: Time lag of > 30 seconds since scan of control {$control_id} - incorrect page reload?\n";
    $control_id = "ERROR";
  }
}


// Get the submitted info
// echo "<p>\n";
$course = $_COOKIE["course"];

if (!file_exists("./{$event}/Competitors/{$competitor_id}") || !file_exists("./{$event}/Courses/{$course}/controls.txt")) {
  error_and_exit("Cannot find event {$event}, competitor {$competitor_id}, or course {$course}, please re-register and retry.\n");
}


$competitor_path = "./${event}/Competitors/${competitor_id}";
$controls_found_path = "{$competitor_path}/controls_found";
$control_list = read_controls("./{$event}/Courses/{$course}/controls.txt");
// echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);


if (!file_exists("${controls_found_path}/start")) {
  $competitor_name = file_get_contents("./{$event}/Competitors/{$competitor_id}/name");
  error_and_exit("<p>Course " . ltrim($course, "0..9-") . " not started for {$competitor_name}, please return and scan Start QR code.\n");
}

if (file_exists("${controls_found_path}/finish")) {
  $competitor_name = file_get_contents("./{$event}/Competitors/{$competitor_id}/name");
  error_and_exit("<p>Course " . ltrim($course, "0..9-") . " already finished for {$competitor_name}, please return and re-register to restart the course.\n");
}

// See how many controls have been completed
$controls_done = scandir("./${controls_found_path}");
$controls_done = array_diff($controls_done, array(".", "..", "start", "finish")); // Remove the annoying . and .. entries
$start_time = file_get_contents("./{$controls_found_path}/start");
$time_on_course = time() - $start_time;
// echo "<br>Controls done on the ${course} course.<br>\n";
// print_r($controls_done);

// Are we at the right control?
$number_controls_found = count($controls_done);
$prior_control_repeat = false;
// echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found][0] . "--\n";
if ($control_id != $control_list[$number_controls_found][0]) {
  // echo "<p>This looks like the wrong control\n";
  // Not the right control, but if we're still at the prior control, the person probably just scanned the control twice - that's ok
  if ($number_controls_found == 0) {
    $prior_control = "NoPriorControl";
  }
  else {
    $prior_control = $control_list[$number_controls_found - 1][0];
  }

  if ($control_id != $prior_control) {
    if ($control_id != "ERROR") {
      $error_string .= "<p>Found wrong control: {$control_id}, course " . ltrim($course, "0..9-") . ", control #" . ($number_controls_found + 1) .
                            ", expected control " . $control_list[$number_controls_found][0] . "\n";
      $extra_control_string = strval(time()) . ",{$control_id}\n";
      file_put_contents($competitor_path . "/extra", $extra_control_string, FILE_APPEND);
      // echo "<p>This looks like it also wasn't the prior control\n";
    }
  }
  else {
    // echo"<p>This looks like a rescan of the prior control.\n";
    $remaining_controls = count($control_list) - $number_controls_found;
    if ($remaining_controls <= 0) {
      $next_control = "Finish";
    }
    else {
      $next_control = $control_list[$number_controls_found][0];
    }
  $control_number_for_printing = $number_controls_found;
  }
}
else {
  $control_found_filename = "{$controls_found_path}/" . strval(time()) . ",{$control_id}";
  file_put_contents($control_found_filename, "");
  $remaining_controls = count($control_list) - $number_controls_found - 1;
  if ($remaining_controls <= 0) {
    $next_control = "Finish";
  }
  else {
    $next_control = $control_list[$number_controls_found + 1][0];
  }
  $control_number_for_printing = $number_controls_found + 1;
  // echo "<p>Saved to the file ${competitor_path}/${number_controls_found}.\n";
}

echo get_web_page_header(true, false, false);

if ($error_string == "") {
  echo "<p>Correct!  Reached {$control_id}, control #{$control_number_for_printing} on " . ltrim($course, "0..9-") . "\n";
  echo "<p>{$remaining_controls} more to find, next is {$next_control}.\n";
}
else {
  echo "<p>ERROR: {$error_string}\n";
}
echo "<br><p>Time on course is: " . formatted_time($time_on_course) . "\n";

echo get_web_page_footer();
?>
