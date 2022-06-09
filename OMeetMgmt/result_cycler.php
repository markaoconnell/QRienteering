<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = isset($_GET["event"]) ? $_GET["event"]: "";
$key = isset($_GET["key"]) ? $_GET["key"]: "";

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

$courses_path = get_courses_path($event, $key);
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

set_timezone($key);

$lines_to_show = isset($_GET["lines_to_show"]) ? $_GET["lines_to_show"] : 15;
$columns = isset($_GET["columns"]) ? $_GET["columns"] : 2;
$time_delay = isset($_GET["time_delay"]) ? $_GET["time_delay"] : 30;  # Delay in seconds before moving to next results
$initial_run = isset($_GET["initial_run"]);
$prior_page_end = isset($_GET["prior_page_cookie"]) ? $_GET["prior_page_cookie"] : "";

$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));
$course_list = array_values($course_list);  # Make the keys 0 based

if ($prior_page_end == "") {
  $prior_page_end = "{$course_list[0]},0";
}

$results = get_column_data($prior_page_end);
$prior_page_end = $results[0];
$column_data = $results[1];

if ($columns > 1) {
  $results = get_column_data($prior_page_end);
  $prior_page_end = $results[0];
  $column_data = array_map(function ($e1, $e2) { return ("{$e1}<td width=30></td><td width=30></td>{$e2}"); }, $column_data, $results[1]);
}

$displayable_data = array_map(function ($elt) { return ("<tr>{$elt}</tr>"); }, $column_data);

echo get_web_page_header(true, true, false);

echo "<table>\n";
echo implode("\n", $displayable_data);
echo "</table>\n";

echo get_web_page_footer();

function get_column_data($prior_page_end) {
  global $course_list, $lines_to_show, $event, $key, $courses_path;
  global $TYPE_FIELD, $SCORE_O_COURSE, $MAX_SCORE_FIELD;

  $last_marker_pieces = explode(",", $prior_page_end);
  $course_to_show = $last_marker_pieces[0];
  $next_place_to_show = $last_marker_pieces[1];
  $current_lines = 0;
  $current_output;

  $readable_course_name = ltrim($course_to_show, "0..9-");
  $course_properties = get_course_properties("{$courses_path}/{$course_to_show}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  $label_points_column = "";
  if ($score_course) {
    $max_score = $course_properties[$MAX_SCORE_FIELD];
    $label_points_column = "Pts";
  }

  # Include the header
  $current_output = array();
  $current_output[] = "<td></td><td>{$readable_course_name}</td><td></td><td></td>\n";
  $current_output[] = "<td>Pl</td><td>Name</td><td>Time</td><td>{$label_points_column}</td>\n";
  $current_lines = 2;

  $results_array = get_results_as_array($event, $key, $course_to_show, $score_course, $max_score);

  while ($current_lines < $lines_to_show) {

    if ($next_place_to_show >= count($results_array)) {
      $pick_next_course = false;
      $found_course = false;
      foreach ($course_list as $one_course) {
        if ($pick_next_course) {   # Need to decide what to do about removed courses here
          $found_course = true;
	  $course_to_show = $one_course;
	  break;
	}

        if ($one_course == $course_to_show) {
          $pick_next_course = true;
	}
      }

      if ($found_course) {
	if (($current_lines + 3) >= $lines_to_show) {   # Only start showing the next course if we can show at least one entry
          return(array("{$course_to_show},0", $current_output));  # Start with the next course
	}

        $readable_course_name = ltrim($course_to_show, "0..9-");
        $course_properties = get_course_properties("{$courses_path}/{$course_to_show}");
        $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
        $max_score = 0;
        $label_points_column = "";
        if ($score_course) {
          $max_score = $course_properties[$MAX_SCORE_FIELD];
          $label_points_column = "Pts";
        }
        $current_output[] = "<td></td><td>{$readable_course_name}</td><td></td><td></td>\n";
        $current_output[] = "<td>Pl</td><td>Name</td><td>Time</td><td>{$label_points_column}</td>\n";
        $current_lines += 2;
	$results_array = get_results_as_array($event, $key, $course_to_show, $score_course, $max_score);
	$next_place_to_show = 0;
	continue;
      }
      else {
        return(array("{$course_list[0]},0", $current_output));  # Start over next time
      }
    }

    $this_entry = $results_array[$next_place_to_show];
    $output = "<td>" . ($next_place_to_show + 1) . "</td><td>" . $this_entry["competitor_name"] . "</td>";
    if ($this_entry["dnf"]) {
      $output .= "<td>DNF</td>";
    }
    else {
      $output .= "<td>" . trim($this_entry["time"]) . "</td>";
    }

    if ($score_course) {
      $output .= "<td>" . $this_entry["scoreo_points"] . "</td>";
    }
    else {
      $output .= "<td></td>";
    }
    $current_output[] = $output;

    $next_place_to_show++;
    $current_lines++;
  }


  return(array("{$course_to_show},{$next_place_to_show}", $current_output));  # Marker of where to start next
}

?>
