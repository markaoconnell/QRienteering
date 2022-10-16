<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$course = $_COOKIE["course"];
$competitor_id = $_COOKIE["competitor_id"];
$event = $_COOKIE["event"];
$key = $_COOKIE["key"];
$error_string = "";

if (($key == "") && redirect_to_secure_http_if_no_key_cookie() && !isset($_SERVER["HTTPS"])) {
  echo "<html><head><meta http-equiv=\"refresh\" content=\"0; URL=https://{$_SERVER["SERVER_NAME"]}{$_SERVER["REQUEST_URI"]}\" /></head></html>";
  return;
}

if (($event == "") || ($competitor_id == "")) {
  error_and_exit("<p>Event and competitor cookies not found - possible causes:\n" .
	         "<ul><li>You did not scan the registration QR code and complete the registration process.\n" .
		 "<li>You are using a browser in incognito (or private) mode, which is not supported - please switch your browser mode.\n" .
		 "</ul><p><p>For questions, please talk to a club member (if any are available)\n" .
		 "<p>Details of error: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?" . get_error_info_string());
}

if (!key_is_valid($key)) {
  error_and_exit("<p>ERROR: Unknown location key \"{$key}\", is this an authorized link?" . get_error_info_string());
}

$event_path = get_event_path($event, $key, "..");

if (!is_dir($event_path) || !file_exists("{$event_path}/description")) {
  error_and_exit("<p>ERROR: Bad event \"{$event}\", was this created properly?" . get_error_info_string());
}

$competitor_path = get_competitor_path($competitor_id, $event, $key, ".."); 
$controls_found_path = "{$competitor_path}/controls_found";
// $control_list = file("./${event}/Courses/${course}");

$event_fullname = file_get_contents("{$event_path}/description");

if (file_exists("{$competitor_path}/name")) {
  $competitor_name = file_get_contents("{$competitor_path}/name");
}
else {
  error_and_exit("<p>ERROR: Bad registration for event \"{$event_fullname}\" and competitor \"{$competitor_id}\", please reregister and try again?");
}

if (file_exists("{$competitor_path}/si_stick")) {
  error_and_exit("<p>ERROR: {$competitor_name} registered for {$event_fullname} with SI unit, should not scan start QR code.");
}

if (file_exists("{$controls_found_path}/start")) {
  // If no controls have been found yet, then just update the start time
  // Remember that the control found could have been one not on the course
  $controls_done = scandir("{$controls_found_path}");
  $controls_done = array_diff($controls_done, array(".", "..", "start"));
  if ((count($controls_done) != 0) || file_exists("{$competitor_path}/extra")) {
    $error_string = "Course " . ltrim($course, "0..9-") . " already started for {$competitor_name}.";
  }
  else {
    file_put_contents("{$controls_found_path}/start", strval(time()));
  }
}
else {
  file_put_contents("{$controls_found_path}/start", strval(time()));
}

if ($error_string == "") {
  set_success_background();
}
else {
  set_error_background();
}

echo get_web_page_header(true, false, false);

if ($error_string == "") {
  echo "<p>" . ltrim($course, "0..9-") . " course started for ${competitor_name}.\n";
}
else {
  echo "<p>ERROR: ${error_string}\n<br>Course not started.";
}

echo get_web_page_footer();
?>
