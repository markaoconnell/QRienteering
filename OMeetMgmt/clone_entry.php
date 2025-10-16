<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$competitor = $_GET["competitor"];

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, cloning an entry cannot occur.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, cloning an entry cannot occur.\n");
}

$courses_path = get_courses_path($event, $key);
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

$competitor_path = get_competitor_path($competitor, $event, $key);
if (!is_dir($competitor_path)) {
  error_and_exit("<p>ERROR: No such competitor found {$competitor} (possibly already edited or removed?).\n");
}


$competitor_name = file_get_contents("{$competitor_path}/name");
$course = file_get_contents("{$competitor_path}/course");



// ###########################################
// Save a new competitor (a clone of this one)
// Let the user delete this competitor after making sure all is ok.
$error_string = "";
if ($error_string == "") {
  // Generate the competitor_id and make sure it is truly unique
  $new_competitor_name = "{$competitor_name}";
  $tries = 0;
  while ($tries < 5) {
    $new_competitor_id = uniqid();
    $new_competitor_path = get_competitor_path($new_competitor_id, $event, $key, "..");
    mkdir ($new_competitor_path, 0777);
    $new_competitor_file = fopen($new_competitor_path . "/name", "x");
    if ($new_competitor_file !== false) {
      break;
    }
    $tries++;
  }

  if ($tries === 5) {
    $output_string .= "ERROR Cannot register " . $new_competitor_name . " with id: " . $new_competitor_id . "\n";
    $error = true;
  }
  else {
    $output_string .= "<p>New entry created: " . $new_competitor_name . " on " . ltrim($course, "0..9-");

    // Save the information about the competitor
    fwrite($new_competitor_file, $new_competitor_name);
    fclose($new_competitor_file);
    file_put_contents("{$new_competitor_path}/course", $course);
    mkdir("./{$new_competitor_path}/controls_found");

    if (file_exists("{$competitor_path}/registration_info")) {
      $raw_registration_info = file_get_contents("{$competitor_path}/registration_info");
      file_put_contents("{$new_competitor_path}/registration_info", $raw_registration_info);
      if (file_exists("{$competitor_path}/si_stick")) {
        $si_stick = file_get_contents("{$competitor_path}/si_stick");
	file_put_contents("{$new_competitor_path}/si_stick", $si_stick);
	put_stick_xlation($event, $key, $new_competitor_id, $si_stick);
      }

      // Preserve the NRE classification info, if it is present
      if (event_is_using_nre_classes($event, $key) && competitor_has_class($competitor_path)) {
        set_class_for_competitor($new_competitor_path, get_class_for_competitor($competitor_path));
      }
    }

    if (file_exists("${competitor_path}/award_ineligible")) {
      file_put_contents("${new_competitor_path}/award_ineligible", "");
    }

    $output_string .= "<form action=\"./remove_from_event.php\">\n";
    $output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
    $output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
    $output_string .= "<input type=hidden name=Remove-{$competitor} value=\"1\">\n";
    $output_string .= "<input type=submit value=\"Remove prior entry for {$competitor_name}\">\n";
    $output_string .= "</form>\n\n";
    // Update the existing competitor name as having been overridden by the edits
    // if (substr($competitor_name, -4) != " (*)") {
    //   $updated_competitor_name = "{$competitor_name} (*)";
    //   file_put_contents("{$competitor_path}/name", $updated_competitor_name);
    // }
  }
}

// ###################################

echo get_web_page_header(true, true, false);

echo $output_string;

echo "<p><p><a href=\"./competitor_info.php?key={$key}&event={$event}\">Back to meet director information page</a>\n";

echo get_web_page_footer();
?>
