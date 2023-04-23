<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/course_properties.php';
require 'name_matcher.php';
require 'preregistration_routines.php';

ck_testing();

function find_get_key_or_empty_string($parameter_name) {
  return(isset($_GET[$parameter_name]) ? $_GET[$parameter_name] : "");
}

$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$event = isset($_GET["event"]) ? $_GET["event"] : "";
if ($event == "") {
  error_and_exit("Unknown event (empty), are you using an authorized link?\n");
}

$quick_lookup_member_id = isset($_GET["quick_lookup_member_id"]) ? $_GET["quick_lookup_member_id"] : "";

$event_path = get_event_path($event, $key, "..");
if (!is_dir($event_path) || !file_exists("{$event_path}/description")) {
  error_and_exit("<p>ERROR: Bad event \"{$event}\", was this created properly?" . get_error_info_string());
}

if (file_exists("{$event_path}/done")) {
  error_and_exit("Event " . file_get_contents("{$event_path}/description") . " has completed and registrations are no longer possible.\n");
}

$classification_info = isset($_GET["classification_info"]) ? $_GET["classification_info"] : "";
$classification_info_supplied = ($classification_info != "");

$using_nre_classes = event_is_using_nre_classes($event, $key);

$has_preset_id = isset($_GET["member_id"]);
$is_preregistered_checkin = isset($_GET["checkin"]) && ($_GET["checkin"] == "true");
$is_member = false;
$member_id = "";
$pass_info_to_registration = "";

if ($has_preset_id) {
  if ($is_preregistered_checkin) {
    $prereg_id = $_GET["member_id"];
    $entrant_path = get_preregistered_entrant($prereg_id, $event, $key);
    $entrant_info = decode_preregistered_entrant($entrant_path, $event, $key);

    $first_name = $entrant_info["first_name"];
    $last_name = $entrant_info["last_name"];

    $pass_info_to_registration="&course={$entrant_info["course"]}";

    if (($entrant_info["member_id"] != "not_a_member") && ($entrant_info["member_id"] != "")) {
      $member_properties = get_member_properties(get_base_path($key));
      $club_name = get_club_name($key, $member_properties);
      $is_member = true;
      $member_id = $entrant_info["member_id"];
    }
    else if (isset($entrant_info["club_name"])) {
      $club_name = $entrant_info["club_name"];
    }
    else {
      $club_name = "unknown";
    }
  }
  else {
    $member_id = $_GET["member_id"];
  
    $member_properties = get_member_properties(get_base_path($key));
    $member_info = quick_get_member($quick_lookup_member_id, get_members_path($key, $member_properties));
  
    if (!isset($_GET["member_id"])) {
      error_and_exit("No member id specified, please restart registration.\n");
    }
  
    $is_member = true;
    $first_name = isset($member_info["first"]) ? $member_info["first"] : "";
    $last_name = isset($member_info["last"]) ? $member_info["last"] : "";
    $club_name = get_club_name($key, $member_properties);
  }
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
$birth_year = find_get_key_or_empty_string("birth_year");
$gender = find_get_key_or_empty_string("gender");


// Let's do some validations
if ($first_name == "") {
  error_and_exit("Invalid (empty) first name, please go back and enter a valid first name.\n");
}

if ($last_name == "") {
  error_and_exit("Invalid (empty) last name, please go back and enter a valid last name.\n");
}

if ($cell_phone == "") {
  error_and_exit("Invalid (empty) cell phone number, please go back and enter a valid emergency contact number (yours, a parent's, etc).\n");
}

if ($si_stick != "") {
  if (!preg_match("/^[0-9]+$/", $si_stick)) {
    error_and_exit("Invalid SI unit id \"{$si_stick}\", only numbers allowed.  Please go back and re-enter.\n");
  }
}

if ($waiver_signed != "signed") {
  error_and_exit("The waiver must be acknowledged in order to participate in this event.\n");
}

if ($using_nre_classes) {
  if ($birth_year != "") {
    if (!preg_match("/^\d{4}$/", $birth_year)) {
      error_and_exit("Birth year ({$birth_year}) must be 4 digits, please go back and re-enter.\n");
    }
    else {
      if (($birth_year < 1900) || ($birth_year > 2100)) {
        error_and_exit("Birth year ({$birth_year}) less than 1900 or greater than 2100, please go back and re-enter.\n");
      }
    }
  }

  if (($gender != "") && ($gender != "m") && ($gender != "f") && ($gender != "o")) {
    error_and_exit("Gender ({$gender}) has an unexpected value, please see the administrator or go back and re-enter.\n");
  }
}

$success_string = "";
$registration_pieces = array("first_name", base64_encode($first_name),
                              "last_name", base64_encode($last_name),
                              "club_name", base64_encode($club_name),
                              "si_stick", base64_encode($si_stick),
                              "email_address", base64_encode($email_address),
                              "cell_phone", base64_encode($cell_phone),
                              "car_info", base64_encode($car_info),
                              "member_id", base64_encode($is_member ? $member_id : ""),
			      "is_member", base64_encode($is_member ? "yes" : "no"));
if ($using_nre_classes) {
  if (($birth_year != "") || ($gender != "")) {
    $classification_info_hash = array();
    if ($classification_info_supplied) {
      $classification_info_hash = decode_entrant_classification_info($classification_info);
    }

    if ($birth_year != "") {
      $classification_info_hash["BY"] = $birth_year;
    }

    if ($gender != "") {
      $classification_info_hash["G"] = $gender;
    }

    $classification_info = encode_entrant_classification_info($classification_info_hash["BY"],
	                                                      $classification_info_hash["G"],
	                                                      $classification_info_hash["CLASS"]);
    $registration_pieces[]= "classification_info";
    $registration_pieces[] = base64_encode($classification_info);
  }
  elseif ($classification_info_supplied) {
    $registration_pieces[] = "classification_info";
    $registration_pieces[] = base64_encode($classification_info);
  }
}

$registration_info_string = implode(",", $registration_pieces);

// Redirect to the main registration screens
echo "<html><head><meta http-equiv=\"refresh\" content=\"0; URL=../OMeetRegistration/register.php?key={$key}&event={$event}&registration_info=${registration_info_string}{$pass_info_to_registration}&show_reregister_link=1\" /></head></html>";


// If the person is a member doing normal checkin,
// Check to see if we should email the webmaster to register the SI stick
$email_si_stick = isset($_GET["email_si_stick"]) ? $_GET["email_si_stick"] : "";
if (!$is_preregistered_checkin && $has_preset_id && ($email_si_stick != "")) {
  // check the properties - see if allowing email (should have been checked already)
  // echo "<p>Checking to email about {$email_si_stick}\n";
  $email_properties = get_email_properties(get_base_path($key));
  $email_enabled = isset($email_properties["from"]) && isset($email_properties["reply-to"]);
  // print_r($email_properties);
  if ($email_enabled && ($email_properties["email_to_register_stick"] != "")) {
    $email_addr = $email_properties["email_to_register_stick"];
    if (preg_match("/^[a-zA-z0-9_.\-]+@[a-zA-Z0-9_.\-]+/", $email_addr)) {
      $headers = array();
      $headers[] = "From: " . $email_properties["from"];
      $headers[] = "Reply-To: ". $email_properties["reply-to"];
      $headers[] = "MIME-Version: 1.0";
      $headers[] = "Content-type: text/html; charset=iso-8859-1";

      $header_string = implode("\r\n", $headers);

      $body_string = "<html><body>\r\nRegister SI {$email_si_stick}\r\nto {$first_name} {$last_name}.\r\n";
      $body_string .= "\r\n</body></html>";
    
      //echo "<p>Mail: Attempting mail send to {$email_addr} with results.\n";
      $subject = "Register SI {$email_si_stick} to {$first_name} {$last_name}";

      // echo "<p>Emailing to {$email_addr}\n";
      if (isset($email_properties["extra_params"]) && ($email_properties["extra_params"] != "")) {
        $email_send_result = mail($email_addr, $subject, $body_string, $header_string, $email_properties["extra_params"]);
      }
      else {
        $email_send_result = mail($email_addr, $subject, $body_string, $header_string);
      }

      // echo "<p> Mail sent " . ($email_send_result ? " successfully" : " failure")  . "\n";
    }
  }
}
?>
