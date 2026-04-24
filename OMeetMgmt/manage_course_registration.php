<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

require '../OMeetCommon/course_properties.php';
require '../OMeetMgmt/event_mgmt_common.php';

// Check to see if this course is a "container" course for other courses - i.e.
// this course aggregates results from other courses.  This is used for a Motala,
// where the sub-coursess are run in different orders, or for Billygoats, where
// skipping a control is allowed.
function is_combo_course($course) {
  global $event, $key, $courses_path, $TYPE_FIELD, $COMBO_COURSE;

  $props = get_course_properties("{$courses_path}/{$course}");
  return(isset($props[$TYPE_FIELD]) && ($props[$TYPE_FIELD] == $COMBO_COURSE));
}

function set_course_as_autoplace($courses_path, $course_to_set) {
  global $event, $key, $TYPE_FIELD, $COMBO_COURSE, $LINEAR_COURSE, $COMBO_COURSE_LIST, $COMBO_COURSE_CONTROL_OPTIONS;

  $props = get_course_properties("{$courses_path}/{$course_to_set}");
  if (!isset($props[$TYPE_FIELD]) || ($props[$TYPE_FIELD] != $COMBO_COURSE) || !isset($props[$COMBO_COURSE_LIST])) {
    return false;
  }

  $list_of_subcourses = explode(",", $props[$COMBO_COURSE_LIST]);
  $event_courses = scandir($courses_path);
  $event_courses = array_diff($event_courses, array(".", ".."));
  $readable_course_hash = array();
  array_map(function ($elt) use (&$readable_course_hash) { $readable_course_hash[ltrim($elt, "0..9-")] = $elt; }, $event_courses);
  $control_list_array = array();

  foreach ($list_of_subcourses as $this_subcourse) {
    if (!isset($readable_course_hash[$this_subcourse])) {
      return false;
    }

    $course_full_name = $readable_course_hash[$this_subcourse];
    if (file_exists("{$courses_path}/{$course_full_name}/removed")) {
      return false;
    }

    if (!file_exists("{$courses_path}/{$course_full_name}/controls.txt")) {
      return false;
    }

    $controls_info = read_controls("{$courses_path}/{$course_full_name}/controls.txt");
    $subcourse_properties = get_course_properties("{$courses_path}/{$course_full_name}");
    if (isset($subcourse_properties[$TYPE_FIELD]) && ($subcourse_properties[$TYPE_FIELD] != $LINEAR_COURSE)) {
      return false;
    }

    $subcourse_controls = array_map(function ($elt) { return ($elt[0]); }, $controls_info);
    $subcourse_entry = "{$course_full_name};" . implode(",", $subcourse_controls);
    $control_list_array[] = $subcourse_entry;
  }

  $props[$COMBO_COURSE_CONTROL_OPTIONS] = implode("#", $control_list_array);
  file_put_contents("{$courses_path}/{$course_to_set}/properties.txt", create_properties($props));
  if (file_exists("{$courses_path}/{$course_to_set}/no_registrations")) {
    unlink("{$courses_path}/{$course_to_set}/no_registrations");
  }

  return true;
}

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
    $readable_name = ltrim($pieces[1], "0..9-");
    if ($pieces[0] == "disable") {
      if (!file_exists("{$courses_path}/{$pieces[1]}/no_registrations")) {
        touch("{$courses_path}/{$pieces[1]}/no_registrations");
        $results_string .= "<p>Course {$readable_name} is no longer available for direct registration.\n";
      }
      else {
        $results_string .= "<p>Course {$readable_name} was already marked unavailable for direct registration.\n";
      }
    }
    else if ($pieces[0] == "enable") {
      if (file_exists("{$courses_path}/{$pieces[1]}/no_registrations")) {
        unlink("{$courses_path}/{$pieces[1]}/no_registrations");
        $results_string .= "<p>Course {$readable_name} is available for direct registration again.\n";
      }
      else {
        $results_string .= "<p>Course {$readable_name} was already available for direct registration.\n";
      }
    }
    else if ($pieces[0] == "autoplace") {
      if (set_course_as_autoplace($courses_path, $pieces[1])) {
        $results_string .= "<p>Course {$readable_name} enabled for autoplacement.\n";
      }
      else {
        $results_string .= "<p>Course {$readable_name} ineligible for autoplacement, check sub-courses for validity.\n";
      }
    }
  }
}

// Show the interface to add / remove the courses, even if we've just removed some
$current_courses = scandir($courses_path);
$current_courses = array_diff($current_courses, array(".", ".."));

$readable_course_names = array_map(function ($elt) { return (ltrim($elt, "0..9-")); }, $current_courses);
$combo_courses = array_filter($current_courses, 'is_combo_course');
$regular_courses = array_diff($current_courses, $combo_courses);

$combo_courses_valid_for_autoplacement = array();
$combo_courses_with_autoplacement = array();
$combo_courses_incomplete = array();

// For the combo courses, see which one(s) have course definitions for ALL the subcourses
// Those are eligible for automatically placing finishers in the correct course (and
// thus allowing direct registrations)
if (count($combo_courses) > 0) {
  foreach ($combo_courses as $this_combo_course) {
    if (file_exists("{$courses_path}/{$this_combo_course}/no_registrations")) {
      $props = get_course_properties("{$courses_path}/{$this_combo_course}");
      if (!isset($props[$TYPE_FIELD]) || ($props[$TYPE_FIELD] == $COMBO_COURSE)) {
        // Should never be here...
      }
  
      $subcourses = explode(",", $props[$COMBO_COURSE_LIST]);
      $unknown_courses = array_diff($subcourses, $readable_course_names);
      if (count($unknown_courses) == 0) {
        $combo_courses_valid_for_autoplacement[] = $this_combo_course;
      }
      else {
        $combo_courses_incomplete[] = $this_combo_course;
      }
    }
    else {
      $combo_courses_with_autoplacement[] = $this_combo_course;
    }
  }
}

// Determine which courses have been removed already vs which are currently valid
// Show the checkboxes differently for the two cases
$valid_courses = array_filter($regular_courses, function ($elt) use ($courses_path) { return(!file_exists("{$courses_path}/{$elt}/no_registrations")); });
$invalid_courses = array_filter($regular_courses, function ($elt) use ($courses_path) { return(file_exists("{$courses_path}/{$elt}/no_registrations")); });
$valid_courses = array_map(function ($elt) { return ("<li><input type=checkbox name=\"disable:${elt}\">" . ltrim($elt, "0..9-")); }, $valid_courses);
$invalid_courses = array_map(function ($elt) { return ("<li><input type=checkbox name=\"enable:{$elt}\">" . ltrim($elt, "0..9-")); }, $invalid_courses);

$current_event_name = file_get_contents("{$event_path}/description");

echo "<p>Manipulate course registration status for: <strong>{$current_event_name}</strong>\n";
if ($results_string != "") {
  echo "<p>{$results_string}\n";
}
echo "<form action=\"./manage_course_registration.php\">\n";
echo "<input type=\"hidden\" name=\"key\" value=\"{$key}\" />\n";
echo "<input type=\"hidden\" name=\"event\" value=\"{$event}\" />\n";

if (count($valid_courses) > 0) {
  echo "<p><p>Disable direct registration for one or more courses\n<br><ul>\n";
  echo implode("\n", $valid_courses);
  echo "</ul>\n";
}

if (count($invalid_courses) > 0) {
  echo "<p><p>Re-enable direct registration for one or more courses\n<br><ul>\n";
  echo implode("\n", $invalid_courses);
  echo "</ul>\n";
}

if (count($combo_courses_valid_for_autoplacement) > 0) {
  $autoplace_options = array_map(function ($elt) { return ("<li><input type=checkbox name=\"autoplace:${elt}\">" . ltrim($elt, "0..9-")); }, $combo_courses_valid_for_autoplacement);
  echo "<p><p>Enable automatic subcourse choice for one or more courses\n<br><ul>\n";
  echo implode("\n", $autoplace_options);
  echo "</ul>\n";
}

if (count($combo_courses_incomplete) > 0) {
  echo "<p><p>Courses with incomplete subcourse definitions:\n<br><ul>\n";
  echo implode("\n", array_map(function ($elt) { return ("<li>" . ltrim($elt, "0..9-")); }, $combo_courses_incomplete));
  echo "</ul>\n";
}

if (count($combo_courses_with_autoplacement) > 0) {
  echo "<p><p>Courses with automatic subcourse choice already enabled:\n<br><ul>\n";
  echo implode("\n", array_map(function ($elt) { return ("<li>" . ltrim($elt, "0..9-")); }, $combo_courses_with_autoplacement));
  echo "</ul>\n";
}


echo "<p><input type=submit name=\"submit\" value=\"Change course status\">\n";
echo "</form>\n";
echo get_web_page_footer();
?>
