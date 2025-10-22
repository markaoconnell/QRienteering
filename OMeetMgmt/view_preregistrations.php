<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetWithMemberList/preregistration_routines.php';
require '../OMeetRegistration/nre_class_handling.php';


// Return a hash from the readable course name to the unique course name
// e.g. White -> 00-White, BrownX -> 05-BrownX
function get_course_hash($event, $key) {
  $courses_path = get_courses_path($event, $key);
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  // Strip off the leading digits and hyphen to get the readable course name
  $course_hash = array();
  foreach ($course_list as $this_course) {
    $course_hash[ltrim($this_course, "0..9-")] = $this_course;
  }

  return ($course_hash);
}

function register_competitor($entrant_info) {
  global $key, $event, $course_hash, $show_id_for_auto_start;

  //print_r($entrant_info);

  if (!isset($course_hash[$entrant_info["course"]])) {
    return(array("ERROR", "Invalid course {$entrant_info["course"]} for entrant {$entrant_info["first_name"]} {$entrant_info["last_name"]}"));
  }

  if (!isset($entrant_info["stick"]) || ($entrant_info["stick"] == "")) {
    return(array("ERROR", "No si unit specified for entrant {$entrant_info["first_name"]} {$entrant_info["last_name"]}"));
  }

  // Get the unique id for the competitor
  $tries = 0;
  while ($tries < 5) {
    $competitor_id = uniqid();
    $competitor_path = get_competitor_path($competitor_id, $event, $key);
    mkdir ($competitor_path, 0777);
    $competitor_file = fopen("{$competitor_path}/name", "x");
    if ($competitor_file !== false) {
      break;
    }
    $tries++;
  }

  if ($tries === 5) {
    return(array("ERROR", "Internal error during registration for entrant {$entrant_info["first name"]} {$entrant_info["last_name"]}"));
  }


  $saved_competitor_name = "{$entrant_info["first_name"]} {$entrant_info["last_name"]}";

  //echo "<p>Got competitor id ${competitor_id} for {$saved_competitor_name}\n";

  // Save the information about the competitor
  fwrite($competitor_file, $saved_competitor_name);
  fclose($competitor_file);
  file_put_contents("{$competitor_path}/course", $course_hash[$entrant_info["course"]]);
  file_put_contents("{$competitor_path}/si_stick", $entrant_info["stick"]);
  put_stick_xlation($event, $key, $competitor_id, $entrant_info["stick"]);
  mkdir("./{$competitor_path}/controls_found");

  // Add the registration information
  $first_name = isset($entrant_info["first_name"]) ? $entrant_info["first_name"] : "";
  $last_name = isset($entrant_info["last_name"]) ? $entrant_info["last_name"] : "";
  $start_time = isset($entrant_info["start_time"]) ? $entrant_info["start_time"] : "";
  $stick = isset($entrant_info["stick"]) ? $entrant_info["stick"] : "";
  $email_address = isset($entrant_info["email_address"]) ? $entrant_info["email_address"] : "";
  $cell_phone = isset($entrant_info["cell_phone"]) ? $entrant_info["cell_phone"] : "";
  $club_name = isset($entrant_info["club_name"]) ? $entrant_info["club_name"] : "";
  $waiver_signed = isset($entrant_info["waiver_signed"]) ? $entrant_info["waiver_signed"] : "";

  //echo "<p>Setting Registration info : ${competitor_id} for {$saved_competitor_name}\n";
  // NRE information (optional)
  $birth_year = isset($entrant_info["birth_year"]) ? $entrant_info["birth_year"] : "";
  $gender = isset($entrant_info["gender"]) ? $entrant_info["gender"] : "";
  $competitive_class = isset($entrant_info["class"]) ? $entrant_info["class"] : "";
  $award_eligibility = isset($entrant_info["award_eligibility"]) ? $entrant_info["award_eligibility"] : "";
  $classification_info = encode_entrant_classification_info($birth_year, $gender, $competitive_class);

  $registration_info = implode(",", array("email_address", base64_encode($email_address),
	                                  "AutoStarted", base64_encode("yes"),
                                          "first_name", base64_encode($first_name),
                                          "last_name", base64_encode($last_name),
                                          "start_time", base64_encode($start_time),
                                          "si_stick", base64_encode($stick),
                                          "cell_phone", base64_encode($cell_phone),
                                          "club_name", base64_encode($club_name),
                                          "waiver_signed", base64_encode($waiver_signed),
                                          "award_eligibility", base64_encode($award_eligibility),
                                          "classification_info", base64_encode($classification_info)));
  file_put_contents("{$competitor_path}/registration_info", $registration_info);

  $lower_case_award_eligibility = strtolower($award_eligibility);
  if (($lower_case_award_eligibility == "n") || ($lower_case_award_eligibility == "no")) {
    file_put_contents("{$competitor_path}/award_ineligible", "");
  }


  //echo "<p>Setting NRE info : ${competitor_id} for {$saved_competitor_name}\n";
  // Handle the processing of the OUSA classes if necessary
  if (event_is_using_nre_classes($event, $key)) {
    if ($competitive_class != "") {
      set_class_for_competitor($competitor_path, $competitive_class);
    }

    // Now lookup to see what the class is, if necessary
    if (($competitive_class == "") && ($birth_year != "") && ($gender != "")) {
	    // echo "Looking up class for {$birth_year} and {$gender}\n";
	// Final parameter is true - when autostarting, must always be with a si unit, not for QRienteering
  //echo "<p>Getting NRE info : ${competitor_id} for {$saved_competitor_name} with {$birth_year} and {$gender}\n";
      $entrant_class = get_nre_class($event, $key, $gender, $birth_year, $course_hash[$entrant_info["course"]], true);
  //echo "<p>Got NRE info {$entrans_class} : ${competitor_id} for {$saved_competitor_name} with {$birth_year} and {$gender}\n";
      if ($entrant_class != "") {
        set_class_for_competitor($competitor_path, $entrant_class);
        $competitive_class = $entrant_class;
      }
    }
  }

  //echo "<p>All seems ok for : ${competitor_id} for {$saved_competitor_name}\n";
  return(array("OK", "Registered {$first_name} {$last_name}" . ($show_id_for_auto_start ?  "--{$competitor_id}--" : "" ) . " on {$entrant_info["course"]}" .
	                  (($competitive_class != "") ? " ({$competitive_class})" : "")));
}

ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

$found_error = false;
$error_string = "";

$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key))) {
  error_and_exit("No directory found for events, is your key \"{$key}\" valid?\n");
}

$event = isset($_GET["event"]) ? $_GET["event"] : "";
$event_path = get_event_path($event, $key);
if (!is_dir($event_path)) {
  error_and_exit("No event directory found, is \"{$event}\" from a valid link?\n");
}

$show_id_for_auto_start = isset($_GET["auto_start_show_id"]) && ($_GET["auto_start_show_id"] == "true");
$auto_start = isset($_GET["auto_start"]) && ($_GET["auto_start"] == "true");
$course_hash = array();
if ($auto_start) {
  $course_hash = get_course_hash($event, $key);
}


$output_string = "";
$errors_string = "";
$preregistration_currently_allowed = preregistrations_allowed($event, $key);
if ($preregistration_currently_allowed) {
  $prereg_info = read_preregistrations($event, $key);
  $prereg_list = $prereg_info["members_hash"];
  foreach ($prereg_list as $prereg_entry) {
    $entrant_info = $prereg_entry["entrant_info"];

    if ($auto_start) {
      $result = register_competitor($entrant_info);
      if ($result[0] == "ERROR") {
        $errors_string .= "<p>{$result[1]}\n";
      }
      else {
        $output_string .= "<p>{$result[1]}\n";
      }
    }
    else {
      $fields = array();
      $fields[] = $entrant_info["first_name"];
      $fields[] = $entrant_info["last_name"];
      $fields[] = $entrant_info["course"];
      $fields[] = $entrant_info["start_time"];
      $fields[] = $entrant_info["stick"];
      $fields[] = $entrant_info["cell_phone"];
      $fields[] = $entrant_info["email_address"];
      $fields[] = $entrant_info["club_name"];
      $fields[] = $entrant_info["waiver_signed"];
      $fields[] = isset($entrant_info["birth_year"]) ? $entrant_info["birth_year"] : "";
      $fields[] = isset($entrant_info["gender"]) ? $entrant_info["gender"] : "";
      $fields[] = isset($entrant_info["class"]) ? $entrant_info["class"] : "";
      $fields[] = isset($entrant_info["award_eligibility"]) ? $entrant_info["award_eligibility"] : "";
      $output_string .= "<p>" . implode(",", $fields) . "\n";
    }
  }

  if ((count($prereg_list) > 0) && !$auto_start) {
    $output_string .= "<p><p><form action=\"./view_preregistrations.php\">\n";
    $output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
    $output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
    $output_string .= "<input type=hidden name=auto_start value=\"true\">\n";
    $output_string .= "<input type=submit value=\"Auto start all preregistered entrants\">\n";
    $output_string .= "</form>\n";
  }
}
else {
  $output_string .= "<p>Preregistration currently <u>disabled</u>, cannot view preregistered entrants.\n";
}


$current_event_name = file_get_contents("{$event_path}/description");

if ($auto_start) {
  echo "<p>AutoStarted entrants for: <strong>{$current_event_name}</strong>\n";
  if ($errors_string != "") {
    echo "<p><strong>Entries with errors</strong>:{$errors_string}<p><p>\n";
  }
  echo "<p><strong>Successful entries</strong>:{$output_string}\n";
}
else {
  echo "<p>View preregistrations: <strong>{$current_event_name}</strong>\n";
  echo $output_string;
}
echo "<p><p><a href=\"./event_management.php?key={$key}&event={$event}\">Return to event mangement page</a>\n";

echo get_web_page_footer();
?>
