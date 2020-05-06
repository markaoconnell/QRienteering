<?php
require 'common_routines.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$course = $_GET["course"];
$time_and_competitor = $_GET["entry"];
$event = $_GET["event"];

$result_pieces = explode(",", $time_and_competitor);
$competitor_id = $result_pieces[2];


$competitor_path = "./" . $event . "/Competitors/" . $competitor_id;
$competitor_name = file_get_contents("./{$event}/Competitors/{$competitor_id}/name");
$controls_found_path = "{$competitor_path}/controls_found";

$control_list = read_controls("./{$event}/Courses/{$course}/controls.txt");
//echo "Controls on the ${course} course.<br>\n";
// print_r($control_list);
$error_string = "";

if (!file_exists("{$controls_found_path}/start")) {
  $error_string = "<p>Course not started\n";
}

$start_time = file_get_contents("{$controls_found_path}/start");

// See how many controls have been completed
$controls_done = scandir("./{$controls_found_path}");
$controls_done = array_diff($controls_done, array(".", "..", "start", "finish")); // Remove the annoying . and .. entries
$number_controls_found = count($controls_done);

$split_times = array();
$cumulative_time = array();
$prior_control_time = $start_time;
foreach ($controls_done as $control_entry) {
  $control_info_array = explode(",", $control_entry);  // format is <time>,<control_id>
  $time_at_control[$i] = $control_info_array[0];
  $split_times[$i] = $time_at_control[$i] - $prior_control_time;
  $cumulative_time[$i] = $time_at_control[$i] - $start_time;
  $prior_control_time = $time_at_control[$i];
}
$time_at_control[$i] = file_get_contents("{$controls_found_path}/finish");
$split_times[$i] = $time_at_control[$i] - $prior_control_time;
$cumulative_time[$i] = $time_at_control[$i] - $start_time;

$extra_controls_string="";
if (file_exists("{$competitor_path}/extra")) {
  $extra_controls = explode("\n", file_get_contents("{$competitor_path}/extra"));
  $extra_controls_string = "<tr></tr><tr><td colspan=4>Wrong controls punched (not on course)</td></tr>\n";
  foreach ($extra_controls as $extra_one) {
    if ($extra_one != "") {
      $extra_control_info = explode(",", $extra_one);  // Format of each entry is <time>,<control_id>
      $extra_controls_string .= "<tr><td></td><td>{$extra_control_info[1]}</td><td></td><td></td><td>" . strftime("%T", $extra_control_info[0]) . "</td>\n";
    }
  }
}

$table_string = "";
$table_string .= "<p class=\"title\">Splits for ${competitor_name} on " . ltrim($course, "0..9-") . "\n";
$table_string .= "<table border=1><tr><th>Control Num</th><th>Control Id</th><th>Split Time</th><th>Cumulative Time</th><th>Time of Day</th></tr>\n";
$table_string .= "<tr><td>Start</td><td></td><td></td><td></td><td>" . strftime("%T (%a - %d)", $start_time) . "</td></tr>\n";
for ($i = 0; $i < $number_controls_found; $i++){
  $table_string .= "<tr><td>" . ($i + 1) . "</td><td>" . $control_list[$i][0] . "</td><td>" . formatted_time($split_times[$i]) . "</td>" .
                                           "<td>" . formatted_time($cumulative_time[$i]) . "</td><td>" . strftime("%T", $time_at_control[$i]) . "</td></tr>\n";
}
$table_string .= "<tr><td>Finish</td><td></td><td>" . formatted_time($split_times[$i]) . "</td>" .
                                         "<td>" . formatted_time($cumulative_time[$i]) . "</td>" .
                                         "<td>" . strftime("%T (%a - %d)", $time_at_control[$i]) . "</td></tr>\n{$extra_controls_string}\n</table>\n";

echo get_web_page_header(true, true, false);

if ($error_string != "") {
  echo "<p>ERROR: ${error_string}\n";
}

echo $table_string;
echo "<p>Total Time: " . formatted_time($result_pieces[1]) . "\n";
if (file_exists("${competitor_path}/dnf")) {
  echo "<p>DNF\n";
}

echo get_web_page_footer();
?>
