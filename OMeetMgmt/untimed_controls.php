<?php
require '../OMeetCommon/common_routines.php';


ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

$found_error = false;
$error_string = "";

$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key))) {
  error_and_exit("No directory found for events, is your key \"{$key}\" valid?\n");
}

$event = isset($_GET["event"]) ? $_GET["event"] : "";
$event_path = get_event_path($event, $key);
if (!is_dir($event_path)) {
  error_and_exit("No event directory found, is \"{$event}\" from a valid link?\n");
}

$courses_path = get_courses_path($event, $key);
$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));

$current_event_name = file_get_contents("{$event_path}/description");

$error_string = "";
$submit_new_entries = true;
if (isset($_GET["update_values"])) {
  // Gather the submitted entries
  $new_untimed_entries = array();
  foreach (array_keys($_GET) as $this_field) {
    if (in_array($this_field, $course_list)) {
      $decoded_entry = htmlentities($_GET[$this_field]);
      if (trim($decoded_entry) == "") {
        // nothing to do in this case, but it is valid
      }
      else {
        $new_entries = implode(",", array_map(function ($elt) { $bits=explode(":", $elt); return(trim($bits[0]) . ":" . trim($bits[1])); }, explode(",", $decoded_entry)));
        $all_valid_entries = array_reduce(array_map(function($elt) { return (preg_match("/^[a-zA-Z0-9]+:[0-9]+$/", $elt)); }, explode(",", $new_entries)),
	        function ($carry, $elt) { return ($carry && $elt); }, true);
        if ($all_valid_entries) {
          $new_untimed_entries[$this_field] = $new_entries;
	}
	else {
          $error_string .= "<p style=\"color: red;\">>Invalid value(s) in line: " . ltrim($this_field, "0..9-") . ":{$decoded_entry}.\n";
          $submit_new_entries = false;
        }
      }
    }
  }

  if ($submit_new_entries) {
    put_untimed_controls($event, $key, $new_untimed_entries);
  }
  else {
    echo "<strong>ERROR on parsing new untimed control entries, no changes made.\n{$error_string}</strong>\n";
  }
}

$untimed_controls_by_course = get_untimed_controls($event, $key);

echo "<p>Manage untimed controls for {$current_event_name}\n";
echo "<p><p>Enter comma separated list of controls, per course, for which the split time should not count.  Format is just control:max_wait_in_seconds\n";
echo "<p>E.g. if control 105 is on one side of a busy road and control 106 is on the other side, and competitors have 1 minute to cross the road, so ";
echo "enter 106:60 in the box for the appropriate course.\n";
echo "<p>Leave the entry blank (or make it blank) if there are no untimed controls on a course.\n";

echo "<p><p>";
echo "<form action=\"./untimed_controls.php\" method=\"get\">";
echo "<input type=hidden name=key value={$key}>\n<input type=hidden name=event value={$event}>\n";
foreach ($course_list as $this_course) {
  $printable_course_name = ltrim($this_course, "0..9-");
  echo "<p>{$printable_course_name}: <input type=text name=\"{$this_course}\" value=\"" .
	  (isset($untimed_controls_by_course[$this_course]) ? $untimed_controls_by_course[$this_course] : "") .
	  "\">\n";
}
echo "<p><p><input type=submit name=\"update_values\" value=\"Submit untimed controls\">\n";
echo "</form>\n";

echo "<p><p><a href=\"./manage_events.php?key={$key}&event={$event}\">Return to mangement page</a>\n";

echo get_web_page_footer();
?>
