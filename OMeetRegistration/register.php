<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, true);


// Get some phpinformation, just in case
// Verify that php is running properly
// echo 'Current PHP version: ' . phpversion();
// phpinfo();

function is_event($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("${base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function name_to_link($event_id) {
  global $raw_registration_info, $registration_info_supplied, $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");

  if ($registration_info_supplied) {
    return ("<li><a href=./register.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
  }
  else {
    return ("<li><a href=./register.php?event={$event_id}&key={$key}&registration_info={$raw_registration_info}>{$event_fullname}</a>\n");
  }
}

echo "<p>\n";

if (isset($_GET["registration_info"])) {
  $registration_info_supplied = true;
  $raw_registration_info = $_GET["registration_info"];
  $registration_info = parse_registration_info($raw_registration_info);
}
else {
  $registration_info_supplied = false;
}

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key, "..");

$event = $_GET["event"];
//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
if (strcmp($event, "") == 0) {
  $event_list = scandir($base_path);
  //print_r($event_list);
  $event_list = array_filter($event_list, is_event);
  //print_r($event_list);
  if (count($event_list) == 1) {
    $event = basename(current($event_list));
    //echo "Identified event as ${event}\n<p>";
  }
  else if (count($event_list) > 1) {
    $event_output_array = array_map(name_to_link, $event_list);
    echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
    return;
  }
  else {
    echo "<p>No available events.\n";
    return;
  }
}

$courses_array = scandir(get_courses_path($event, $key, ".."));
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
// print_r($courses_array);
echo "<p>\n";

echo "<p>Registration for orienteering event: ${event}\n<br>";
echo "<form action=\"./register_competitor.php\">\n";

if ($registration_info_supplied) {
  echo "<br><p>Welcome:<br>\n";
  echo "<input type=\"text\" name=\"competitor_name\" value=\"{$registration_info["first_name"]} {$registration_info["last_name"]}\" readonly><br>\n";
  echo "<input type=\"hidden\" name=\"registration_info\" value=\"{$raw_registration_info}\">\n";
}
else {
  echo "<br><p>What is your name?<br>\n";
  echo "<input type=\"text\" name=\"competitor_name\"><br>\n";
}
echo "<input type=\"hidden\" name=\"event\" value=\"{$event}\">\n";
echo "<input type=\"hidden\" name=\"key\" value=\"{$key}\">\n";

echo "<br><p>Select a course:<br>\n";
foreach ($courses_array as $course_name) {
  echo "<input type=\"radio\" name=\"course\" value=\"" . $course_name . "\">" . ltrim($course_name, "0..9-") . " <br>\n";
}

echo "<input type=\"submit\" value=\"Submit\">\n";
echo "</form>";

echo "<p><a href=\"../OMeet/view_results?event={$event}&key={$key}\">View results</a>";
echo "<p><a href=\"../OMeet/on_course?event={$event}&key={$key}\">Out on course</a><p>";


echo get_web_page_footer();
?>
