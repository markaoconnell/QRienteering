<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require 'preregistration_routines.php';

ck_testing();

echo get_web_page_header(true, false, true);

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}


function is_event_preregistration_enabled($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done") &&
          preregistrations_allowed_by_event_path("{$base_path}/{$filename}"));
}

function name_to_link($event_id) {
  global $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./checkin_preregistered.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
}

echo "<p>\n";

$base_path = get_base_path($key, "..");

$event = isset($_GET["event"]) ? $_GET["event"] : "";
//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
if (strcmp($event, "") == 0) {
  $event_list = scandir($base_path);
  //print_r($event_list);
  $event_list = array_filter($event_list, "is_event_preregistration_enabled");
  //print_r($event_list);
  if (count($event_list) == 1) {
    $event = basename(current($event_list));
    //echo "Identified event as ${event}\n<p>";
  }
  else if (count($event_list) > 1) {
    $event_output_array = array_map(name_to_link, $event_list);
    echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
    return;
  }
  else {
    echo "<p>No available events for emailing preregistration links.\n";
    return;
  }
}

if (file_exists("{$base_path}/{$event}/done")) {
  error_and_exit("Event " . file_get_contents("{$base_path}/{$event}/description") . " has completed and checkins are no longer possible.\n");
}

$event_name = file_get_contents("{$base_path}/{$event}/description");

$courses_array = scandir(get_courses_path($event, $key, ".."));
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
$course_map_array = array_map(function ($elt) { return (array(ltrim($elt, "0..9-"), $elt)); }, $courses_array);


// Figure out the full URL to use for the emailed links
if (use_secure_http_for_qr_codes()) {
  $proto = "https://";
  $port = get_secure_http_port_spec();
}
else {
  $proto = "http://";
  $port = get_http_port_spec();
}

$url_prefix = $proto . $_SERVER["SERVER_NAME"] . $port . dirname(dirname($_SERVER["REQUEST_URI"]));
while (substr($url_prefix, -1) == "/") {
  $url_prefix = substr($url_prefix, 0, -1);
}

$preregistration_list = read_preregistrations($event, $key);

foreach ($preregistration_list["members_hash"] as $prereg_entry_hash) {
  $entrant_info = $prereg_entry_hash["entrant_info"];
  $is_member = (($entrant_info["member_id"] != "not_a_member") && ($entrant_info["member_id"] != ""));
  
  $registration_info_string = implode(",", array("first_name", base64_encode($entrant_info["first_name"]),
                                                 "last_name", base64_encode($entrant_info["last_name"]),
                                                 "club_name", base64_encode($entrant_info["club_name"]),
                                                 "si_stick", base64_encode($entrant_info["stick"]),
                                                 "email_address", base64_encode($entrant_info["email_address"]),
                                                 "cell_phone", base64_encode($entrant_info["cell_phone"]),
                                                 "waiver_signed", base64_encode($entrant_info["waiver_signed"]),
					         "member_id", base64_encode($is_member ? $entrant_info["member_id"] : ""),
						 "is_member", base64_encode($is_member ? "yes" : "no"),
						 "preregistration", base64_encode("yes")));

  // Convert the readable course name to the unique course name
  $unique_course = array_filter($course_map_array, function ($elt) use ($entrant_info) { return ($elt[0] == $entrant_info["course"]); });
  if (count($unique_course) == 1) {
    $valid_course = true;
    $valid_course_name = array_values($unique_course)[0][1];
  }
  else {
    $valid_course = false;
    $valid_course_name = "";
  }

  // Redirect to the main registration screens
  $competitor_name = "{$entrant_info["first_name"]} {$entrant_info["last_name"]}";
  $email_link_choose_course = "{$url_prefix}/OMeetRegistration/register.php?key={$key}&registration_info=${registration_info_string}&event={$event}&course={$entrant_info["course"]}";
  $email_link_all_correct = "{$url_prefix}/OMeetRegistration/register_competitor.php?key={$key}&competitor_name={$competitor_name}&registration_info=${registration_info_string}&event={$event}&course={$valid_course_name}";
  $email_link_checkin = "{$url_prefix}/OMeetWithMemberList/name_lookup.php?key={$key}&competitor_first_name={$entrant_info["first_name"]}&competitor_last_name={$entrant_info["last_name"]}&event={$event}&checkin=true";

  $email_body_string = "";
  $email_body_string .= "<p>Welcome {$competitor_name}, you are registered at {$event_name}, using " .
	  (($entrant_info["stick"] != "") ? "SI unit {$entrant_info["stick"]}" : "QR codes");
  if ($valid_course) {
    $email_body_string .= " on {$entrant_info["course"]}\n";
  }
  else {
    $email_body_string .= ", course still to be chosen.\n";
  }
  $email_body_string .= "<p>For safety, your cell phone is listed as: {$entrant_info["cell_phone"]}\n";
  if ($entrant_info["email_address"] != "") {
    $email_body_string .= "<p>Your results will be emailed to: {$entrant_info["email_address"]}\n";
  }
  else {
    $email_body_string .= "<p>No email provided, you may collect your results at the event.\n";
  }
  if ($valid_course) {
    $email_body_string .= "<p><a href=\"{$email_link_all_correct}\">Confirm, all information is correct</a>\n";
  }
  $email_body_string .= "<p><a href=\"{$email_link_choose_course}\">Change course but information otherwise correct</a>\n";
  $email_body_string .= "<p><a href=\"{$email_link_checkin}\">Adjust information</a>\n";
  $email_body_string .= "<p>If you are no longer attending this meet, please just ignore this email.\n";


  $email_properties = get_email_properties(get_base_path($key, ".."));
  //print_r($email_properties);
  $email_enabled = isset($email_properties["from"]) && isset($email_properties["reply-to"]);
  if (($entrant_info["email_address"] != "") && $email_enabled) {
    // See if this looks like a valid email
    // Make sure to escape anything that could be a funky html character
    $email_addr = htmlentities($entrant_info["email_address"]);
    if (preg_match("/^[a-zA-z0-9_.\-]+@[a-zA-Z0-9_.\-]+/", $email_addr)) {
      $headers = array();
      $headers[] = "From: " . $email_properties["from"];
      $headers[] = "Reply-To: ". $email_properties["reply-to"];
      $headers[] = "MIME-Version: 1.0";
      $headers[] = "Content-type: text/html; charset=iso-8859-1";

      $header_string = implode("\r\n", $headers);

      $email_extra_info_file = get_email_extra_info_file(get_base_path($key, ".."));
      if (file_exists($email_extra_info_file)) {
        $extra_info = "<p><p><hr><p>" . implode("\r\n", file($email_extra_info_file));
      }
      else {
        $extra_info = "";
      }
      $body_string = "<html><body>\r\n" .
                     wordwrap("{$email_body_string}\r\n", 70, "\r\n");

      $body_string .= wordwrap("{$extra_info}\r\n", 70, "\r\n") . "\r\n</body></html>";
      
      //echo "<p>Mail: Attempting mail send to {$email_addr} with results.\n";
      if (isset($email_properties["preregistration_subject"])) {
        $subject = $email_properties["preregistration_subject"];
      }
      else {
        $subject = "Orienteering Preregistration Entry Confirmation";
      }
      $email_send_result = mail($email_addr, $subject, $body_string, $header_string);

      if ($email_send_result) {
        echo "<p>Mail: Sent preregistration confirmation to {$competitor_name} @ {$email_addr}.\n";
      }
      else {
        echo "<p>Mail: Failed when sending preregisration confirmation to {$competitor_name} @ {$email_addr}\n";
      }
    }
  }
}

echo get_web_page_footer();
?>
