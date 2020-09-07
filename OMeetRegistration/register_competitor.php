<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$competitor_name = $_GET["competitor_name"];
$course = $_GET["course"];

if (isset($_GET["registration_info"])) {
  $registration_info_supplied = true;
  $raw_registration_info = $_GET["registration_info"];
  $registration_info = parse_registration_info($raw_registration_info);
}
else {
  $registration_info_supplied = false;
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
// print_r($courses_array);
// echo "<p>\n";

$body_string = "";
$using_si_stick = false;

// Validate the info
$error = false;
if (!in_array($course, $courses_array)) {
  $body_string .= "<p>ERROR: Course must be specified.\n";
  $error = true;
}

if ($competitor_name == "") {
  $body_string .= "<p>ERROR: Competitor name must be specified.\n";
  $error = true;
}

// Input information is all valid, save the competitor information
if (!$error) {
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
        $member_ids = array_map(function ($elt) { return (explode(":", $elt)); }, explode(",", $_COOKIE["member_ids"]));
        $member_ids_hash = array();
        array_map(function ($elt) use (&$member_ids_hash, $time_cutoff)
                     { if ($elt[1] > $time_cutoff) { $member_ids_hash[$elt[0]] = $elt[1]; } }, $member_ids);
        $member_ids_hash[$registration_info["member_id"]] = $current_time;
        $member_cookie = implode(",", array_map (function ($elt) use ($member_ids_hash) { return($elt . ":" . $member_ids_hash[$elt]); }, array_keys($member_ids_hash)));
        setcookie("member_ids", $member_cookie, $current_time + 86400 * 120, $cookie_path);
      }
    }
    
    if (!$using_si_stick) {
      // Set the cookies with the name, course, next control
      $timeout_value = $current_time + 3600 * 6;  // 6 hour timeout, should be fine for most any course
      setcookie("competitor_id", $competitor_id, $timeout_value, $cookie_path);
      setcookie("course", $course, $timeout_value, $cookie_path);
      setcookie("event", $_GET["event"], $timeout_value, $cookie_path);
      setcookie("key", $key, $timeout_value, $cookie_path);
    }
  }
}

if (!$error) {
  set_success_background();
}
else {
  set_error_background();
}

echo get_web_page_header(true, false, true);

echo $body_string;

if (!$error) {
  if ($using_si_stick) {
    echo "<p>To start the course, clear and check your SI stick, then proceed to the start control with your SI stick.\n";
  }
  else {
    echo "<p>To start the course, please proceed to start and scan the start QR code there or click the \"Start course\" button below to start now.\n";
    echo "<p><form action=\"../OMeet/start_course.php\"> <input type=\"submit\" value=\"Start course\"> </form>\n";
  }
}

echo get_web_page_footer();
?>
