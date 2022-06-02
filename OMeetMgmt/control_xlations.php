<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

function validate_name($string) {
  if (!preg_match("/^[a-zA-Z '\"]+$/", $string)) {
    return ("Name field may only contain alphabetic characters, apostrophe, quote, and space");
  }
  return("");
}

function validate_course($string) {
  if (!preg_match("/^[a-zA-Z0-9]+$/", $string)) {
    return ("Course field may only contain alphabetic characters and numbers");
  }
  return("");
}

function validate_stick($string) {
  if (!preg_match("/^[0-9]+$/", $string)) {
    return ("SI unit field may only contain numbers");
  }
  return("");
}

function validate_cell_phone($string) {
  if (!preg_match("/^\+?[0-9-]+$/", $string)) {
    return ("Cell phone field looks malformatted, should be like +1-508-123-4567 (no parens)");
  }
  return("");
}

function validate_email($string) {
  if (!preg_match("/^[a-zA-Z0-9_]+@[a-zA-Z0-9_.]+$/", $string)) {
    return ("Email field looks malformatted, should be like foo@bar.gmail");
  }
  return("");
}

function validate_waiver($string) {
  if ($string != "yes") {
    return ("Waiver field should be \"yes\" or blank");
  }
  return("");
}

function validate_birth_year($string) {
  if (!preg_match("/^\d\d\d\d$/", $string)) {
    return ("Birth year must be 4 digits");
  }
  return("");
}

function validate_gender($string) {
  if (!preg_match("/^[mfo]$/", $string)) {
    return ("Birth year must be 4 digits");
  }
  return("");
}

function validate_class($string) {
  if (!preg_match("/^[0-9 A-Za-z+-]+$/", $string)) {
    return ("Class can only contain letters, numbers, space, +, and -");
  }
  return("");
}

$output_string = "";

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

$found_error = false;
$error_string = "";

if (isset($_GET["key"])) {
  $key = $_GET["key"];
}
else {
  $key = "";
}
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key))) {
  error_and_exit("No directory found for events, is your key \"{$key}\" valid?\n");
}

if (isset($_GET["event"])) {
  $event = $_GET["event"];
}
else {
  $event = "";
}
$event_path = get_event_path($event, $key);
if (!is_dir($event_path)) {
  error_and_exit("No event directory found, is \"{$event}\" from a valid link?\n");
}

$control_xlations = isset($_GET["control_xlations"]) ? $_GET["control_xlations"] : "";
if ($control_xlations != "") {
  $control_xlation_list = explode(",", $control_xlations);
  foreach ($control_xlation_list as $xlation_entry) {
    $xlation_pieces = explode(":", $xlation_entry);
    if ($xlation_pieces[1] != "-") {
      set_xlation_for_control($key, $event, $xlation_pieces[0], $xlation_pieces[1]);
    }
    else {
      remove_xlation_for_control($key, $event, $xlation_pieces[0]);
    }
  }
}

$xlations_hash = get_control_xlations($key, $event);
if (count($xlations_hash) > 0) {
  $output_string .= "<p>Current control translation entries\n";
  $xlation_string = implode("\n<li>", array_map(function ($k, $v) { return("{$k} in the field -> {$v} on the map"); }, array_keys($xlations_hash), array_values($xlations_hash)));
  $output_string .= "<ul>\n<li>{$xlation_string}\n</ul>\n";
}
else {
  $output_string .= "<p>No current control translations.\n";
}

$output_string .= "<p><p><hr><p>Add one (or more) translations.\n";
$output_string .= "<p>Format is a comma separated list, with each entry being the control in the field, a colon, then the control on the map.\n";
$output_string .= "<p>Example: 202:102,203:134\n";
$output_string .= "<p>That example would be used when controls 102 and 134 went bad and were replaced by controls 202 and 203 in the field, but\n";
$output_string .= "the maps could not be reprinted in time so the maps show controls 102 and 134, while the actual controls which are hung\n";
$output_string .= "in the woods, which are in the course definition, are 202 and 203.\n";
$output_string .= "<p>Use 202:- to remove an entry (if one was created in error)\n";
$output_string .= "<p><p><form action=\"./control_xlations.php\">\n";
$output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
$output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
$output_string .= "<p><input type=text name=control_xlations>\n";
$output_string .= "<p><input type=submit value=\"Submit translations\">\n";
$output_string .= "</form>";



$current_event_name = file_get_contents("{$event_path}/description");

echo "<p>Control translations for: <strong>{$current_event_name}</strong>\n";

echo $output_string;

echo "<p><p><a href=\"../OMeetMgmt/manage_events.php?key={$key}&event={$event}\">Return to event management page</a>\n";

echo get_web_page_footer();
?>
