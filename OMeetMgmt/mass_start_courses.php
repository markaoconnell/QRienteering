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

$universal_start = !$si_time_supplied && isset($_GET["universal_start"]) && $_GET["universal_start"] == "yes";

if (!key_is_valid($key)) {
  error_and_exit("Unknown management key \"{$key}\", are you using an authorized link?\n");
}

set_timezone($key);

$courses_to_start = explode(",", $_GET["courses_to_start"]);
//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
$event_path = get_event_path($event, $key, "..");
$courses_path = get_courses_path($event, $key, "..");

$parseable_status = "\n<!--\n";
if (($event != "") && file_exists($event_path) && is_dir($courses_path)) {

  // Make sure the courses are valid
  $validated_courses = array_filter($courses_to_start, function ($course) use ($courses_path) { return(file_exists("{$courses_path}/{$course}/controls.txt")); } );
  $invalid_courses = array_diff($courses_to_start, $validated_courses);

  // Get the list of competitors who haven't started and are on one of the courses to mass_start
  // Generate a start time for the si stick users - these generally seem to be timestamps for the current day,
  // so ignore everything but the hour, minute, second for today and use that as the si stick start time
  if (!$si_time_supplied) {
    $mass_start_time = time();
    $si_stick_mass_start_pieces = explode(":", date_format(date_create("@{$mass_start_time}"), "H:i:s"));
    $si_stick_start_time = ($si_stick_mass_start_pieces[0] * 3600) + ($si_stick_mass_start_pieces[1] * 60) + $si_stick_mass_start_pieces[0];
  }


  $competitor_path = get_competitor_directory($event, $key, "..");
  $competitor_list = scandir($competitor_path);
  $competitor_list = array_diff($competitor_list, array(".", ".."));

  foreach ($competitor_list as $competitor) {
    $course_for_competitor = file_get_contents("{$competitor_path}/{$competitor}/course");
    $competitor_started = false;
    $competitor_already_started = false;
    if (in_array($course_for_competitor, $validated_courses)) {
      $competitor_name = file_get_contents("{$competitor_path}/{$competitor}/name");

      if (file_exists("{$competitor_path}/{$competitor}/si_stick")) {  # SI Unit competitor
        if ($si_time_supplied || $universal_start) {
          if (!file_exists("{$competitor_path}/{$competitor}/mass_si_stick_start") && !file_exists("{$competitor_path}/{$competitor}/controls_found/start")) {
            file_put_contents("{$competitor_path}/{$competitor}/mass_si_stick_start", $si_stick_start_time);
            $competitor_started = true;
            if ($universal_start) {
              file_put_contents("{$competitor_path}/{$competitor}/raw_mass_start_time", date_format(date_create("@{$mass_start_time}"), "H:i:s"));
            }
          }
          else {
            $competitor_already_started = true;
          }
        }
      }
      else {   # QR competitor
        if (!$si_time_supplied) {
          if (!file_exists("{$competitor_path}/{$competitor}/controls_found/start")) {
            file_put_contents("{$competitor_path}/{$competitor}/controls_found/start", $mass_start_time);
            $competitor_started = true;
          }
          else {
            $competitor_already_started = true;
          }
        }
      }

      if ($competitor_started) {
        $started_array[] = "{$competitor_name} on " . ltrim($course_for_competitor, "0..9-");
        $parseable_status .= "####,STARTED,{$competitor_name},$course_for_competitor\n";
      }

      if ($competitor_already_started) {
        $already_started_array[] = "{$competitor_name} already on " . ltrim($course_for_competitor, "0..9-");
        $parseable_status .= "####,ALREADY_STARTED,{$competitor_name},$course_for_competitor\n";
      }
    }
  }
}
else {
  $error_string .= "<p>ERROR: No event or bad event ({$event}) specified, bad link?\n";
  $parseable_status .= "####,ERROR,No event or bad event ({$event})";
}


echo get_web_page_header(true, false, true);

if ($error_string == "") {
  if (count($already_started_array) > 0) {
    echo "<p>Competitors started BEFORE the mass start:\n";
    echo "<ul><li>" . join("</li>\n<li>", $already_started_array) . "</li></ul>\n";
  }

  if (count($invalid_courses) > 0) {
    echo "<p>Bad courses specified, no competitors started:\n";
    echo "<ul><li>" . join("</li>\n<li>", $invalid_courses) . "</li></ul>\n";
    $parseable_status .= ("\n" . join("\n", array_map(function ($elt) { return("####,BAD_COURSE,$elt"); }, $invalid_courses)) . "\n");
  }

  if (count($started_array) > 0) {
    echo "<p>Competitors started correctly:\n";
    echo "<ul><li>" . join("</li>\n<li>", $started_array) . "</li></ul>\n";
  }
  else {
    echo "<p>No competitors started - second mass start???";
  }
}
else {
  echo "<p>ERROR: $error_string\n<p>No courses started.\n";
}

echo "{$parseable_status}\n-->\n";

echo get_web_page_footer();
?>
