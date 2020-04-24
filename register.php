<?php
require 'common_routines.php';
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>Orienteering Event Management</title>
  <meta content="Mark O'Connell" name="author">
<?php
echo get_paragraph_style_header();
echo get_input_form_style_header();
?>
</head>
<body>
<br>

<?php
// Get some phpinformation, just in case
// Verify that php is running properly
// echo 'Current PHP version: ' . phpversion();
// phpinfo();

function is_event($filename) {
  return (is_dir($filename) && (substr($filename, strlen($filename) - 5) == "Event"));
}

function name_to_link($pathname) {
  $final_element = basename($pathname);
  return ("<li><a href=./register.php?event=${final_element}>${final_element}</a>\n");
}

echo "<p>\n";

$event = $_GET["event"];
//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
if (strcmp($event, "") == 0) {
  $event_list = scandir("./");
  //print_r($event_list);
  $event_list = array_filter($event_list, is_event);
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
    echo "<p>No available events.\n";
    return;
  }
}

$courses_array = scandir("./${event}/Courses");
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
// print_r($courses_array);
echo "<p>\n";

echo "<p>Registration for orienteering event: ${event}\n<br>";
echo "<form action=\"./register_competitor.php\">\n";

echo "<br><p>What is your name?<br>\n";
echo "<input type=\"text\" name=\"competitor_name\"><br>\n";
echo "<input type=\"hidden\" name=\"event\" value=\"${event}\">\n";

echo "<br><p>Select a course:<br>\n";
foreach ($courses_array as $course_name) {
  echo "<input type=\"radio\" name=\"course\" value=\"" . $course_name . "\">" . ltrim($course_name, "0..9-") . " <br>\n";
}

echo "<input type=\"submit\" value=\"Submit\">\n";
echo "</form>";

echo "<p><a href=\"./view_results?event=${event}\">View results</a>";
echo "<p><a href=\"./on_course?event=${event}\">Out on course</a><p>";


?>


</body>
</html>
