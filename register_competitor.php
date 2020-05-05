<?php
require 'common_routines.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$competitor_name = $_GET["competitor_name"];
$course = $_GET["course"];


$courses_array = scandir('./' . $_GET["event"] . '/Courses');
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
// print_r($courses_array);
// echo "<p>\n";

$body_string = "";

// Validate the info
$error = false;
if (!in_array($course, $courses_array)) {
  $body_string .= "<p>ERROR: Course must be specified.\n";
  $error = true;
}

if ($competitor_name === "") {
  $body_string .= "<p>ERROR: Competitor name must be specified.\n";
  $error = true;
}

// Input information is all valid, save the competitor information
if (!$error) {
  // Generate the competitor_id and make sure it is truly unique
  $tries = 0;
  while ($tries < 5) {
    $competitor_id = uniqid();
    $competitor_path = "./" . $_GET["event"] . "/Competitors/" . $competitor_id;
    mkdir ($competitor_path, 0777);
    $competitor_file = fopen($competitor_path . "/name", "x");
    if ($competitor_file !== false) {
      break;
    }
    $tries++;
  }

  if ($tries === 5) {
    $body_string .= "ERROR Cannot register " . $competitor_name . " with id: " . $competitor_id . "\n";
  }
  else {
    $body_string .= "<p>Registration complete: " . $competitor_name . " on " . ltrim($course, "0..9-");

    // Save the information about the competitor
    fwrite($competitor_file, $competitor_name);
    fclose($competitor_file);
    file_put_contents($competitor_path . "/course", $course);
    mkdir("./{$competitor_path}/controls_found");
    
    // Set the cookies with the name, course, next control
    $timeout_value = time() + 3600 * 6;  // 6 hour timeout, should be fine for most any course
    setcookie("competitor_id", $competitor_id, $timeout_value);
    setcookie("course", $course, $timeout_value);
    setcookie("event", $_GET["event"], $timeout_value);
  }
}

echo get_web_page_header(true, false, false);

echo $body_string;

echo get_web_page_footer();
?>
