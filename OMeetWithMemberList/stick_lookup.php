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


$parseable_result_string = "\n<!--\n";

if (!isset($_GET["si_stick"])) {
  $parseable_result_string .= "####,ERROR,Unspecified SI unit number in the lookup call\n-->\n";
  error_and_exit("{$parseable_result_string}Unspecified SI unit number, please hit back and retry.\n");
}

$si_stick = $_GET["si_stick"];

if ($is_preregistered_checkin) {
  $member_id = $prereg_matching_info["si_hash"][$si_stick];
  if ($member_id == "") {
    $parseable_result_string .= "####,ERROR,No preregistration entry found for {$si_stick}\n-->\n";
    error_and_exit("{$parseable_result_string}No preregistration entry with SI unit \"{$si_stick}\" found, please hit back and retry.\n");
  }
}
else {
  $member_id = get_by_si_stick($si_stick, $matching_info);
  if ($member_id == "") {
    $parseable_result_string .= "####,ERROR,No member entry found for {$si_stick}\n-->\n";
    error_and_exit("{$parseable_result_string}No member with SI unit \"{$si_stick}\" found, please hit back and retry.\n");
  }
}

$error_string = "";
$success_string = "";

$club_name = "";
$preregistered_course = "";
if ($is_preregistered_checkin) {
  $printable_name = get_full_name($member_id, $prereg_matching_info);
  $entrant_path = get_preregistered_entrant($member_id, $event, $key);
  $entrant_info = decode_preregistered_entrant($entrant_path, $event, $key);

  $club_member_id = $prereg_matching_info["members_hash"][$member_id]["club_member_id"]; 
  if (($club_member_id != "not_a_member") && ($club_member_id != "")) {
    $email_address = get_member_email($club_member_id, $matching_info);
    $club_name = get_club_name($key, $member_properties);
  }
  else {
    if (isset($entrant_info["email_address"])) {
      $email_address = $entrant_info["email_address"];
    }
    $club_name = isset($entrant_info["club_name"]) ? $entrant_info["club_name"] : "";
  }
  $pass_preregistration_marker = "<input type=\"hidden\" name=\"checkin\" value=\"true\">\n";
  $pass_preregistration_marker .= "<input type=\"hidden\" name=\"event\" value=\"{$event}\">\n";

  $preregistered_course = isset($entrant_info["course"]) ? "," . $entrant_info["course"] : "";
}
else {
  $printable_name = get_full_name($member_id, $matching_info);
  $email_address = get_member_email($member_id, $matching_info);
  $club_name = get_club_name($key, $member_properties);
  $pass_preregistration_marker = "";
}
$success_string .= "<p>Welcome {$printable_name}.\n";
$parseable_result_string .= "\n####,MEMBER_ENTRY," . base64_encode($printable_name) . ",{$member_id},{$email_address},{$club_name}{$preregistered_course}\n";
$success_string .= <<<END_OF_FORM
<form action="./add_safety_info.php">
<input type=hidden name="member_id" value="{$member_id}"/>
<input type=hidden name="member_email" value="{$email_address}"/>
<input type=hidden name="key" value="{$key}"/>
<input type=hidden name="registered_si_stick" value="yes"/>
{$pass_preregistration_marker}
<p> How are you orienteering today? <br>
<p> Using Si unit <input type=radio name="using_stick" value="yes" checked /> <input type=text name="si_stick_number" value="{$si_stick}" readonly/>
<p> Using QR codes <input type=radio name="using_stick" value="no" />
<p><input type="submit" value="Fill in safety information"/>
<p>If you are using a different SI unit, go back and register by name rather than by SI unit.
<p>If your name is wrong, go back and re-register.
</form>
END_OF_FORM;


echo get_web_page_header(true, false, true);

echo $success_string;
echo "{$parseable_result_string}\n-->\n";

echo get_web_page_footer();
?>
