<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';
require 'si_stick_finish.php';

ck_testing();

//function calculate_score($controls_found, $controls_points_hash) {
//  $total_score = 0;
//  $controls_found_list = array();
//
//  foreach ($controls_found as $entry) {
//    // If someone scans a control multiple times, only count it once
//    if (!isset($controls_found_list[$entry])) {
//      $controls_found_list[$entry] = 1;
//      $total_score += $controls_points_hash[$entry];
//    }
//  }
//
//  return($total_score);
//}

// Get the submitted info
// echo "<p>\n";
if (isset($_GET["si_stick_finish"])) {
  if (!isset($_GET["event"]) || ($_GET["event"] == "")) {
    error_and_exit("ERROR: Cannot find competitor for registered stick {$finish_info["si_stick"]}: No event set.\n");
  }

  $event = $_GET["event"];
  $key = $_GET["key"];
  $si_results_string = base64_decode($_GET["si_stick_finish"]);
  $finish_info = record_finish_by_si_stick($event, $key, $si_results_string);

  if ($finish_info["error"] != "") {
    error_and_exit("ERROR: Cannot find competitor for registered stick {$finish_info["si_stick"]}: {$finish_info["error"]}\n");
  }

  $course = $finish_info["course"];
  $competitor_id = $finish_info["competitor_id"];
  $finish_time = $finish_info["finish_time"];
}
else {
  $course = $_COOKIE["course"];
  $competitor_id = $_COOKIE["competitor_id"];
  $event = $_COOKIE["event"];
  $key = $_COOKIE["key"];
  $finish_time = time();
}

$now = $finish_time;

if (($event == "") || ($competitor_id == "")) {
  error_and_exit("<p>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?" . get_error_info_string());
}

if (!key_is_valid($key)) {
  error_and_exit("Bad location key \"{$key}\", is this an unauthorized link?\n");
}


$competitor_path = get_competitor_path($competitor_id, $event, $key, ".."); 
$controls_found_path = "{$competitor_path}/controls_found";

$courses_path = get_courses_path($event, $key, "..");
$results_path = get_results_path($event, $key, "..");
if (!file_exists($competitor_path) || !file_exists("{$courses_path}/{$course}/controls.txt")) {
  error_and_exit("<p>ERROR: Event \"{$event}\" or competitor \"{$competitor}\" appears to be no longer appears valid, please re-register and try again.\n");
}

if (file_exists("{$competitor_path}/si_stick") && !isset($_GET["si_stick_finish"])) {
  error_and_exit("<p>ERROR: If using si stick, do not scan the finish QR code, use si stick to finish instead.\n");
}

$control_list = read_controls("${courses_path}/${course}/controls.txt");
$controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                      array_map(function ($element) { return $element[1]; }, $control_list));

$course_properties = get_course_properties("{$courses_path}/{$course}");
$score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
if ($score_course) {
  $max_score = $course_properties[$MAX_SCORE_FIELD];
}
else {
  // For a non-ScoreO, each control is 1 point
  $max_score = count($control_list);
}

//echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);
$error_string = "";
$result_filename = "";

if (!file_exists("${controls_found_path}/start")) {
  error_and_exit("<p>Course " . ltrim($course, "0..9-") . " not yet started.\n<br>Please scan the start QR code to start a course.\n");
}

if (!file_exists("{$controls_found_path}/finish")) {
  // See how many controls have been completed
  $controls_done = scandir("./${controls_found_path}");
  $controls_done = array_diff($controls_done, array(".", "..", "start", "finish")); // Remove the annoying . and .. entries
  // echo "<br>Controls done on the ${course} course.<br>\n";
  // print_r($controls_done);
  
  if (!$score_course) {
    // Are we at the right control?
    $number_controls_found = count($controls_done);
    $number_controls_on_course = count($control_list);
    // echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found][0] . "--\n";
    if ($number_controls_found != $number_controls_on_course) {
        $error_string .= "<p>Not all controls found, found ${number_controls_found} controls, expected ${number_controls_on_course} controls.\n";
        file_put_contents("{$competitor_path}/dnf", $error_string, FILE_APPEND);
    }
  }
  
  file_put_contents("{$controls_found_path}/finish", strval($now));
  $course_started_at = file_get_contents("{$controls_found_path}/start");
  $time_taken = $now - $course_started_at;
  if (!file_exists("{$results_path}/${course}")) {
    mkdir("{$results_path}/${course}");
  }

  // Just pluck off the controls found (ignore the timestamp for now
  $controls_found = array_map(function ($item) { return (explode(",", $item)[1]); }, $controls_done);

  // For each control, look up its point value in the associative array and sum the total points
  // TODO: Must de-dup the controls found - Don't doublecount the points!!
  if ($score_course) {
    $score_penalty_msg = "";
    $unique_controls = array_unique($controls_found);
    //$total_score = calculate_score($unique_controls, $controls_points_hash);
    $total_score = array_reduce($unique_controls, function ($carry, $elt) use ($controls_points_hash) { return($carry + $controls_points_hash[$elt]); }, 0);
    // Reduce the total_score if over time
    if (($course_properties[$LIMIT_FIELD] > 0) && ($time_taken > $course_properties[$LIMIT_FIELD])) {
      $time_over = $time_taken - $course_properties[$LIMIT_FIELD];
      $minutes_over = floor(($time_over + 59) / 60);
      $penalty = $minutes_over * $course_properties[$PENALTY_FIELD];

      $score_penalty_msg = "<p>Exceeded time limit of " . formatted_time($course_properties[$LIMIT_FIELD]) . " by " . formatted_time($time_over) . "\n" .
                           "<p>Penalty is {$course_properties[$PENALTY_FIELD]} pts/minute, total penalty of $penalty points.\n" .
                           "<p>Control score was $total_score -> " . ($total_score - $penalty) . " after penalty.\n";

      $total_score -= $penalty;
    }
  }
  else {
    $total_score = count($controls_found);
  }

  $result_filename = sprintf("%04d,%06d,%s", $max_score - $total_score, $time_taken, $competitor_id);
  file_put_contents("{$results_path}/${course}/${result_filename}", "");
}
else {
  $error_string .= "<p>Second scan of finish?  Finish time not updated.\n";
  $course_started_at = file_get_contents("{$controls_found_path}/start");
  $course_finished_at = file_get_contents("{$controls_found_path}/finish");
  $time_taken = $course_finished_at - $course_started_at;
}


// Clear the cookies, ready for another course registration
// Set them as expired a day ago
setcookie("competitor_id", $competitor_id, $now - 86400);
setcookie("course", $course, $now - 86400);
setcookie("next_control", "start", $now - 86400);
setcookie("key", $key, $now - 86400);
setcookie("event", $event, $now - 86400);


echo get_web_page_header(true, true, false);

if ($error_string != "") {
  echo "<p>ERROR: ${error_string}\n";
}

$dnf_string = "";
if (file_exists("${competitor_path}/dnf")) {
  echo "<p>ERROR: DNF status.\n";
  $dnf_string = " - DNF";
}

$competitor_name = file_get_contents("{$competitor_path}/name");
$readable_course_name = ltrim($course, "0..9-");
$results_string = "<p class=\"title\">Results for: {$competitor_name}, course complete ({$readable_course_name}{$dnf_string}), time taken " . formatted_time($time_taken) . "<p><p>";
echo "{$results_string}\n";
if ($score_course && ($score_penalty_msg != "")) {
  echo $score_penalty_msg;
}

echo show_results($event, $key, $course, $score_course, $max_score, "..");
echo get_all_course_result_links($event, $key, "..");

// echo "<p>Course started at ${course_started_at}, course finished at ${now}, difference is ${time_taken}.\n";

if (file_exists("{$competitor_path}/registration_info")) {
  $registration_info = parse_registration_info(file_get_contents("{$competitor_path}/registration_info"));
  $email_properties = get_email_properties(get_base_path($key, ".."));
  //print_r($email_properties);
  $email_enabled = isset($email_properties["from"]) && isset($email_properties["reply-to"]);
  if (($registration_info["email_address"] != "") && $email_enabled) {
    // See if this looks like a valid email
    $email_addr = $registration_info["email_address"];
    if (preg_match("/^[a-zA-z0-9_.\-]+@[a-zA-Z0-9_.\-]+/", $email_addr)) {
      $headers = array();
      $headers[] = "From: " . $email_properties["from"];
      $headers[] = "Reply-To: ". $email_properties["reply-to"];
      $headers[] = "MIME-Version: 1.0";
      $headers[] = "Content-type: text/html; charset=iso-8859-1";

      $header_string = implode("\r\n", $headers);

      $course_description = file_get_contents(get_event_path($event, $key, "..") . "/description");
      $email_extra_info_file = get_email_extra_info_file(get_base_path($key, ".."));
      if (file_exists($email_extra_info_file)) {
        $extra_info = implode("\r\n", file($email_extra_info_file));
      }
      else {
        $extra_info = "";
      }
      $body_string = "<html><body>\r\n" .
                     "<p>Orienteering results for\r\n{$course_description}\r\n" .
                     wordwrap("{$results_string}\r\n", 70, "\r\n") . 
                     wordwrap(get_email_course_result_links($event, $key, ".."), 70, "\r\n");

      if (isset($email_properties["include-splits"]) && ($result_filename != "")) {
        $splits_output = get_splits_output($competitor_id, $event, $key, $result_filename);
        $body_string .= wordwrap("<p><p>{$splits_output}\r\n", 70, "\r\n") . "\r\n</body></html>";
      }

      $body_string .= wordwrap("<p><p>{$extra_info}\r\n", 70, "\r\n") . "\r\n</body></html>";
      
      //echo "<p>Mail: Attempting mail send to {$email_addr} with results.\n";
      if (isset($email_properties["subject"])) {
        $subject = $email_properties["subject"];
      }
      else {
        $subject = "Orienteering Results";
      }
      $email_send_result = mail($email_addr, $subject, $body_string, $header_string);

      if ($email_send_result) {
        echo "<p>Mail: Sent results to {$email_addr}.\n";
      }
      else {
        echo "<p>Mail: Failed when sending results to {$email_addr}\n";
      }
    }
    else {
      echo "<p>Mail: {$email_addr} looks fake, no result email attempted.\n";
    }
  }
}

echo "<p><p>Want to <a href=\"https://www.newenglandorienteering.org/meet-directors/142-mark-o-connell\">give feedback?</a>  All comments welcome.\n";

echo get_web_page_footer();
?>
