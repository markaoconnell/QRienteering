<?php
require 'common_routines.php';

// Get the submitted info
// echo "<p>\n";
$competitor_name = $_COOKIE["competitor_name"];
$course = $_COOKIE["course"];
$competitor_id = $_COOKIE["competitor_id"];
$next_control = $_COOKIE["next_control"];
$event = $_COOKIE["event"];

if (($event == "") || ($competitor_id == "")) {
  echo "<h1>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?";
  echo "<br><h1>This is a BYOM (Bring Your Own Map) Orienteering control.  For more information on orienteering, \n";
  echo "type \"orienteering new england\" into Google to learn about the sport and to find events in your area.\n";
  echo "If this is hanging in the woods, please leave it alone so as not to ruin an existing orienteering course that\n";
  echo "others may be currently enjoying.";
  exit(1);
}

$competitor_path = "./" . $event . "/Competitors/" . $competitor_id;
// $control_list = file("./${event}/Courses/${course}");

if (file_exists("${competitor_path}/start")) {
  $error_string = "Course already started";
}
else {
  file_put_contents("${competitor_path}/start", strval(time()));
}

// See how many controls have been completed
// $controls_done = scandir("./${competitor_path}");
// $controls_done = array_diff($controls_done, array(".", "..", "course", "name", "next", "start", "finish")); // Remove the annoying . and .. entries
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
?>
</head>
<body>
<br>

<?php
if ($error_string == "") {
  echo "<p>" . ltrim($course, "0..9-") . " course started for ${competitor_name}.\n";
}
else {
  echo "<p>ERROR: ${error_string}\nCourse not started.";
}
?>

</body>
</html>
