<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

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
$results_string = "";
if (isset($_GET["submit"])) {
  foreach (array_keys($_GET) as $get_key) {
    $pieces = explode(":", $get_key);
    if ($pieces[0] == "remove") {
      if (!file_exists("{$courses_path}/{$pieces[1]}/removed")) {
        touch("{$courses_path}/{$pieces[1]}/removed");
        $results_string .= "<p>Course {$pieces[1]} is no longer valid.\n";
      }
      else {
        $results_string .= "<p>Course {$pieces[1]} was already marked invalid.\n";
      }
    }
    else if ($pieces[0] == "readd") {
      if (file_exists("{$courses_path}/{$pieces[1]}/removed")) {
        unlink("{$courses_path}/{$pieces[1]}/removed");
        $results_string .= "<p>Course {$pieces[1]} is valid again.\n";
      }
      else {
        $results_string .= "<p>Course {$pieces[1]} was already valid.\n";
      }
    }
  }
}

// Show the interface to add / remove the courses, even if we've just removed some
$current_courses = scandir($courses_path);
$current_courses = array_diff($current_courses, array(".", ".."));

// Determine which courses have been removed already vs which are currently valid
// Show the checkboxes differently for the two cases
$valid_courses = array_filter($current_courses, function ($elt) use ($courses_path) { return(!file_exists("{$courses_path}/{$elt}/removed")); });
$invalid_courses = array_filter($current_courses, function ($elt) use ($courses_path) { return(file_exists("{$courses_path}/{$elt}/removed")); });
$valid_courses = array_map(function ($elt) { return ("<li><input type=checkbox name=\"remove:${elt}\">" . ltrim($elt, "0..9-")); }, $valid_courses);
$invalid_courses = array_map(function ($elt) { return ("<li><input type=checkbox name=\"readd:{$elt}\">" . ltrim($elt, "0..9-")); }, $invalid_courses);

$current_event_name = file_get_contents("{$event_path}/description");

echo "<p>Manipulate valid courses for: <strong>{$current_event_name}</strong>\n";
if ($results_string != "") {
  echo "<p>{$results_string}\n";
}
echo "<form action=\"./remove_course_from_event.php\">\n";
echo "<input type=\"hidden\" name=\"key\" value=\"{$key}\" />\n";
echo "<input type=\"hidden\" name=\"event\" value=\"{$event}\" />\n";

if (count($valid_courses) > 0) {
  echo "<p><p>Remove one or more courses\n<br><ul>\n";
  echo implode("\n", $valid_courses);
  echo "</ul>\n";
}

if (count($invalid_courses) > 0) {
  echo "<p><p>Re-add one or more already removed courses\n<br><ul>\n";
  echo implode("\n", $invalid_courses);
  echo "</ul>\n";
}


echo "<p><input type=submit name=\"submit\" value=\"Change course status\">\n";
echo "</form>\n";
echo get_web_page_footer();
?>
