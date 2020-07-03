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
      }

      if ($registration_info["is_member"] == "yes") {
        // Two month timeout for the cookie about the member's name, should generally be sufficient
        setcookie("member_first_name", $registration_info["first_name"], $current_time + 86400 * 60, $cookie_path);
        setcookie("member_last_name", $registration_info["last_name"], $current_time + 86400 * 60, $cookie_path);
      }
    }
    
    // Set the cookies with the name, course, next control
    $timeout_value = $current_time + 3600 * 6;  // 6 hour timeout, should be fine for most any course
    setcookie("competitor_id", $competitor_id, $timeout_value, $cookie_path);
    setcookie("course", $course, $timeout_value, $cookie_path);
    setcookie("event", $_GET["event"], $timeout_value, $cookie_path);
    setcookie("key", $key, $timeout_value, $cookie_path);
  }
}

echo get_web_page_header(true, false, false);

echo $body_string;

echo get_web_page_footer();
?>
