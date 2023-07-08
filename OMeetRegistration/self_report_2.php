<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/results_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";

// Make sure any funky HTML sequeneces in the name are escaped
$competitor_name = htmlentities($_GET["competitor_name"]);
$course = $_GET["course"];
if (isset($_GET["competitor"])) {
  $competitor = $_GET["competitor"];
}
else {
  $competitor = "";
}


$key = $_GET["key"];
$event = $_GET["event"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"{$key}\", are you using an authorized link?\n");
}

if (!is_dir(get_event_path($event, $key, "..")) || !file_exists(get_event_path($event, $key, "..") . "/description")) {
  error_and_exit("Unknown event \"{$event}\" (" . get_base_path($event, $key, "..") . "), are you using an authorized link?\n");
}


$courses_array = scandir(get_courses_path($event, $key, ".."));
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries

// Validate the info
if (!in_array($course, $courses_array)) {
  error_and_exit("<p>ERROR: Course must be specified.\n");
}

if ($competitor_name == "") {
  error_and_exit("<p>ERROR: Competitor name must be specified.\n");
}

$reported_time = trim($_GET["reported_time"]);
$is_a_dnf = !isset($_GET["found_all"]);
$scoreo_score = $_GET["scoreo_score"];

$time_for_results = 0;
if ($reported_time == "none") {
  $time_for_results = 86400 * 2;  // 2 days as a sentinel, should be plenty for a normal course
}
else {
  $time_for_results = time_limit_to_seconds($reported_time);
  if ($time_for_results == -1) {
    error_and_exit("<p>ERROR: Incorrectly formatted time \"{$reported_time}\", must be of the format XXhYYmZZs for XX hours, YY minutes, ZZ seconds.\n");
  }
}

$courses_path = get_courses_path($event, $key, "..");
$results_path = get_results_path($event, $key, "..");
if (!file_exists("{$courses_path}/{$course}/controls.txt")) {
  error_and_exit("<p>ERROR: Event \"{$event}\" appears to be no longer appears valid, please try again.\n");
}

$course_properties = get_course_properties("{$courses_path}/{$course}");
$score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
$score_penalty_msg = "";
if ($score_course) {
  $max_score = $course_properties[$MAX_SCORE_FIELD];
  $scoreo_score = trim($scoreo_score);
  // Penalties are calculated later, so the reported score must be >= 0
  if (!preg_match("/^[0-9]+$/", $scoreo_score)) {
    error_and_exit("<p>ERROR: Score value \"{$scoreo_score}\" appears to contain non-numeric characters, please try again.\n");
  }

  if ($scoreo_score > $max_score) {
    error_and_exit("<p>ERROR: Course score \"{$scoreo_score}\" larger than course maximum: {$max_score}.\n");
  }

  if (($course_properties[$LIMIT_FIELD] > 0) && ($time_for_results > $course_properties[$LIMIT_FIELD]) && ($reported_time != "none")) {
    $time_over = $time_for_results - $course_properties[$LIMIT_FIELD];
    $minutes_over = floor(($time_over + 59) / 60);
    $penalty = $minutes_over * $course_properties[$PENALTY_FIELD];

    $score_penalty_msg = "<p>Exceeded time limit of " . formatted_time($course_properties[$LIMIT_FIELD]) . " by " . formatted_time($time_over) . "\n" .
                         "<p>Penalty is {$course_properties[$PENALTY_FIELD]} pts/minute, total penalty of $penalty points.\n" .
                         "<p>Control score was $scoreo_score -> " . ($scoreo_score - $penalty) . " after penalty.\n";

    $scoreo_score -= $penalty;
  }

  // For a ScoreO, there are no DNFs, so clear the dnf flag
  $is_a_dnf = false;
}
else {
  // For a non-ScoreO, each control is 1 point
  $control_list = read_controls("{$courses_path}/{$course}/controls.txt");
  $max_score = count($control_list);
  if (!$is_a_dnf) {
    $scoreo_score = $max_score;
  }
  else {
    // For a self-reported DNF, assume they found no controls for the sake of argument
    $scoreo_score = 0;
  }
}

$is_a_replay = false;
if (isset($_GET["setcookie"])) {
  $cookie_unique_id = $_GET["setcookie"];
  $cookie_name = "SelfReportReplayProtectCookie-{$cookie_unique_id}";
  $cookie_value="{$competitor_name},{$course},{$time_for_results}," . ($is_a_dnf ? "DNF" : "OK") . ",{$scoreo_score}";
  if (isset($_COOKIE[$cookie_name]) && ($_COOKIE[$cookie_name] == $cookie_value)) {
    $is_a_replay = true;
  }
}


$force_new_competitor_id = false;
// If the competitor has punches already, then we need to make a new competitor id for recording the results
// This can only happen when editing punches for a competitor and changing it to a self-reported time
if ($competitor != "") {
    $competitor_path = get_competitor_path($competitor, $event, $key);
    $controls_found_path = "{$competitor_path}/controls_found";
    if (!is_dir($competitor_path) || !is_dir($controls_found_path)) {
      error_and_exit("Unknown competitor {$competitor} with name {$competitor_name}, was the entry already removed?\n");
    }

    $controls_found = scandir($controls_found_path);
    $controls_found = array_diff($controls_found, array(".", "..", "start", "finish"));
    if (count($controls_found) != 0) {
      $force_new_competitor_id = true;
    }
    else {
      // The competitor has not visited ANY controls, just remove any start or finish entry and mark this as self-timed
      if (file_exists("{$controls_found_path}/start")) {
        unlink("{$controls_found_path}/start");
      }

      if (file_exists("{$controls_found_path}/finish")) {
        unlink("{$controls_found_path}/finish");
      }
    }
}

// Generate the competitor_id and make sure it is truly unique
// Unless this is for an existing competitor...
$tries = 0;
if (!$is_a_replay) {
  if (($competitor == "") || $force_new_competitor_id) {
    while ($tries < 5) {
      $competitor_id = uniqid();
      $competitor_path = get_competitor_path($competitor_id, $event, $key);
      mkdir ($competitor_path, 0777);
      $competitor_file = fopen($competitor_path . "/name", "x");
      if ($competitor_file !== false) {
        break;
      }
      $tries++;
    }
  }
  else {
    $competitor_id = $competitor;
  }
}

if ($tries === 5) {
  error_and_exit("<p>Temporary error recording results, please retry.\n");
}
else if (!$is_a_replay) {
  // Save the information about the competitor
  if (($competitor == "")  || $force_new_competitor_id) {
    fwrite($competitor_file, $competitor_name);
    fclose($competitor_file);
    file_put_contents("{$competitor_path}/course", $course);
  }
  file_put_contents("{$competitor_path}/self_reported", "");
  if (!file_exists("{$competitor_path}/controls_found")) {
    mkdir("{$competitor_path}/controls_found");
  }
  
  // Mark as a DNF if not all the controls were found
  if ($is_a_dnf) {
    file_put_contents("{$competitor_path}/dnf", "self reported DNF", FILE_APPEND);
  }

  if ($reported_time == "none") {
    file_put_contents("{$competitor_path}/no_time", "no time given", FILE_APPEND);
  }

  // If this is the first result for the course, create the directory
  if (!file_exists("{$results_path}/{$course}")) {
    mkdir("{$results_path}/{$course}");
  }

  $result_filename = sprintf("%04d,%06d,%s", $max_score - $scoreo_score, $time_for_results, $competitor_id);
  file_put_contents("{$results_path}/{$course}/{$result_filename}", "");
}


// Guard against someone who hits reload on this page - don't add the entry a second time
if (isset($_GET["setcookie"])) {
  $cookie_path = isset($_SERVER["REQUEST_URI"]) ? dirname(dirname($_SERVER["REQUEST_URI"])) : "";
  $current_time = time();
  setcookie($cookie_name, $cookie_value, $current_time + (3600 * 2), $cookie_path);
}


echo get_web_page_header(true, true, false);


if (!$is_a_replay) {
  $dnf_string = "";
  if (file_exists("${competitor_path}/dnf")) {
    $dnf_string = " - DNF";
  }

  $competitor_name = file_get_contents("{$competitor_path}/name");
  $readable_course_name = ltrim($course, "0..9-");
  $results_string = "<p class=\"title\">Results for: {$competitor_name}, course complete ({$readable_course_name}{$dnf_string}), time taken " . formatted_time($time_for_results) . "<p><p>";
  echo "{$results_string}\n";
  if ($score_course && ($score_penalty_msg != "")) {
    echo $score_penalty_msg;
  }
}

if ($force_new_competitor_id) {
  echo "<p><p>Converted competitor {$competitor_name} to self reported time despite partial or full course completion, old entry must be manually removed.\n";
  echo "<p><a href=\"../OMeetMgmt/remove_from_event.php?event={$event}&key={$key}&Remove-{$competitor}=1\">Remove original {$competitor_name} entry</a><p><p>\n";
}

echo show_results($event, $key, $course, "", $score_course, $max_score, array());
echo get_all_course_result_links($event, $key);


echo "<p><p>Want to <a href=\"https://www.newenglandorienteering.org/meet-directors/142-mark-o-connell\">give feedback?</a>  All comments welcome.\n";

echo get_web_page_footer();
?>
