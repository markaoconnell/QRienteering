<?php
require 'common_routines.php';

// Get the submitted info
// echo "<p>\n";
$course = $_GET["course"];
$time_and_competitor = $_GET["entry"];
$event = $_GET["event"];

$result_pieces = explode(",", $time_and_competitor);
$competitor_id = $result_pieces[1];


$competitor_path = "./" . $event . "/Competitors/" . $competitor_id;
$competitor_name = file_get_contents("./{$event}/Competitors/{$competitor_id}/name");

$control_list = file("./{$event}/Courses/{$course}/controls.txt");
$control_list = array_map('trim', $control_list);
//echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);
$error_string = "";

if (!file_exists("{$competitor_path}/start")) {
  $error_string = "<p>Course not started\n";
}

$start_time = file_get_contents("{$competitor_path}/start");

// See how many controls have been completed
$controls_done = scandir("./{$competitor_path}");
$controls_done = array_diff($controls_done, array(".", "..", "course", "name", "next", "start", "finish", "extra", "dnf")); // Remove the annoying . and .. entries
$number_controls_found = count($controls_done);

$split_times = array();
$prior_control_time = $start_time;
for ($i = 0; $i < $number_controls_found; $i++){
  $time_at_control[$i] = file_get_contents("{$competitor_path}/{$i}");
  $split_times[$i] = $time_at_control[$i] - $prior_control_time;
  $prior_control_time = $time_at_control[$i];
}
$time_at_control[$i] = file_get_contents("{$competitor_path}/finish");
$split_times[$i] = $time_at_control[$i] - $prior_control_time;

$extra_controls_string="";
if (file_exists("{$competitor_path}/extra")) {
  $extra_controls = explode("\n", file_get_contents("{$competitor_path}/extra"));
  $extra_controls_string = "<tr></tr><tr><td colspan=4>Wrong controls punched (not on course)</td></tr>\n";
  foreach ($extra_controls as $extra_one) {
    if ($extra_one != "") {
      $extra_control_info = explode(",", $extra_one);
      $extra_controls_string .= "<tr><td></td><td>{$extra_control_info[0]}</td><td></td><td>" . strftime("%T", $extra_control_info[1]) . "</td>\n";
    }
  }
}

$table_string = "";
$table_string .= "<p class=\"title\">Splits for ${competitor_name} on " . ltrim($course, "0..9-") . "\n";
$table_string .= "<table border=1><tr><th>Control Num</th><th>Control Id</th><th>Split</th><th>Time of Day</th></tr>\n";
$table_string .= "<tr><td>Start</td><td></td><td></td><td>" . strftime("%T (%a - %d)", $start_time) . "</td></tr>\n";
for ($i = 0; $i < $number_controls_found; $i++){
  $table_string .= "<tr><td>" . ($i + 1) . "</td><td>" . $control_list[$i] . "</td><td>" . formatted_time($split_times[$i]) . "</td>" .
                                              "<td>" . strftime("%T", $time_at_control[$i]) . "</td></tr>\n";
}
$table_string .= "<tr><td>Finish</td><td></td><td>" . formatted_time($split_times[$i]) . "</td>" .
                                         "<td>" . strftime("%T (%a - %d)", $time_at_control[$i]) . "</td></tr>\n{$extra_controls_string}\n</table>\n";
?>


<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>Orienteering Event Management</title>
  <meta content="Mark O'Connell" name="author">
</head>
<?php
echo get_paragraph_style_header();
?>
<?php
echo get_table_style_header();
?>
<body>
<br>


<?php
if ($error_string != "") {
  echo "<p>ERROR: ${error_string}\n";
}

echo $table_string;
echo "<p>Total Time: " . formatted_time($result_pieces[0]) . "\n";
if (file_exists("${competitor_path}/dnf")) {
  echo "<p>DNF\n";
}

?>

</body>
</html>
