<?php
require '../OMeetCommon/common_routines.php';
require 'name_matcher.php';

ck_testing();

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$is_member = isset($_GET["member_id"]);

if ($is_member) {
  $matching_info = read_names_info(get_members_path($key, ".."), get_nicknames_path($key, ".."));
  
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
  $club_name_path = get_base_path($key, "..") . "/club_name";
  if (file_exists($club_name_path)) {
    $club_name = file_get_contents($club_name_path);
  }
  else {
    $club_name = "NEOC";  // Should not be hardcoded
  }
}
else {
  $first_name = $_GET["competitor_first_name"];
  $last_name = $_GET["competitor_last_name"];
  $club_name = $_GET["club_name"];
}
$waiver_signed = $_GET["waiver_signed"];
$car_info = $_GET["car_info"];
$cell_phone = $_GET["cell_number"];
$email_address = $_GET["email"];
$si_stick = $_GET["si_stick"];


// Let's do some validations
if ($first_name == "") {
  error_and_exit("Invalid (empty) first name, please go back and enter a valid first name.\n");
}

if ($last_name == "") {
  error_and_exit("Invalid (empty) last name, please go back and enter a valid last name.\n");
}

if ($si_stick != "") {
  if (!preg_match("/^[0-9]+$/", $si_stick)) {
    error_and_exit("Invalid si_stick \"{$si_stick}\", only numbers allowed.  Please go back and re-enter.\n");
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
