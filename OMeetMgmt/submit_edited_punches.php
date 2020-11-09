<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$competitor = $_GET["competitor"];
$allow_editing = isset($_GET["allow_editing"]);

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, no results can be shown.\n");
}

$competitor_path = get_competitor_path($competitor, $event, $key);
$courses_path = get_courses_path($event, $key);
if (!file_exists($courses_path) || !is_dir($competitor_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

$competitor_name = file_get_contents("{$competitor_path}/name");
$course = file_get_contents("{$competitor_path}/course");
$start_time = file_get_contents("{$competitor_path}/controls_found/start");
$finish_time = file_get_contents("{$competitor_path}/controls_found/finish");

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
    $final_punch_array[$new_punch_iterator] = array("timestamp" => $additional_pieces[0] + $start_time, "control_id" => $additional_pieces[1]);
  }
  else {
    $error_string .= "<p>Additional control ignored, incorrectly formatted, was: {$_GET["additional"]}, should be timestamp,control, all numeric.\n";
  }
}


$final_punch_entries = array_map(function ($elt) { return ("{$elt["timestamp"]},{$elt["control_id"]}"); }, $final_punch_array);
sort($final_punch_entries);

$output_string = "<p>Punches for {$competitor_name} on " . ltrim($course, "0..9-") . "\n";

$output_string .= "<p>Start at: {$start_time}\n";
$output_string .= "<ul>\n<li>\n";
$output_string .= implode("\n<li>", $final_punch_entries);
$output_string .= "</ul>\n";
$output_string .= "<p>Finish at: {$finish_time}\n";

if ($error_string != "") {
  $output_string .= $error_string;
}


// ###########################################
// Input information is all valid, save the competitor information
if (0) {
  // Generate the competitor_id and make sure it is truly unique
  $tries = 0;
  while ($tries < 5) {
    $competitor_id = uniqid();
    $competitor_path = get_competitor_path($competitor_id, $event, $key, "..");
    mkdir ($competitor_path, 0777);
    $competitor_file = fopen($competitor_path . "/name", "x");
    if ($competitor_file !== false) {
      break;
    }
    $tries++;
  }

  if ($tries === 5) {
    $body_string .= "ERROR Cannot register " . $competitor_name . " with id: " . $competitor_id . "\n";
    $error = true;
  }
  else {
    $body_string .= "<p>Registration complete: " . $competitor_name . " on " . ltrim($course, "0..9-");

    $cookie_path = dirname(dirname($_SERVER["REQUEST_URI"]));

    // Save the information about the competitor
    fwrite($competitor_file, $competitor_name);
    fclose($competitor_file);
    file_put_contents($competitor_path . "/course", $course);
    mkdir("./{$competitor_path}/controls_found");

    $current_time = time();

    if ($registration_info_supplied) {
      file_put_contents("{$competitor_path}/registration_info", $raw_registration_info);
      if ($registration_info["si_stick"] != "") {
        file_put_contents("{$competitor_path}/si_stick", $registration_info["si_stick"]);
        $using_si_stick = true;
      }

      if (($registration_info["is_member"] == "yes") && ($registration_info["member_id"] != "")) {
        // Format will be member_id:timestamp_of_last_registration,member_id:timestamp_of_last_registration,...
        // 3 month timeout
        $time_cutoff = $current_time - (86400 * 90);
        $member_ids = array_map(function ($elt) { return (explode(":", $elt)); }, explode(",", $_COOKIE["{$key}-member_ids"]));
        $member_ids_hash = array();
        array_map(function ($elt) use (&$member_ids_hash, $time_cutoff)
                     { if ($elt[1] > $time_cutoff) { $member_ids_hash[$elt[0]] = $elt[1]; } }, $member_ids);
        $member_ids_hash[$registration_info["member_id"]] = $current_time;
        $member_cookie = implode(",", array_map (function ($elt) use ($member_ids_hash) { return($elt . ":" . $member_ids_hash[$elt]); }, array_keys($member_ids_hash)));
        setcookie("{$key}-member_ids", $member_cookie, $current_time + 86400 * 120, $cookie_path);
      }
    }
  }
}

// ###################################

echo get_web_page_header(true, true, false);

echo $output_string;

echo get_web_page_footer();
?>
