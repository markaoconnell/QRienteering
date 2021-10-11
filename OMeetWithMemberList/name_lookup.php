<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require 'name_matcher.php';
require 'preregistration_routines.php';

ck_testing();

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$event = isset($_GET["event"]) ? $_GET["event"] : "";
 
$saved_registration_info = array();
if (isset($_COOKIE["{$key}-safety_info"])) {
  $saved_registration_info = parse_registration_info($_COOKIE["{$key}-safety_info"]);
}

$member_properties = get_member_properties(get_base_path($key));
$matching_info = read_names_info(get_members_path($key, $member_properties), get_nicknames_path($key, $member_properties));

$is_preregistered_checkin = isset($_GET["checkin"]) && ($_GET["checkin"] == "true");
if ($is_preregistered_checkin) {
  if ($event == "") {
    error_and_exit("Event must be set when checking in a preregistered competitor.\n");
  }

  $prereg_matching_info = read_preregistrations($event, $key);
  $prereg_matching_info["nicknames_hash"] = $matching_info["nicknames_hash"];
}

if (!isset($_GET["member_id"])) {
  if (!isset($_GET["competitor_first_name"]) || ($_GET["competitor_first_name"] == "")) {
    error_and_exit("Unspecified competitor first name, please retry.\n");
  }
  
  if (!isset($_GET["competitor_last_name"]) || ($_GET["competitor_last_name"] == "")) {
    error_and_exit("Unspecified competitor last name, please retry.\n");
  }
  
  $first_name_to_lookup = $_GET["competitor_first_name"];
  $last_name_to_lookup = $_GET["competitor_last_name"];
  
  if ($is_preregistered_checkin) {
    $possible_member_ids = find_best_name_match($prereg_matching_info, $first_name_to_lookup, $last_name_to_lookup);
  }
  else {
    $possible_member_ids = find_best_name_match($matching_info, $first_name_to_lookup, $last_name_to_lookup);
  }
}
else {
  $possible_member_ids = array($_GET["member_id"]);

  if ($is_preregistered_checkin) {
    if (get_full_name($possible_member_ids[0], $prereg_matching_info) == "") {
      error_and_exit("No such member id {$_GET["member_id"]} found, please retry or ask for assistance.\n");
    }
  }
  else {
    if (get_full_name($possible_member_ids[0], $matching_info) == "") {
      error_and_exit("No such member id {$_GET["member_id"]} found, please retry or ask for assistance.\n");
    }
  }
}

$error_string = "";
$success_string = "";
if (count($possible_member_ids) == 0) {
  error_and_exit("No such member {$first_name_to_lookup} {$last_name_to_lookup} found, please retry or ask for assistance.\n");
}
else if (count($possible_member_ids) == 1) {
  if ($is_preregistered_checkin) {
    $printable_name = get_full_name($possible_member_ids[0], $prereg_matching_info);
    $si_stick = get_si_stick($possible_member_ids[0], $prereg_matching_info);
    $member_id = $prereg_matching_info["members_hash"][$possible_member_ids[0]]["club_member_id"]; 
    if ($member_id != "not_a_member") {
      $email_address = get_member_email($member_id, $matching_info);
    }
    $pass_preregistration_marker = "<input type=\"hidden\" name=\"checkin\" value=\"true\">\n";
    $pass_preregistration_marker .= "<input type=\"hidden\" name=\"event\" value=\"{$event}\">\n";
  }
  else {
    $printable_name = get_full_name($possible_member_ids[0], $matching_info);
    $si_stick = get_si_stick($possible_member_ids[0], $matching_info);
    $email_address = get_member_email($possible_member_ids[0], $matching_info);
    $pass_preregistration_marker = "";
  }
  $success_string .= "<p>Welcome {$printable_name}.\n";
  if ($si_stick != "") {
    $yes_checked_by_default = "checked";
    $no_checked_by_default = "";
    $pass_registered_si_stick_entry = "<input type=hidden name=\"registered_si_stick\" value=\"yes\"/>\n";
  }
  else if (isset($saved_registration_info["si_stick"]) && ($saved_registration_info["si_stick"] != "")) {
    $si_stick = $saved_registration_info["si_stick"];
    $yes_checked_by_default = "checked";
    $no_checked_by_default = "";
    $pass_registered_si_stick_entry = "<input type=hidden name=\"registered_si_stick\" value=\"yes\"/>\n";
  }
  else {
    $yes_checked_by_default = "";
    $no_checked_by_default = "checked";
    $pass_registered_si_stick_entry = "";
  }
  $success_string .= "<p>How are you orienteering today?";
  $success_string .= <<<END_OF_FORM
<form action="./add_safety_info.php">
<input type=hidden name="member_id" value="{$possible_member_ids[0]}"/>
<input type=hidden name="member_email" value="{$email_address}"/>
{$pass_preregistration_marker}
{$pass_registered_si_stick_entry}
<p> Using Si unit <input type=radio name="using_stick" value="yes" {$yes_checked_by_default} /> <input type=text name="si_stick_number" value="{$si_stick}" />
<p> Using QR codes <input type=radio name="using_stick" value="no" {$no_checked_by_default}/>
<input type="hidden" name="key" value="{$key}">
<p><input type="submit" value="Fill in safety information"/>
</form>
END_OF_FORM;
}
else {
  if ($is_preregistered_checkin) {
    $success_string .= "<p>Ambiguous preregistered entrant name, please choose:\n";
  }
  else {
    $success_string .= "<p>Ambiguous member name, please choose:\n";
  }
  $success_string .= "<form action=\"name_lookup.php\">\n";
  foreach ($possible_member_ids as $possible_member) {
    $success_string .= "<p><input type=radio name=\"member_id\" value=\"{$possible_member}\"> " . get_full_name($possible_member, $matching_info) . "\n";
  }
  if ($is_preregistered_checkin) {
    $success_string .= "<input type=\"hidden\" name=\"event\" value=\"{$event}\">\n";
    $success_string .= "<input type=\"hidden\" name=\"checkin\" value=\"true\">\n";
  }
  $success_string .= "<input type=\"hidden\" name=\"key\" value=\"{$key}\">\n";
  $success_string .= "<p><input type=submit name=\"Choose member\"/>\n";
  $success_string .= "</form>\n";
}


echo get_web_page_header(true, false, true);

echo $success_string;

if ($is_preregistered_checkin) {
  echo "<a href=\"./competition_register.php?key={$key}&member=1\">Start over and re-enter information</a>\n";
}
else {
  echo "<a href=\"./checkin_preregistered.php?key={$key}&event={$event}\">Start over and re-enter information</a>\n";
}

echo get_web_page_footer();
?>
