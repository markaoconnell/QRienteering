<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetRegistration/nre_class_handling.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$competitor = $_GET["competitor"];

$new_name = isset($_GET["new_name"]) ? $_GET["new_name"] : "";
$new_course = isset($_GET["new_course"]) ? $_GET["new_course"] : "";
$has_updated_values = isset($_GET["update_values"]);

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, cannot change course.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, cannot change course.\n");
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
$is_started = file_exists("{$competitor_path}/controls_found/start");
$is_finished = file_exists("{$competitor_path}/controls_found/finish");
$is_si_user = file_exists("{$competitor_path}/si_stick");
$is_self_reported = file_exists("{$competitor_path}/self_reported");
$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));
$course_list = array_filter($course_list,
	             function ($elt) use ($courses_path) { return (!file_exists("{$courses_path}/{$elt}/removed") && !file_exists("{$courses_path}/{$elt}/no_registrations")); });
$output_string = "";
$error_string = "";

// Conditions
// finished -> use edit_punches
// unstarted -> ok to change course
// self_reported - re-report the result

if ($is_finished) {
  // Redirect to edit_punches
  $output_string .= "<p>Competitor \"{$competitor_name}\" has finished, redirecting to <a href=\"./edit_punches.php?key=${key}&event={$event}&competitor={$competitor}&allow_editing=1\">" .
                    "Edit Punches interface</a> to change course.<br>\n";
  set_redirect("\n<meta http-equiv=\"refresh\" content=\"10; url=./edit_punches.php?key={$key}&event={$event}&competitor={$competitor}&allow_editing=1\">\n");
}
elseif ($is_self_reported) {
  $output_string .= "<p>Competitor \"{$competitor_name}\" has self-reported, change course by <a href=\"../OMeetRegistration/self_report_1.php?key={$key}&event={$event}\">self reporting a new result</a>.\n";
}
elseif ($is_started) {
  $output_string .= "<p>Competitor \"{$competitor_name}\" has is actively on the course, change the course after the course has been completed.\n";
}
else {
  // Ok to change the course
  if ($has_updated_values) {
    $updated_name = "";
    $updated_course = "";
    $updated_class = "";

    // Make sure that the newly specified course, if any, is valid
    if ($new_course != "") {
      if (!file_exists("{$courses_path}/{$new_course}")) {
        $error_string .= "<p>{$new_course} does not exist.\n";
      }
      elseif (file_exists("{$courses_path}/{$new_course}/removed") || file_exists("{$courses_path}/{$new_course}/no_registrations")) {
        $error_string .= "<p>{$new_course} is no longer accepting registrations.\n";
      }
      else {
        file_put_contents("{$competitor_path}/course", $new_course);
	if ($new_course != $course) {
	  $updated_course = $new_course;
	}

        // Given that the person may be on a new course, make sure that their competitive class is still correct
        if (event_is_using_nre_classes($event, $key) && competitor_has_class($competitor_path) && ($new_course != $course)) {
          if (file_exists("{$new_competitor_path}/registration_info")) {
            $registration_info = parse_registration_info(file_get_contents("{$new_competitor_path}/registration_info"));
            if (isset($registration_info["classification_info"])) {
              $classification_hash = decode_entrant_classification_info($registration_info["classification_info"]);
              if (($classification_hash["BY"] != "") && ($classification_hash["G"] != "")) {
                $updated_class = get_nre_class($event, $key, $classification_hash["G"], $classification_hash["BY"], $new_course, file_exists("{$new_competitor_path}/si_stick"));
              }
            }
          }
          if ($updated_class != "") {
            set_class_for_competitor($new_competitor_path, $updated_class);
          }
          else {
            remove_class_for_competitor($new_competitor_path);
          }
        }
      }
    }

    if ($new_name != "") {
      file_put_contents("{$competitor_path}/name", $new_name);
      if ($new_name != $competitor_name) {
        $updated_name = $new_name;
      }
    }

    $output_string .= "Competitor " . (($updated_name != "") ? "\"{$updated_name}\"" : "\"{$competitor_name}\" (unchanged)") .
	                  " is now on " . (($updated_course != "") ? ltrim($updated_course, "0..9-") : ltrim($course, "0..9-") . " (unchanged)") .
	                  (event_is_using_nre_classes($event, $key) ? (($updated_class != "") ? " ({$updated_class})" : " (Rec - unranked)") : "") . "\n";
  }
  else {
    $output_string .= "<p><form action=./change_course.php>\n";
    $output_string .= "<input type=hidden name=key value={$key}>\n<input type=hidden name=event value={$event}>\n";
    $output_string .= "<input type=hidden name=competitor value={$competitor}>\n<input type=hidden name=update_values value=1>\n";
    $output_string .= "<p>Competitor name: <input type=text name=new_name value=\"{$competitor_name}\">\n";
    $output_string .= "<p>New course:<br>\n";
    $output_string .= "<ul>" . implode("\n", array_map(function ($elt) use ($course) { return ("<li> <input type=radio name=new_course value={$elt}" .
	                                                                         (($elt == $course) ? " checked" : "") . "> " . (ltrim($elt, "0..9-"))); }, $course_list)) . "\n</ul>\n";
    $output_string .= "<input type=submit value=\"Update course and/or name\">\n";
    $output_string .= "</form>\n";
  }

}


// ###################################

echo get_web_page_header(true, true, false);

echo $error_string;
echo $output_string;

echo "<p><p><a href=\"./competitor_info.php?key={$key}&event={$event}\">Back to meet director information page</a>\n";

echo get_web_page_footer();
?>
