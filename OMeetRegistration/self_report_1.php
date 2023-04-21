<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();




function is_event($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("${base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function name_to_link($event_id) {
  global $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./self_report_1.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
}


$default_name = "";
$default_email = "";

$key = isset($_GET["key"]) ? $_GET["key"] : "";
$event = isset($_GET["event"]) ? $_GET["event"] : "";
// Only translate the key if no event is specified - otherwise the key should be correct already
if ($event == "") {
    $key = translate_key($key);
}
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

echo get_web_page_header(true, false, true);
echo "<p>\n";

$base_path = get_base_path($key, "..");

//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
if ($event == "") {
  $event_list = scandir($base_path);
  //print_r($event_list);
  $event_list = array_filter($event_list, "is_event");
  //print_r($event_list);
  if (count($event_list) == 1) {
    $event = basename(current($event_list));
    //echo "Identified event as ${event}\n<p>";
  }
  else if (count($event_list) > 1) {
    $event_output_array = array_map('name_to_link', $event_list);
    echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
    return;
  }
  else {
    echo "<p>No available events.\n";
    return;
  }
}

// Do we allow self-reporting for completed events???
if (file_exists("{$base_path}/{$event}/done")) {
  error_and_exit("Event " . file_get_contents("{$base_path}/{$event}/description") . " has completed and self-reporting is no longer possible.\n");
}

if (file_exists("{$base_path}/{$event}/no_self_reporting")) {
  error_and_exit("Event " . file_get_contents("{$base_path}/{$event}/description") . " does not allow self-reporting.\n");
}

$courses_path = get_courses_path($event, $key);
$courses_array = scandir($courses_path);
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries

if (isset($_GET["competitor"])) {
  $competitor = $_GET["competitor"];
  $competitor_path = get_competitor_path($competitor, $event, $key);
  if (!is_dir($competitor_path)) {
    error_and_exit("<p>ERROR: No such competitor found {$competitor} (possibly already removed or edited?).\n");
  }

  $default_name = file_get_contents("{$competitor_path}/name");

  $course = file_get_contents("{$competitor_path}/course");
}
// print_r($courses_array);
echo "<p>\n";

echo "<p class=title>Self-report a result for orienteering event: " . file_get_contents("{$base_path}/{$event}/description") . "\n<br>";
echo "<form action=\"./self_report_2.php\">\n";

echo "<br><p class=title>What is your name?</p><br>\n";
echo "<input type=\"text\" size=30 name=\"competitor_name\" value=\"{$default_name}\"" . (isset($_GET["competitor"]) ? " readonly" : "") . "><br>\n";

echo "<input type=\"hidden\" name=\"event\" value=\"{$event}\">\n";
echo "<input type=\"hidden\" name=\"key\" value=\"{$key}\">\n";

echo "<hr>\n";

if (!isset($_GET["competitor"])) {
  echo "<br><p class=title>Select a course:</p><br>\n";
  foreach ($courses_array as $course_name) {
    if (!file_exists("{$courses_path}/{$course_name}/removed") && !file_exists("{$courses_path}/{$course_name}/no_registrations")) {
      echo "<p><input type=\"radio\" name=\"course\" value=\"" . $course_name . "\">" . ltrim($course_name, "0..9-") . " <br>\n";
    }
  }
  echo "<input type=hidden name=setcookie value=\"" . time() . "\">\n";
}
else {
  echo "<input type=hidden name=course value=\"{$course}\">\n";
  echo "<input type=hidden name=competitor value=\"{$competitor}\">\n";
}

echo "<hr>\n";
echo "<br><p class=title>What was your time?</p><br>\n";
echo "<br>Format is XXhXXmXXs, e.g. 1h32m48s, or 92m48s.<br>\n";
echo "<br>Use \"none\" to report having done the course without reporting a time.<br>\n";
echo "<input type=\"text\" size=30 name=\"reported_time\"><br>\n";

echo "<p><input type=checkbox name=\"found_all\" checked> I found all the controls (if unchecked, will be a DNF)<br>\n";

echo "<hr>\n";
echo "<br><p>If a ScoreO, what was your score?</p><br>\n";
echo "<br>Note: Enter only sum of points for controls visited without regard to ";
echo "penalties for exceeding the time limit, time penalties will be automatically calculated and deducted.\n";
echo "<input type=\"text\" size=30 name=\"scoreo_score\"><br>\n";



echo "<p><input type=\"submit\" value=\"Submit self-reported result\">\n";
echo "</form>";


echo get_web_page_footer();
?>
