<?php
require 'common_routines.php';

$event = $_COOKIE["event"];
$control_id = $_GET["control"];
$competitor_id = $_COOKIE["competitor_id"];

if (($event == "") || ($competitor_id == "")) {
  echo "<h1>ERROR: Unknown event \"{$event}\" or competitor \"{$competitor_id}\", probably not registered for a course?";
  echo "<br><h1>This is a BYOM (Bring Your Own Map) Orienteering control.  For more information on orienteering, \n";
  echo "type \"orienteering new england\" into Google to learn about the sport and to find events in your area.\n";
  echo "If this is hanging in the woods, please leave it alone so as not to ruin an existing orienteering course that\n";
  echo "others may be currently enjoying.";
  exit(1);
}

$error_string = "";

// Do an internal redirect, encoding the competitor_id and control - this is to prevent later
// replays when this device is potentially redoing the course
// A redo of the course will generate a new competitor_id, which will then be detected
if (!file_exists("./${event}/no_redirects") && ($_GET["mumble"] == "")) {
  $current_time = time();
  $redirect_encoded_info = base64_encode("{$control_id},{$competitor_id},{$current_time}");
  echo "<html><head><meta http-equiv=\"refresh\" content=\"0; URL=./reach_control.php?mumble=${redirect_encoded_info}\" /></head></html>";
  return;
}

if ($_GET["mumble"] != "") {
  $pieces = explode(",", base64_decode($_GET["mumble"]));
  $control_id = $pieces[0];
  $encoded_competitor_id = $pieces[1];
  $time_of_page_access = $pieces[2];

  if ($encoded_competitor_id != $competitor_id) {
    $error_string .= "<p>ERROR: Competitor mismatch, possible replay of earlier scan?\n";
    $control_id = "ERROR";
  }
  else if ((time() - $time_of_page_access) > 30) {
    // 30 second buffer for page reloads
    $error_string .= "<p>ERROR: Time lag of > 30 seconds since scan of control {$control_id} - incorrect page reload?\n";
    $control_id = "ERROR";
  }
}

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
// Get the submitted info
// echo "<p>\n";
$competitor_name = $_COOKIE["competitor_name"];
$course = $_COOKIE["course"];
$next_control = $_COOKIE["next_control"];




$competitor_path = "./${event}/Competitors/${competitor_id}";
$control_list = file("./${event}/Courses/${course}/controls.txt");
$control_list = array_map('trim', $control_list);
//echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);


if (!file_exists("${competitor_path}/start")) {
  $error_string .= "<p>Course not started\n";
}

// See how many controls have been completed
$controls_done = scandir("./${competitor_path}");
$controls_done = array_diff($controls_done, array(".", "..", "course", "name", "next", "start", "finish", "extra", "dnf")); // Remove the annoying . and .. entries
$start_time = file_get_contents("./{$competitor_path}/start");
$time_on_course = time() - $start_time;
// echo "<br>Controls done on the ${course} course.<br>\n";
// print_r($controls_done);

// Are we at the right control?
$number_controls_found = count($controls_done);
$prior_control_repeat = false;
// echo "<br>At control ${control_id}, expecting to be at " . $control_list[$number_controls_found] . "--\n";
if ($control_id != $control_list[$number_controls_found]) {
  // echo "<p>This looks like the wrong control\n";
  // Not the right control, but if we're still at the prior control, the person probably just scanned the control twice - that's ok
  if ($number_controls_found == 0) {
    $prior_control = "NoPriorControl";
  }
  else {
    $prior_control = $control_list[$number_controls_found - 1];
  }

  if ($control_id != $prior_control) {
    if ($control_id != "ERROR") {
      $error_string .= "<p>Found wrong control: {$control_id}, course " . ltrim($course, "0..9-") . ", control #" . ($number_controls_found + 1) .
                            ", expected control " . $control_list[$number_controls_found] . "\n";
      $extra_control_string = "{$control_id}," . strval(time()) . "\n";
      file_put_contents($competitor_path . "/extra", $extra_control_string, FILE_APPEND);
      // echo "<p>This looks like it also wasn't the prior control\n";
    }
  }
  else {
    // echo"<p>This looks like a rescan of the prior control.\n";
    $remaining_controls = count($control_list) - $number_controls_found;
    if ($remaining_controls <= 0) {
      $next_control = "Finish";
    }
    else {
      $next_control = $control_list[$number_controls_found];
    }
  }
}
else {
  file_put_contents($competitor_path . "/" . $number_controls_found, strval(time()));
  $remaining_controls = count($control_list) - $number_controls_found - 1;
  if ($remaining_controls <= 0) {
    $next_control = "Finish";
  }
  else {
    $next_control = $control_list[$number_controls_found + 1];
  }
  // echo "<p>Saved to the file ${competitor_path}/${number_controls_found}.\n";
}
?>



<?php
if ($error_string == "") {
  echo "<p>Correct!  Reached {$control_id}, control #" . ($number_controls_found + 1) . " on " . ltrim($course, "0..9-") . "\n";
  echo "<p>{$remaining_controls} more to find, next is {$next_control}.\n";
}
else {
  echo "<p>ERROR: {$error_string}\n";
}
echo "<br><p>Time on course is: " . formatted_time($time_on_course) . "\n";
?>

</body>
</html>
