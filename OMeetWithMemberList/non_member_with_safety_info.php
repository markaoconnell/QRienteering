<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

$first_name = $_GET["competitor_first_name"];
$last_name = $_GET["competitor_last_name"];
$club_name = $_GET["club_name"];
$si_stick = $_GET["si_stick"];
$email_address = $_GET["email"];
$cell_phone = $_GET["cell_number"];
$car_info = $_GET["car_info"];
$key = $_GET["key"];
$waiver_signed = $_GET["waiver_signed"];

if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

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
                                               "is_member", base64_encode("no")));

// Redirect to the main registration screens
echo "<html><head><meta http-equiv=\"refresh\" content=\"0; URL=../OMeetRegistration/register.php?key={$key}&registration_info=${registration_info_string}\" /></head></html>";
?>
