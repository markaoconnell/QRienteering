<?php
require '../OMeetCommon/common_routines.php';

ck_testing();


echo "<p>\n";
$error_string = "";
$already_started_array = array();
$started_array = array();

$event = $_GET["event"];
$key = $_GET["key"];

if (isset($_GET["si_stick_time"])) {
  $si_stick_start_time = $_GET["si_stick_time"];
  $si_time_supplied = true;

  if (($si_stick_start_time < 0) || ($si_stick_start_time > 86400)) {
    error_and_exit("Malformatted start time of {$si_stick_start_time}, must be between 0 and 86400 (one day)\n");
  }
}
else {
  $si_time_supplied = false;
  $si_stick_start_time = 0;
}

if (!key_is_valid($key)) {
  error_and_exit("Unknown management key \"{$key}\", are you using an authorized link?\n");
}

set_timezone($key);

$courses_to_start = explode(",", $_GET["courses_to_start"]);
//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
$event_path = get_event_path($event, $key, "..");
$courses_path = get_courses_path($event, $key, "..");
if (($event != "") && file_exists($event_path) && is_dir($courses_path)) {

  // Make sure the courses are valid
  $validated_courses = array_filter($courses_to_start, function ($course) use ($courses_path) { return(file_exists("{$courses_path}/{$course}/controls.txt")); } );
  $invalid_courses = array_diff($courses_to_start, $validated_courses);

  // Get the list of competitors who haven't started and are on one of the courses to mass_start
  // Generate a start time for the si stick users - these generally seem to be timestamps for the current day,
  // so ignore everything but the hour, minute, second for today and use that as the si stick start time
  if (!$si_time_supplied) {
    $mass_start_time = time();
    $si_stick_mass_start_pieces = explode(":", strftime("%T", $mass_start_time));
    $si_stick_start_time = ($si_stick_mass_start_pieces[0] * 3600) + ($si_stick_mass_start_pieces[1] * 60) + $si_stick_mass_start_pieces[0];
  }


  $competitor_path = get_competitor_directory($event, $key, "..");
  $competitor_list = scandir($competitor_path);
  $competitor_list = array_diff($competitor_list, array(".", ".."));

  foreach ($competitor_list as $competitor) {
    $course_for_competitor = file_get_contents("{$competitor_path}/{$competitor}/course");
    if (in_array($course_for_competitor, $validated_courses)) {
      $competitor_name = file_get_contents("{$competitor_path}/{$competitor}/name");

      if (!file_exists("{$competitor_path}/{$competitor}/controls_found/start")) {
        if (!file_exists("{$competitor_path}/{$competitor}/si_stick")) {
          if (!$si_time_supplied) {
            $started_array[] = "{$competitor_name} on " . ltrim($course_for_competitor, "0..9-");
            file_put_contents("{$competitor_path}/{$competitor}/controls_found/start", $mass_start_time);
          }
        }
        else {
          file_put_contents("{$competitor_path}/{$competitor}/mass_si_stick_start", $si_stick_start_time);
          $started_array[] = "{$competitor_name} on " . ltrim($course_for_competitor, "0..9-");
          if (!$si_time_supplied) {
            file_put_contents("{$competitor_path}/{$competitor}/raw_mass_start_time", strftime("%T", $mass_start_time));
          }
        }
      }
      else {
        $already_started_array[] = "{$competitor_name} already on " . ltrim($course_for_competitor, "0..9-");
      }
    }
  }
}
else {
  $error_string .= "<p>ERROR: No event or bad event ({$event}) specified, bad link?\n";
}


echo get_web_page_header(true, false, true);

if ($error_string == "") {
  if (count($already_started_array) > 0) {
    echo "<p>Competitors started BEFORE the mass start:\n";
    echo "<ul><li>" . join("</li><li>", $already_started_array) . "</li></ul>";
  }

  if (count($invalid_courses) > 0) {
    echo "<p>Bad courses specified, no competitors started:\n";
    echo "<ul><li>" . join("</li><li>", $invalid_courses) . "</li></ul>";
  }

  if (count($started_array) > 0) {
    echo "<p>Competitors started correctly:\n";
    echo "<ul><li>" . join("</li><li>", $started_array) . "</li></ul>";
  }
  else {
    echo "<p>No competitors started - second mass start???";
  }
}
else {
  echo "<p>ERROR: $error_string\n<p>No courses started.\n";
}

echo get_web_page_footer();
?>
