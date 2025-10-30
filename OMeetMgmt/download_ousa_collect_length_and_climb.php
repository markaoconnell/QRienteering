<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

set_page_title("OUSA results collect info");

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

require '../OMeetCommon/course_properties.php';
require '../OMeetMgmt/event_mgmt_common.php';

$event_created = false;
$found_error = false;

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key, ".."))) {
  error_and_exit("No directory found for events, is your key \"{$key}\" valid?\n");
}

$event = $_GET["event"];
$event_path = get_event_path($event, $key, "..");
if (!is_dir($event_path)) {
  error_and_exit("No event directory found, is \"{$event}\" from a valid link?\n");
}

$courses_path = get_courses_path($event, $key);

// Show the interface to add / remove the courses, even if we've just removed some
$current_courses = scandir($courses_path);
$current_courses = array_diff($current_courses, array(".", ".."));

// Get the text to display for the course information
$valid_courses = array_filter($current_courses, function ($elt) use ($courses_path) { return(!file_exists("{$courses_path}/{$elt}/removed")); });
$valid_courses = array_map(function ($elt) { return (ltrim($elt, "0..9-") . ","); }, $valid_courses);

$current_event_name = file_get_contents("{$event_path}/description");

echo "<p>Add course length and climb information: <strong>{$current_event_name}</strong>\n";
echo "<p>After each course, add the length and climb, comma separated.\n";
echo "<p>Warning - no validation is done on these fields, they are copied directly into the OUSA result .csv.\n";
echo "<p>For invalid courses, either leave the line blank or remove the line.\n";
echo "<form action=\"./download_results_ousacsv.php\" method=post>\n";
echo "<input type=\"hidden\" name=\"key\" value=\"{$key}\" />\n";
echo "<input type=\"hidden\" name=\"event\" value=\"{$event}\" />\n";

echo "<textarea name=\"course_info\" rows=\"" . count($valid_courses) . "\" cols=30>\n";
echo implode("\n", $valid_courses);
echo "</textarea>\n";


echo "<p><input type=submit name=\"submit\" value=\"Get OUSA results file\">\n";
echo "</form>\n";
echo get_web_page_footer();
?>
