<?php
require 'common_routines.php';
require 'course_properties.php';
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
  $si_results_string = base64_decode($_GET["si_stick_finish"]);
  $finish_info = record_finish_by_si_stick($event, $si_results_string);

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
  $finish_time = time();
}

$now = $finish_time;

if (($event == "") || ($competitor_id == "")) {
  error_and_exit("<p>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?" . get_error_info_string());
}

$competitor_path = "./" . $event . "/Competitors/" . $competitor_id;
$controls_found_path = "{$competitor_path}/controls_found";

if (!file_exists($competitor_path) || !file_exists("./{$event}/Courses/{$course}/controls.txt")) {
  error_and_exit("<p>ERROR: Event \"{$event}\" or competitor \"{$competitor}\" appears to be no longer appears valid, please re-register and try again.\n");
}

$control_list = read_controls("./${event}/Courses/${course}/controls.txt");
$controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                      array_map(function ($element) { return $element[1]; }, $control_list));

$course_properties = get_course_properties("./{$event}/Courses/{$course}");
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
  if (!file_exists("./${event}/Results/${course}")) {
    mkdir("./${event}/Results/${course}");
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
    if ($time_taken > $course_properties[$LIMIT_FIELD]) {
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
  file_put_contents("./${event}/Results/${course}/${result_filename}", "");
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


echo get_web_page_header(true, true, false);

if ($error_string != "") {
  echo "<p>ERROR: ${error_string}\n";
}

if (file_exists("${competitor_path}/dnf")) {
  echo "<p>ERROR: DNF status.\n";
}

echo "<p class=\"title\">Course complete, time taken " . formatted_time($time_taken) . "<p><p>";
if ($score_course && ($score_penalty_msg != "")) {
  echo $score_penalty_msg;
}

echo show_results($event, $course, $score_course, $max_score);
echo get_all_course_result_links($event);

// echo "<p>Course started at ${course_started_at}, course finished at ${now}, difference is ${time_taken}.\n";

echo get_web_page_footer();
?>
