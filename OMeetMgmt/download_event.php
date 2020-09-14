<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

require '../OMeetCommon/course_properties.php';
require '../OMeetMgmt/event_mgmt_common.php';


$key = $_GET["key"];
$event = $_GET["event"];

if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

$event_path = get_event_path($event, $key, "..");
if (!is_dir($event_path)) {
  error_and_exit("Event not found, is \"{$key}\" and \"{$event}\" a valid pair?\n");
}

$current_event_name = file_get_contents("{$event_path}/description");
$path_to_courses = get_courses_path($event, $key, "..");
$current_courses = scandir($path_to_courses);
$current_courses = array_diff($current_courses, array(".", ".."));

$event_description_string = "";
foreach ($current_courses as $this_course) {
  $control_list = read_controls("{$path_to_courses}/{$this_course}/controls.txt");

  $course_properties = get_course_properties("{$path_to_courses}/{$this_course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));

  if ($score_course) {
    $event_description_string .= "<p>s:" . ltrim($this_course, "0..9-") . ":" . $course_properties[$LIMIT_FIELD] . "s:" . $course_properties[$PENALTY_FIELD] . "," .
                                 implode(",", array_map(function ($elt) { return ($elt[0] . ":" . $elt[1]); }, $control_list)) . "\n";
  }
  else {
    $event_description_string .= "<p>l:" . ltrim($this_course, "0..9-") . "," .
                                  implode(",", array_map(function ($elt) { return ($elt[0]); }, $control_list)) . "\n";
  }
}


echo "<p>Event name:<p>{$current_event_name}<p>\n";
echo "<p>{$event_description_string}\n";


echo get_web_page_footer();
?>
