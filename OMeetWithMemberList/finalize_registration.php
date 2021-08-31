<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require 'name_matcher.php';

ck_testing();

function find_get_key_or_empty_string($parameter_name) {
  return(isset($_GET[$parameter_name]) ? $_GET[$parameter_name] : "");
}

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$is_member = isset($_GET["member_id"]);

if ($is_member) {
  $member_properties = get_member_properties(get_base_path($key));
  $matching_info = read_names_info(get_members_path($key, $member_properties), get_nicknames_path($key, $member_properties));
  
  if (!isset($_GET["member_id"])) {
    error_and_exit("No member id specified, please restart registration.\n");
  }
  else {
    $member_id = $_GET["member_id"];
    if (get_full_name($member_id, $matching_info) == "") {
      error_and_exit("No such member id {$_GET["member_id"]} found, please retry or ask for assistance.\n");
    }
  }
  
  $name_info = get_member_name_info($member_id, $matching_info);
  $first_name = $name_info[0];
  $last_name = $name_info[1];
  $club_name = get_club_name($key, $member_properties);
}
else {
  $first_name = find_get_key_or_empty_string("competitor_first_name");
  $last_name = find_get_key_or_empty_string("competitor_last_name");
  $club_name = find_get_key_or_empty_string("club_name");
}
$waiver_signed = find_get_key_or_empty_string("waiver_signed");
$car_info = find_get_key_or_empty_string("car_info");
$cell_phone = find_get_key_or_empty_string("cell_number");
$email_address = find_get_key_or_empty_string("email");
$si_stick = find_get_key_or_empty_string("si_stick");


// Let's do some validations
if ($first_name == "") {
  error_and_exit("Invalid (empty) first name, please go back and enter a valid first name.\n");
}

if ($last_name == "") {
  error_and_exit("Invalid (empty) last name, please go back and enter a valid last name.\n");
}

if ($si_stick != "") {
  if (!preg_match("/^[0-9]+$/", $si_stick)) {
    error_and_exit("Invalid SI unit id \"{$si_stick}\", only numbers allowed.  Please go back and re-enter.\n");
  }
}

if ($waiver_signed != "signed") {
  error_and_exit("The waiver must be acknowledged in order to participate in this event.\n");
}

$success_string = "";
$registration_info_string = implode(",", array("first_name", base64_encode($first_name),
                                               "last_name", base64_encode($last_name),
                                               "club_name", base64_encode($club_name),
                                               "si_stick", base64_encode($si_stick),
                                               "email_address", base64_encode($email_address),
                                               "cell_phone", base64_encode($cell_phone),
                                               "car_info", base64_encode($car_info),
					       "member_id", base64_encode($is_member ? $member_id : ""),
                                               "is_member", base64_encode($is_member ? "yes" : "no")));

// Redirect to the main registration screens
echo "<html><head><meta http-equiv=\"refresh\" content=\"0; URL=../OMeetRegistration/register.php?key={$key}&registration_info=${registration_info_string}\" /></head></html>";
?>
