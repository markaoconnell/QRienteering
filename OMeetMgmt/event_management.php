<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetWithMemberList/preregistration_routines.php';

ck_testing();

function validate_name($string) {
  if (!preg_match("/^[-a-zA-Z '\".]+$/", $string)) {
    return ("Name field may only contain alphabetic characters, apostrophe, quote, space, hyphen, and period");
  }
  return("");
}

function validate_course($string) {
  if (!preg_match("/^[a-zA-Z0-9_-]+$/", $string)) {
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
  if (!preg_match("/^[a-zA-Z0-9_.-]+@[a-zA-Z0-9_.-]+$/", $string)) {
    return ("Email field looks malformatted, should be like foo.bill@bar.gmail");
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
    return ("Gender must be m, f, or o (lower case)");
  }
  return("");
}

function validate_class($string) {
  if (!preg_match("/^[0-9A-Za-z][0-9 A-Za-z+-]*$/", $string)) {
    return ("Class can only contain letters, numbers, space, +, and -, must begin with a letter or number");
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
else if (isset($_POST["key"])) {
  $key = $_POST["key"];
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
else if (isset($_POST["event"])) {
  $event = $_POST["event"];
}
else {
  $event = "";
}
$event_path = get_event_path($event, $key, "..");
if (!is_dir($event_path)) {
  error_and_exit("No event directory found, is \"{$event}\" from a valid link?\n");
}

if (isset($_GET["remove_preregistrations"]) && ($_GET["remove_preregistrations"] != "")) {
  disable_preregistration($event, $key);
  if (preregistrations_allowed($event, $key)) {
    $error_string .= "<p>Error disabling preregistrations, ask the site administrator to handle.\n";
  }
  else {
    $output_string .= "<p>Preregistrations successfully disabled.";
  }
}

if (isset($_POST["upload_preregistrants"])) {
  if ($_FILES["prereg_file"]["size"] > 0) {
    $prereg_file = file($_FILES["prereg_file"]["tmp_name"], FILE_IGNORE_NEW_LINES);
    $prereg_entries = array_map(function ($elt) { return (explode(",", $elt)); }, $prereg_file);

    $validators = array(array("offset" => 0, "field_name" => "first_name", "validator" => "validate_name", "optional" => false),
                        array("offset" => 1, "field_name" => "last_name", "validator" => "validate_name", "optional" => false),
                        array("offset" => 2, "field_name" => "course", "validator" => "validate_course", "optional" => true),
                        array("offset" => 3, "field_name" => "stick", "validator" => "validate_stick", "optional" => true),
                        array("offset" => 4, "field_name" => "cell_phone", "validator" => "validate_cell_phone", "optional" => true),
                        array("offset" => 5, "field_name" => "email_address", "validator" => "validate_email", "optional" => true),
                        array("offset" => 6, "field_name" => "club_name", "validator" => "validate_name", "optional" => true),
                        array("offset" => 7, "field_name" => "waiver_signed", "validator" => "validate_waiver", "optional" => true),
                        array("offset" => 8, "field_name" => "birth_year", "validator" => "validate_birth_year", "optional" => true),
                        array("offset" => 9, "field_name" => "gender", "validator" => "validate_gender", "optional" => true),
			array("offset" => 10, "field_name" => "class", "validator" => "validate_class", "optional" => true));

    $good_entries = array();
    foreach ($prereg_entries as $this_entry) {
      $entry_is_ok = true;
      $entry_string = "";
      foreach ($validators as $field_validator) {
        $current_field = $this_entry[$field_validator["offset"]];
	if ($current_field != "") {
	  $validation_result = $field_validator["validator"]($current_field);
	  if ($validation_result != "") {
            $error_string .= "<p>Error: Field validation error - \"" . join(",", $this_entry) . "\", name: \"" . $field_validator["field_name"] .
		    "\" (field_number: " . ($field_validator["offset"] + 1) . "): {$validation_result}\n";
	    $entry_is_ok = false;
	    break;
	  }
          $entry_string .= $field_validator["field_name"] . ":{$current_field};";
	}
	else {
          if (!$field_validator["optional"]) {
            $error_string .= "<p>Error: Field cannot be blank - \"" . join(",", $this_entry) . "\", name: \"" . $field_validator["field_name"] .
		    "\" (field_number: " . ($field_validator["offset"] + 1) . ")\n";
	    $entry_is_ok = false;
	    break;
	  }
          $entry_string .= $field_validator["field_name"] . ":{$current_field};";
	}
      }

      if ($entry_is_ok) {
        $good_entries[] = $entry_string;
      }
    }
  }

  if (count($good_entries) > 0) {
    enable_preregistration($event, $key);  # Safe to do this is preregistration is already allowed
    if ($_POST["handle_current"] == "replace") {
      file_put_contents(get_preregistration_file($event, $key), implode("\n", $good_entries));
    }
    else if ($_POST["handle_current"] == "append") {
      file_put_contents(get_preregistration_file($event, $key), "\n" . implode("\n", $good_entries), FILE_APPEND);
    }
    else {
      error_and_exit("<p>Incorrect value for \"handle_current\", should never happen");
    }

    $output_string .= "<p>Successfully added " . count($good_entries) . " preregistrants.\n";
  }

  if (isset($_POST["nre_classes"]) && ($_POST["nre_classes"] == "use_nre_classes")) {
    enable_event_nre_classes($event, $key);
    $output_string .= "<p>NRE classifications successfully enabled.";
  }
}

if ($output_string != "") {
  $output_string .= "<hr>\n";
}

if ($error_string != "") {
  $output_string .= "{$error_string}\n<hr>\n";
}

$preregistration_currently_allowed = preregistrations_allowed($event, $key);
if ($preregistration_currently_allowed) {
  $output_string .= "<p>Preregistration currently <u>enabled</u>\n";
  $output_string .= "<p><a href=\"./view_preregistrations.php?key={$key}&event={$event}\">View currently pregistered participants</a>\n";
  $output_string .= "<p><form action=\"./event_management.php\">\n";
  $output_string .= "<input type=hidden name=\"key\" value=\"{$key}\">\n";
  $output_string .= "<input type=hidden name=\"event\" value=\"{$event}\">\n";
  $output_string .= "<input type=hidden name=\"remove_preregistrations\" value=\"1\">\n";
  $output_string .= "<p>Click here to disable preregistration for this event and to remove all currently preregistered entrants<p>\n";
  $output_string .= "<input type=submit value=\"Disable preregistration\">\n</form>\n";
  $output_string .= "<hr>";
}
else {
  $output_string .= "<p>Preregistration currently <u>disabled</u>\n";
}

$output_string .= "<p><p>Upload new preregistration file";
$output_string .= ($preregistration_currently_allowed) ? "\n" : " and enable preregistrations\n";
$output_string .= "<form action=\"./event_management.php\" method=post enctype=\"multipart/form-data\">\n";
$output_string .= "<input type=hidden name=\"key\" value=\"{$key}\">\n";
$output_string .= "<input type=hidden name=\"event\" value=\"{$event}\">\n";
$output_string .= "<p>File format is .csv (comma separated fields), first name and last name are required, all other fields may be blank.\n";
$output_string .= "<p>Field order is:\n";
$output_string .= "<ul><li>First name<li>Last name<li>Course<li>E punch id (si unit)<li>cell phone<li>email address\n";
$output_string .= "<li>orienteering club name<li>presigned waiver (yes or leave blank)<li>year of birth (4 digits)\n";
$output_string .= "<li>gender (m or f)<li>class (e.g. M55+, F-18, etc)</ul>\n";
$output_string .= "<p>File to upload: \n";
$output_string .= "<input type=file name=\"prereg_file\" accept=\".csv\">\n";
if ($preregistration_currently_allowed) {
  $output_string .= "<p><input type=radio name=\"handle_current\" value=\"replace\" checked>Replace all current preregistered entrants\n";
  $output_string .= "<p><input type=radio name=\"handle_current\" value=\"append\">Current preregistered entrants remain and file contains additional entrants\n";
}
else {
  $output_string .= "<input type=\"hidden\" name=\"handle_current\" value=\"replace\">\n";
}

if (!event_is_using_nre_classes($event, $key) && file_exists(get_default_nre_classification_file($key))) {
  $output_string .= "<p><input type=checkbox name=\"nre_classes\" value=\"use_nre_classes\">Enable results per age/gender class\n";
}

$output_string .= "<p><p><input type=submit name=\"upload_preregistrants\" value=\"Update event\">\n";
$output_string .= "</form>";

$current_event_name = file_get_contents("{$event_path}/description");

echo "<p>Event management: <strong>{$current_event_name}</strong>\n";
echo $output_string;

echo get_web_page_footer();
?>
