<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/results_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = isset($_GET["event"]) ? $_GET["event"]: "";
$key = isset($_GET["key"]) ? $_GET["key"]: "";

$color_mapping_hash = array("white" => "white", "yellow" => "yellow", "orange" => "orange",
	"tan" => "tan", "brown" => "sienna", "brownx" => "sienna", "browny" => "sienna", "brownz" => "sienna",
	"brown-x" => "sienna", "brown-y" => "sienna", "brown-z" => "sienna",
	"green" => "lightgreen", "greenx" => "lightgreen", "greeny" => "lightgreen", "greenz" => "lightgreen",
	"green-x" => "lightgreen", "green-y" => "lightgreen", "green-z" => "lightgreen",
       	"red" => "tomato", "blue" => "lightskyblue");

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
$initialize_course_values = isset($_GET["initialize_course_values"]);
$prior_page_end = isset($_GET["prior_page_cookie"]) ? $_GET["prior_page_cookie"] : "";

$output = "<p>Results for " . file_get_contents(get_event_path($event, $key) . "/description") . "\n";

$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));
$course_list = array_values($course_list);  # Make the keys 0 based
  

if ($initial_run) {
  $output .= "<form action=\"./result_cycler.php\">\n";
  $output .= "<input type=hidden name=key value=\"{$key}\">\n";
  $output .= "<input type=hidden name=event value=\"{$event}\">\n";
  $output .= "<input type=hidden name=initialize_course_values value=\"yes\">\n";
  $output .= "<p>Number of columns of output: <input type=text name=columns value=2>\n";
  $output .= "<p>Number of lines of output: <input type=text name=lines_to_show value=15>\n";
  $output .= "<p>Seconds of delay between refreshes: <input type=text name=time_delay value=30>\n";
  $output .= "<p><p>Show all courses: <input type=checkbox name=show_all_courses value=\"yes\" checked>\n"; 
  $output .= "<p><input type=submit value=\"Show results\">\n";
  $output .= "<p><p>Show specific courses (only used if Show all courses deselected):\n";
  foreach ($course_list as $this_course) {
    $readable_course_name = ltrim($this_course, "0..9-");
    $output .= "<p><input type=checkbox name=\"show_{$this_course}\" value=\"yes\"> {$readable_course_name}\n";
  }
  $output .= "<p></form>\n";
}
else {
  if ($initialize_course_values) {
    if (isset($_GET["show_all_courses"]) && ($_GET["show_all_courses"] == "yes")) {
      $courses_to_show = $course_list;
    }
    else {
      $courses_to_show = array();
      foreach ($course_list as $this_course) {
        if (isset($_GET["show_{$this_course}"]) && ($_GET["show_{$this_course}"] == "yes")) {
          $courses_to_show[] = $this_course;
	}
      }
    }
  }
  else {
    $courses_to_show = explode(",", $_GET["courses_to_show"]);
  }

  if (count($courses_to_show) == 0) {
    error_and_exit("<p>No courses to show");
  }

  if ($prior_page_end == "") {
    $prior_page_end = "{$courses_to_show[0]},0";
  }
  
  $results = get_column_data($prior_page_end);
  $prior_page_end = $results[0];
  $column_data = $results[1];
  
  // results[2] is either true or false.  True if we have reached the end of the results
  // and should just stop, false if there are more results to show
  $results_are_complete = $results[2];
  for ($i = 1; $i < $columns; $i++) {
    if ($results_are_complete) {
      break;
    }
    $results = get_column_data($prior_page_end);
    $prior_page_end = $results[0];
    $column_data = array_map(function ($e1, $e2) { return ("{$e1}<td width=30></td><td width=30></td>{$e2}"); }, $column_data, $results[1]);
    $results_are_complete = $results[2];
  }
  
  $displayable_data = array_map(function ($elt) { return ("<tr height=20>{$elt}</tr>"); }, $column_data);
  $output .= "\n<table>\n" . implode("\n", $displayable_data) . "\n</table>\n";
  $string_courses_to_show = implode(",", $courses_to_show);
  set_redirect("\n<meta http-equiv=\"refresh\" content=\"{$time_delay}; url=./result_cycler.php?key={$key}&event={$event}&" .
                                                                                   "lines_to_show={$lines_to_show}&columns={$columns}&time_delay={$time_delay}&" .
										   "prior_page_cookie={$prior_page_end}&courses_to_show={$string_courses_to_show}\"/>");

  $output .= "\n<p>Refreshing every {$time_delay} seconds or <a href=\"./result_cycler.php?key={$key}&event={$event}&" .
                       "lines_to_show={$lines_to_show}&columns={$columns}&time_delay={$time_delay}&" .
                       "prior_page_cookie={$prior_page_end}&courses_to_show={$string_courses_to_show}\">show next now</a>\n";
}

echo get_web_page_header(true, true, false);

echo $output;


echo get_web_page_footer();

function get_column_data($prior_page_end) {
  global $course_list, $lines_to_show, $event, $key, $courses_path, $courses_to_show;
  global $TYPE_FIELD, $SCORE_O_COURSE, $MAX_SCORE_FIELD;
  global $color_mapping_hash;

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
  if (isset($color_mapping_hash[strtolower($readable_course_name)])) {
    $bgcolor = "bgcolor = " . $color_mapping_hash[strtolower($readable_course_name)];
  }
  else {
    $bgcolor = "";
  }
  $current_output = array();
  $current_output[] = "<td></td><td {$bgcolor}><strong><u>{$readable_course_name}</u></strong></td><td></td><td width=20></td>\n";
  $current_output[] = "<td><strong>Pl</strong></td><td><strong>Name</strong></td><td><strong>Time</strong></td><td><strong>{$label_points_column}</strong></td>\n";
  $current_lines = 2;

  $results_array = get_results_as_array($event, $key, $course_to_show, $score_course, $max_score);

  while ($current_lines < $lines_to_show) {

    if ($next_place_to_show >= count($results_array)) {
      $pick_next_course = false;
      $found_course = false;
      foreach ($courses_to_show as $one_course) {
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
	if (($current_lines + 4) >= $lines_to_show) {   # Only start showing the next course if we can show at least one entry
          $current_output = array_pad($current_output, $lines_to_show, "<td></td><td></td><td></td><td></td>\n");
          return(array("{$course_to_show},0", $current_output, false));  # Start with the next course
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
        if (isset($color_mapping_hash[strtolower($readable_course_name)])) {
          $bgcolor = "bgcolor = " . $color_mapping_hash[strtolower($readable_course_name)];
        }
        else {
          $bgcolor = "";
        }
        $current_output[] = "<td></td><td></td><td></td><td></td>\n";
        $current_output[] = "<td></td><td {$bgcolor}><strong><u>{$readable_course_name}</u></strong></td><td></td><td width=20></td>\n";
        $current_output[] = "<td><strong>Pl</strong></td><td><strong>Name</strong></td><td><strong>Time</strong></td><td><strong>{$label_points_column}</strong></td>\n";
        $current_lines += 3;
	$results_array = get_results_as_array($event, $key, $course_to_show, $score_course, $max_score);
	$next_place_to_show = 0;
	continue;
      }
      else {
        $current_output = array_pad($current_output, $lines_to_show, "<td></td><td></td><td></td><td></td>\n");
        return(array("{$courses_to_show[0]},0", $current_output, true));  # Start over next time
      }
    }

    $this_entry = $results_array[$next_place_to_show];
    if ($this_entry["award_eligibility"] == "n") {
      $display_name = "<span style=\"color: red;\">(x)</span> {$this_entry["competitor_name"]}";
    }
    else {
      $display_name = $this_entry["competitor_name"];
    }
    $output = "<td>" . ($next_place_to_show + 1) . "</td><td>" . $display_name . "</td>";
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


  // If we have just finished a course at the end of the column,
  // move on to the next one
  if ($next_place_to_show >= count($results_array)) {
    $pick_next_course = false;
    $found_course = false;
    foreach ($courses_to_show as $one_course) {
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
      return(array("{$course_to_show},0", $current_output, false));  # Start with the next course
    }
    else {
      return(array("{$courses_to_show[0]},0", $current_output, true));  # Start over next time
    }
  }
  return(array("{$course_to_show},{$next_place_to_show}", $current_output, false));  # Marker of where to start next
}

?>
