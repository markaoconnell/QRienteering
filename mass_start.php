<?php
require 'common_routines.php';

ck_testing();

echo get_web_page_header(true, false, true);


// Get some phpinformation, just in case
// Verify that php is running properly
// echo 'Current PHP version: ' . phpversion();
// phpinfo();

function is_event($filename) {
  return (is_dir($filename) && (substr($filename, strlen($filename) - 5) == "Event"));
}

function name_to_link($pathname) {
  $final_element = basename($pathname);
  return ("<li><a href=./mass_start.php?event=${final_element}>${final_element}</a>\n");
}

echo "<p>\n";
$output_string = "";
$mass_start_courses = array();

$event = $_GET["event"];
//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
if ($event == "") {
  $event_list = scandir("./");
  //print_r($event_list);
  $event_list = array_values(array_filter($event_list, is_event));
  //print_r($event_list);
  if (count($event_list) == 1) {
    $event = basename($event_list[0]);
    //echo "Identified event as ${event}\n<p>";
  }
  else if (count($event_list) > 1) {
    $event_output_array = array_map(name_to_link, $event_list);
    $output_string .= "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
  }
  else {
    $output_string .= "<p>No available events.\n";
  }
}

if ($event != "") {
  $mass_start_courses = array_values(array_filter($_GET, function ($key) {
                                        return ((strpos($key, "mass_start_") === 0) && ($_GET[$key] != "")); },  ARRAY_FILTER_USE_KEY));

  if (count($mass_start_courses) == 0) {
    $courses_array = scandir("./${event}/Courses");
    $courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
    // print_r($courses_array);
  
    if (count($courses_array) == 1) {
      $mass_start_courses[0] = $courses_array[0];
    }
    else {
      $output_string .= "<p>\n";
      
      $output_string .= "<p>Mass start for orienteering event: ${event}\n<br>";
      $output_string .= "<form action=\"./mass_start.php\">\n";
      
      $output_string .= "<br><p>Select one or more courses:<br>\n";
      foreach ($courses_array as $course_name) {
        $output_string .= "<input type=\"checkbox\" name=\"mass_start_{$course_name}\" value=\"{$course_name}\">" . ltrim($course_name, "0..9-") . " <br>\n";
      }
      
      $output_string .= "<input type=\"hidden\" name=\"event\" value=\"{$event}\">\n";
      $output_string .= "<input type=\"submit\" value=\"Submit\">\n";
      $output_string .= "</form>";
    }
  }
}

if (($event != "") && (count($mass_start_courses) > 0)) {
  $readable_course_names = array_map(function ($name) { return (ltrim($name, "0..9-")); }, $mass_start_courses);
  $output_string .= "<p>\nConfirm mass start for courses:<p><ul><li>" . join("</li><li>", $readable_course_names) . "</li></ul>\n";
  $output_string .= "<form action=\"mass_start_courses.php\">\n<input type=\"submit\" name=\"submit\" value=\"Confirm and start\">\n";
  $output_string .= "<input type=\"hidden\" name=\"courses_to_start\" value=\"" . implode(",", $mass_start_courses) . "\">\n";
  $output_string .= "</form>\n";
}


echo $output_string;

echo get_web_page_footer();
?>
