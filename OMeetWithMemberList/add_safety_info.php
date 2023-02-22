<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/course_properties.php';
require 'preregistration_routines.php';
require 'name_matcher.php';

ck_testing();


$first_name = "";
$last_name = "";
$club_name = "";
$si_stick = "";
$has_preset_id = isset($_GET["member_id"]);
$member_id = $has_preset_id ? $_GET["member_id"] : "";
$quick_lookup_member_id = isset($_GET["quick_lookup_member_id"]) ? $_GET["quick_lookup_member_id"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
$event = isset($_GET["event"]) ? $_GET["event"] : "";
$classification_info = isset($_GET["classification_info"]) ? $_GET["classification_info"] : "";
$classification_info_supplied = ($classification_info != "");
$classification_info_hash = array();
if ($classification_info_supplied) {
  $classification_info_hash = decode_entrant_classification_info($classification_info);
}


if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

if ($event == "") {
  error_and_exit("Unknown event (empty), are you using an authorized link?\n");
}

$event_path = get_event_path($event, $key);
if (!is_dir($event_path) || !file_exists("{$event_path}/description")) {
  error_and_exit("<p>ERROR: Bad event \"{$event}\", was this created properly?" . get_error_info_string());
}

if (file_exists("{$event_path}/done")) {
  error_and_exit("Event " . file_get_contents("{$event_path}/description") . " has completed and registrations are no longer possible.\n");
}

$is_preregistered_checkin = isset($_GET["checkin"]) && ($_GET["checkin"] == "true");
$using_nre_classes = event_is_using_nre_classes($event, $key);

$saved_registration_info = array();
if (isset($_COOKIE["{$key}-safety_info"])) {
  $saved_registration_info = parse_registration_info($_COOKIE["{$key}-safety_info"]);
}

$stick_override_msg = "";
$db_si_stick = isset($_GET["db_si_stick"]) ? $_GET["db_si_stick"] : "";
if ($has_preset_id) {
  if (!isset($_GET["using_stick"])) {
    error_and_exit("No value found for SI unit usage - error in scripting?  Please restart registration.\n");
  }
  
  $using_stick_value = $_GET["using_stick"];
  if (($using_stick_value != "yes") && ($using_stick_value != "no")) {
    error_and_exit("Invalid value \"{$using_stick_value}\" for SI unit usage.  Please restart registration.\n");
  }

  if (isset($_GET["si_stick_number"]) && ($_GET["si_stick_number"] != "") && ($using_stick_value == "no") && !isset($_GET["registered_si_stick"])) {
    $stick_override_msg = "<p class=title style=\"color:red;\"> <strong>SI unit number \"{$_GET["si_stick_number"]}\" entered but QR orienteering selected.\n";
    $stick_override_msg .= "<br>Overriding and using SI unit orienteering.\n";
    $stick_override_msg .= "<br>If this is wrong, please go back and restart registration and make sure that the SI unit field is blank.\n";
    $stick_override_msg .= "</strong><br><br><br><br>\n";
    $using_stick_value = "yes";
  }
  
  if ($using_stick_value == "yes") {
    if (!isset($_GET["si_stick_number"])) {
      error_and_exit("Yes specified for SI unit usage but no SI unit number found.  Please restart registration.\n");
    }
    $si_stick = $_GET["si_stick_number"];

    if ($si_stick == "") {
      error_and_exit("Yes specified for SI unit usage but SI unit number was blank.  Please restart registration.\n");
    }
  }
}
else {
  $first_name = $_GET["competitor_first_name"];
  $last_name = $_GET["competitor_last_name"];
  $club_name = $_GET["club_name"];
  $si_stick = $_GET["si_stick"];

  // Let's do some validations
  if ($first_name == "") {
    error_and_exit("Invalid (empty) first name, please go back and enter a valid first name.\n");
  }
  
  if ($last_name == "") {
    error_and_exit("Invalid (empty) last name, please go back and enter a valid last name.\n");
  }
}

if ($si_stick != "") {
  if (!preg_match("/^[0-9]+$/", $si_stick)) {
    error_and_exit("Invalid si unit id \"{$si_stick}\", only numbers allowed.  Please go back and re-enter.\n");
  }
}

echo get_web_page_header(true, false, true);

echo "<p class=title><u>Safety information</u>\n";
echo "<form action=\"./finalize_registration.php\">\n";
if ($is_preregistered_checkin) {
  echo "<input type=hidden name=\"checkin\" value=\"true\">\n";
}

if ($has_preset_id) {
  echo "<input type=hidden name=\"member_id\" value=\"{$member_id}\">\n";
}
else {
  echo "<input type=hidden name=\"competitor_first_name\" value=\"{$first_name}\">\n";
  echo "<input type=hidden name=\"competitor_last_name\" value=\"{$last_name}\">\n";
  echo "<input type=hidden name=\"club_name\" value=\"{$club_name}\">\n";
}
echo "<input type=hidden name=\"si_stick\" value=\"{$si_stick}\">\n";
echo "<input type=hidden name=\"key\" value=\"{$key}\">\n";
echo "<input type=hidden name=\"event\" value=\"{$event}\">\n";
if ($classification_info_supplied) {
  echo "<input type=hidden name=\"classification_info\" value=\"{$classification_info}\">\n";
}


// Warn the user if they entered a SI unit number but selected QR code orienteering
if ($stick_override_msg != "") {
  echo $stick_override_msg;
}

if ($is_preregistered_checkin) {
  $entrant_info_path = get_preregistered_entrant($member_id, $event, $key);
  $entrant_info = decode_preregistered_entrant($entrant_info_path, $event, $key);
  $is_member = ($entrant_info["member_id"] != "not_a_member") && ($entrant_info["member_id"] != "");
}
else {
  $is_member = $has_preset_id;
  $members_file = get_members_path($key, get_member_properties(get_base_path($key)));
  $member_info = quick_get_member($quick_lookup_member_id, $members_file);
  $entrant_info = array();
  echo "<p><input type=hidden name=\"quick_lookup_member_id\" value=\"{$quick_lookup_member_id}\">\n";
}

$base_path = get_base_path($key);
if (isset($entrant_info["waiver_signed"]) && (strtolower($entrant_info["waiver_signed"]) == "yes")) {
  echo "<p><input type=hidden name=\"waiver_signed\" value=\"signed\"><br>";
}
else {
  if ($is_member) {
    if (file_exists("{$base_path}/member_waiver")) {
      $waiver_html = file_get_contents("{$base_path}/member_waiver");
      echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) {$waiver_html}</strong><br>\n";
    }
    else {
      echo "<p><input type=hidden name=\"waiver_signed\" value=\"signed\"><br>";
    }
  }
  else {
    if (file_exists("{$base_path}/non_member_waiver")) {
      $waiver_html = file_get_contents("{$base_path}/non_member_waiver");
      echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) {$waiver_html}</strong><br>\n";
    }
    else {
      echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) I am participating of my own accord and hold the organizers harmless for any injury sustained.</strong><br>\n";
    }
  }
}

if ($is_preregistered_checkin && isset($entrant_info["cell_phone"])) {
  $presupplied_cell_phone = "value=\"{$entrant_info["cell_phone"]}\"";
}
else if ($is_member && isset($member_info["cell_phone"])) {
  $presupplied_cell_phone = "value=\"{$member_info["cell_phone"]}\"";
}
else if (isset($saved_registration_info["cell_phone"])) {
  $presupplied_cell_phone = "value=\"{$saved_registration_info["cell_phone"]}\"";
}
else {
  $presupplied_cell_phone = "";
}

if (isset($saved_registration_info["car_info"])) {
  $presupplied_car_info = "value=\"{$saved_registration_info["car_info"]}\"";
}
else {
  $presupplied_car_info = "";
}

if ($is_preregistered_checkin && isset($entrant_info["email_address"])) {
  $presupplied_email_address = "value=\"{$entrant_info["email_address"]}\"";
}
else if ($is_member && isset($member_info["email"])) {
  $presupplied_email_address = "value=\"{$member_info["email"]}\"";
}
else if (isset($saved_registration_info["email_address"]) && ($saved_registration_info["email_address"] != "")) {
  $presupplied_email_address = "value=\"{$saved_registration_info["email_address"]}\"";
}
else {
  $presupplied_email_address = "";
}

?>

<p>It is important that you scan finish at the end of your course so that we know you are safely off the course.
<p>In case there is any question if you have (or have not) safely returned, we need a way to contact you to verify your safety.
<p>This information is maintained while you are on the course and destroyed when you finish the course.

<br><p>(Best option) Your cell phone number, or a parent/guardian's, spouse's, etc.<br>
<input type="text" size=50 name="cell_number" <?php echo $presupplied_cell_phone; ?>><br>
<?php
if (file_exists("{$base_path}/collect_car_info")) {
  echo "<br><p>What car (make/model/plate) did you come in (we can check the lot to see if you've left)?<br>\n";
  echo "<input type=\"text\" size=50 name=\"car_info\" {$presupplied_car_info}><br>\n";
}
?>

<p>
<?php
echo "<br><p>(Optional) If you would like results emailed to you, please supply a valid email address<br>\n";
echo "<input type=\"text\" size=50 name=\"email\" {$presupplied_email_address} ><br><br>\n";

//
if ($using_nre_classes && (!$classification_info_supplied || ($classification_info_hash["CLASS"] == ""))) {
  echo "<br><br><p>If you would like your time to count for national ranking purposes, please enter your birth year and gender.\n";
  echo "<p>Please leave blank (unspecified) if you are orienteering recreationally or going out in a group (more than 1 person).\n";

  echo "<p>(Optional) Birth year (for ranking purposes), please use 4 digits, e.g. 1973, 2001, etc.<br>\n";
  if ($classification_info_supplied && ($classification_info_hash["BY"] != "")) {
    $presupplied_birth_year = "value=\"{$classification_info_hash["BY"]}\"";
  }
  else {
    $presupplied_birth_year = "value=\"\"";
  }
  echo "<input type=\"text\" size=50 name=\"birth_year\" {$presupplied_birth_year} ><br><br>\n";

  $male_checked = "";
  $female_checked = "";
  $other_checked = "";
  if ($classification_info_supplied) {
    if ($classification_info_hash["G"] == "m") {
      $male_checked = "checked";
    }
    if ($classification_info_hash["G"] == "f") {
      $female_checked = "checked";
    }
    if ($classification_info_hash["G"] == "o") {
      $other_checked = "checked";
    }
  }
  echo "<p>(Optional) Gender (for ranking purposes): <br>";
  echo "<input type=radio name=\"gender\" value=\"f\" {$female_checked} >  Female<br>\n";
  echo "<input type=radio name=\"gender\" value=\"m\" {$male_checked} >  Male<br>\n";
  echo "<input type=radio name=\"gender\" value=\"o\" {$other_checked} >  Other<br>\n";
  echo "<input type=radio name=\"gender\" value=\"\" >  Unspecified<br>\n";
}

// If the person is a member doing normal checkin, see if they are using a new stick
// and would like to register it with the club
if (!$is_preregistered_checkin && $has_preset_id) {
  if (($si_stick != "") && ($db_si_stick != $si_stick)) {
    // check the properties - see if allowing stick registration before prompting
    $email_properties = get_email_properties($base_path);
    $email_enabled = isset($email_properties["from"]) && isset($email_properties["reply-to"]);
    if ($email_enabled && ($email_properties["email_to_register_stick"] != "")) {
      echo "<p>(Optional) Check the box to register SI unit {$si_stick} with the club for future meets.\n";
      echo "<input type=checkbox name=\"email_si_stick\" value=\"{$si_stick}\"><br>\n";
    }
  }
}
?>

<input type="submit" value="Choose course">
</form>

<?php
echo get_web_page_footer();
?>
