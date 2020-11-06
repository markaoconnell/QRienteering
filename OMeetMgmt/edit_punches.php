<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$competitor = $_GET["competitor"];

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, no results can be shown.\n");
}

$competitor_path = get_competitor_path($competitor, $event, $key);
$courses_path = get_courses_path($event, $key);
if (!file_exists($courses_path) || !is_dir($competitor_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

$splits_array = get_splits_as_array($competitor, $event, $key, true);

$competitor_name = file_get_contents("{$competitor_path}/name");

$course = file_get_contents("{$competitor_path}/course");
$courses_path = get_courses_path($event, $key);
$control_list = read_controls("{$courses_path}/{$course}/controls.txt");

$course_properties = get_course_properties("{$courses_path}/{$course}");
$number_controls = count($control_list);
$score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
$max_score = 0;
if ($score_course) {
  $max_score = $course_properties[$MAX_SCORE_FIELD];
}

  
$controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                      array_map(function ($element) { return $element[1]; }, $control_list));
$course_properties = get_course_properties("{$courses_path}/{$course}");
$score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
$error_string = "";
  
$output_string = "<p>Punches for {$competitor_name} on " . ltrim($course, "0..9-") . "\n";
$output_string .= "<p><table><tr><th>#</th><th>Control</th><th>Actual time (s)</th><th>Remove</th></tr>\n";
$controls_found = $splits_array["controls"];

$control_num_on_course = 0;
foreach ($controls_found as $this_control) {
  $found_good_control = false;

  while (1) {
    // Are we at the end of the known controls?
    if ($control_num_on_course >= count($control_list)) {
      break;
    }

    if ($control_list[$control_num_on_course][0] == $this_control["control_id"]) {
      $found_good_control = true;
      break;
    }

    // is the punched control on the course at all?
    if (isset($controls_points_hash[$this_control["control_id"]])) {
      $output_string .= "<tr><td>" . ($control_num_on_course + 1) . "</td><td>" . $control_list[$control_num_on_course][0] . "</td>";
      $output_string .=     "<td>-</td><td>-</td></tr>\n";

      $control_num_on_course++;
      continue;
    }
    else {
      break;
    }
  }

  if ($found_good_control) {
    $output_string .= "<tr><td>" . ($control_num_on_course + 1) . "</td><td>" . $this_control["control_id"] . "</td>";
    $output_string .=     "<td>" . $this_control["cumulative_time"] . "</td><td>-</td></tr>\n";

    $control_num_on_course++;
  }
  else {
    $output_string .= "<tr><td>-</td><td>" . $this_control["control_id"] . "</td>";
    $output_string .=     "<td>" . $this_control["cumulative_time"] . "</td><td>-</td></tr>\n";
  }
}

// Handle unpunched controls at the end
for ( ; $control_num_on_course < count($control_list); $control_num_on_course++) {
  $output_string .= "<tr><td>" . ($control_num_on_course + 1) . "</td><td>" . $control_list[$control_num_on_course][0] . "</td>";
  $output_string .=     "<td>-</td><td>-</td></tr>\n";
}

$output_string .= "</table>\n";
  
$finish_time = $splits_array["finish"] - $splits_array["start"];
$output_string .= "<p>Finish at $finish_time (seconds).\n";

echo get_web_page_header(true, true, false);

echo $output_string;

echo get_web_page_footer();
?>
