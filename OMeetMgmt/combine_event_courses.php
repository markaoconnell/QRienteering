<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

set_page_title("Combine Courses for an Event");

function is_event_open($filename) {
  global $base_path, $key;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function is_event_recently_closed($filename) {
  global $base_path, $recent_event_cutoff, $key;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && file_exists("{$base_path}/{$filename}/done") &&
          (stat("{$base_path}/{$filename}/done")["mtime"] > $recent_event_cutoff));
}


$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

$event = isset($_GET["event"]) ? $_GET["event"] : "";
if ($event != "") {
  $event_path = get_event_path($event, $key);
  if (!is_dir($event_path)) {
    error_and_exit("Invalid combination, key \"{$key}\" and event \"{$event}\", please retry.\n");
  }
}
else {
  $event_path = "";
}

if ($event_path != "") {
  $courses_path = get_courses_path($event, $key);
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  $output .= "<p>Choose courses to combine for " . file_get_contents("{$event_path}/description") . "<p>\n";
  $output .= "<form action=\"./combine_event_courses_2.php\">\n";
  $output .= "<input type=hidden name=\"key\" value=\"{$key}\">\n";
  $output .= "<input type=hidden name=\"event\" value=\"{$event}\">\n";
  $output .= "<ul>\n";
  $output .= implode("\n", array_map(function ($elt)
                             { return ("<li><input type=checkbox name=course-{$elt} value=1> " . ltrim($elt, "0..9-")); },
                             $course_list));

  $output .= "</ul>\n";
  $output .= "<p><input type=submit>\n</form>\n";
}
else {
  $base_path = get_base_path($key);
  if (!is_dir($base_path)) {
    // Note: This will not create the full directory path, only the bottom directory if it does not exist
    // Not sure if I want to change this or not, I'll leave it alone for the moment
    mkdir($base_path);
    #error_and_exit("No event directory found, please contact administrator to create \"{$base_path}\"");
  }
  
  
  if (isset($_GET["recent_event_timeout"])) {
    $recent_event_timeout = time_limit_to_seconds($_GET["recent_event_timeout"]);
  }
  else {
    $recent_event_timeout = 86400 * 7;  // Seven day cutoff
  }
  
  $recent_event_cutoff = time() - $recent_event_timeout;
  
  $event_list = scandir($base_path);
  $open_event_list = array_filter($event_list, "is_event_open");
  $closed_event_list = array_filter($event_list, "is_event_recently_closed");
  

  $output = "<br>\n<p>Orienteering Event Combine Results within an event\n<p>\n<p> Choose the event whose results should be combined\n";
  $output .= "<ul>\n";
  $output .= implode("\n", array_map(function ($elt) use ($base_path, $key)
                           { return (
                            "<li><a href=\"./combine_event_courses.php?key={$key}&event={$elt}\">" . (file_get_contents("{$base_path}/{$elt}/description")) . "</a>" );  }, 
                            $open_event_list));
  $output .= implode("\n", array_map(function ($elt) use ($base_path, $key)
                           { return (
                            "<li><a href=\"./combine_event_courses.php?key={$key}&event={$elt}\">" . (file_get_contents("{$base_path}/{$elt}/description")) . "</a>" );  }, 
                           $closed_event_list));
  
  $output .= "</ul>\n";
}
echo get_web_page_header(true, false, false);

echo $output;

echo get_web_page_footer();
?>
