<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetWithMemberList/preregistration_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

$found_error = false;
$error_string = "";

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key, ".."))) {
  error_and_exit("No directory found for events, is your key \"{$key}\" valid?\n");
}

$event = $_GET["event"];
$event_path = get_event_path($event, $key, "..");
if (!is_dir($event_path)) {
  error_and_exit("No event directory found, is \"{$event}\" from a valid link?\n");
}


$output_string = "";
$preregistration_currently_allowed = preregistrations_allowed($event, $key);
if ($preregistration_currently_allowed) {
  $prereg_info = read_preregistrations($event, $key);
  $prereg_list = $prereg_info["members_hash"];
  foreach ($prereg_list as $prereg_entry) {
    $entrant_info = $prereg_entry["entrant_info"];

    $fields = array();
    $fields[] = $entrant_info["first_name"];
    $fields[] = $entrant_info["last_name"];
    $fields[] = $entrant_info["course"];
    $fields[] = $entrant_info["stick"];
    $fields[] = $entrant_info["cell_phone"];
    $fields[] = $entrant_info["email_address"];
    $fields[] = $entrant_info["club_name"];
    $fields[] = $entrant_info["waiver_signed"];
    $fields[] = isset($entrant_info["birth_year"]) ? $entrant_info["birth_year"] : "";
    $fields[] = isset($entrant_info["gender"]) ? $entrant_info["gender"] : "";
    $fields[] = isset($entrant_info["class"]) ? $entrant_info["class"] : "";
    $output_string .= "<p>" . implode(",", $fields) . "\n";
  }
}
else {
  $output_string .= "<p>Preregistration currently <u>disabled</u>, cannot view preregistered entrants.\n";
}


$current_event_name = file_get_contents("{$event_path}/description");

echo "<p>View preregistrations: <strong>{$current_event_name}</strong>\n";
echo $output_string;
echo "<p><p><a href=\"./event_management.php?key={$key}&event={$event}\">Return to event mangement page</a>\n";

echo get_web_page_footer();
?>
