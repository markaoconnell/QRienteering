<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetRegistration/nre_class_handling.php';
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
$initialize_list_to_show = isset($_GET["initialize_list_to_show"]);
$show_by = isset($_GET["show_by"]) ? $_GET["show_by"] : "course"; // Assuming showing by course if not set
$show_by_course = ($show_by == "course");
$show_by_class = ($show_by == "class");
if (!$show_by_course && !$show_by_class) {
  $show_by_course = True;
}
if (!event_is_using_nre_classes($event, $key)) {
  $show_by_course = True;
}
if ($show_by_class) {
  $classification_info = get_nre_classes_info($event, $key);
  if (count($classification_info) == 0) {   // If there are no classes, then go back to showing by course
    $show_by_course = True;
    $show_by_class = False;
  }
}
else {
  $classification_info = array();
}
$prior_page_end = isset($_GET["prior_page_cookie"]) ? $_GET["prior_page_cookie"] : "";

$output = "<p>Results for " . file_get_contents(get_event_path($event, $key) . "/description") . "\n";

$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));
$course_list = array_values($course_list);  # Make the keys 0 based
  

if ($initial_run) {
  $output .= "<form action=\"./result_cycler.php\">\n";
  $output .= "<input type=hidden name=key value=\"{$key}\">\n";
  $output .= "<input type=hidden name=event value=\"{$event}\">\n";
  $output .= "<input type=hidden name=initialize_list_to_show value=\"yes\">\n";
  $output .= "<p>Number of columns of output: <input type=text name=columns value=2>\n";
  $output .= "<p>Number of lines of output: <input type=text name=lines_to_show value=15>\n";
  $output .= "<p>Seconds of delay between refreshes: <input type=text name=time_delay value=30>\n";
  if (event_is_using_nre_classes($event, $key)) {
    $output .= "<p>Show by <input type=radio name=show_by value=course " . ($show_by_course ? "checked" : "") . ">" .
		  " course or <input type=radio name=show_by value=class " . ($show_by_class ? "checked" : "") . "> class\n";
  }
  else {
    $output .= "<input type=hidden name=show_by value=course>\n";
  }
  $output .= "<p><p>Show all courses/classes: <input type=checkbox name=show_all value=\"yes\" checked>\n"; 
  $output .= "<p><input type=submit value=\"Show results\">\n";
  if (event_is_using_nre_classes($event, $key)) {
    $output .= "<p><p>Show specific courses (only used if Show all deselected and courses display chosen):\n";
  }
  else {
    $output .= "<p><p>Show specific courses (only used if Show all deselected):\n";
  }
  foreach ($course_list as $this_course) {
    $readable_course_name = ltrim($this_course, "0..9-");
    $output .= "<p><input type=checkbox name=\"show_{$this_course}\" value=\"yes\"> {$readable_course_name}\n";
  }
  if (event_is_using_nre_classes($event, $key)) {
    $output .= "<p><p>Show specific classes (only used if Show all deselected and classes display chosen):\n";
    $class_list = get_nre_class_display_order($event, $key);
    foreach ($class_list as $this_class) {
      $output .= "<p><input type=checkbox name=\"" . "show_{$this_class}" . "\" value=\"yes\"> {$this_class}\n";
    }
  }
  $output .= "<p></form>\n";
}
else {
  $things_to_show = array();
  if ($show_by_course) {
    if ($initialize_list_to_show) {
      if (isset($_GET["show_all"]) && ($_GET["show_all"] == "yes")) {
        $things_to_show = $course_list;
      }
      else {
        $courses_to_show = array();
        foreach ($course_list as $this_course) {
          if (isset($_GET["show_{$this_course}"]) && ($_GET["show_{$this_course}"] == "yes")) {
            $things_to_show[] = $this_course;
          }
        }
      }
    }
    else {
      $things_to_show = explode(",", $_GET["courses_to_show"]);
    }
  }
  elseif ($show_by_class) {
    if ($initialize_list_to_show) {
      $class_list = get_nre_class_display_order($event, $key);
      if (isset($_GET["show_all"]) && ($_GET["show_all"] == "yes")) {
        $things_to_show = $class_list;
      }
      else {
        foreach ($class_list as $this_class) {
	  $encoded_class = urlencode($this_class);
          if (isset($_GET["show_{$encoded_class}"]) && ($_GET["show_{$encoded_class}"] == "yes")) {
            $things_to_show[] = $this_class;
          }
        }
      }
    }
    else {
      $things_to_show = explode(",", $_GET["classes_to_show"]);
    }
  }

  if (count($things_to_show) == 0) {
    error_and_exit("<p>No courses or classes to show");
  }

  if ($prior_page_end == "") {
    $prior_page_end = "{$things_to_show[0]},0";
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
  if ($show_by_course) {
    $options_string = "show_by=course&courses_to_show=" . urlencode(implode(",", $things_to_show));
  }
  elseif ($show_by_class) {
    $options_string = "show_by=class&classes_to_show=" . urlencode(implode(",", $things_to_show));
  }
  set_redirect("\n<meta http-equiv=\"refresh\" content=\"{$time_delay}; url=./result_cycler.php?key={$key}&event={$event}&" .
                                                                                   "lines_to_show={$lines_to_show}&columns={$columns}&time_delay={$time_delay}&" .
										   "prior_page_cookie=" . urlencode($prior_page_end) . "&{$options_string}\"/>");

  $output .= "\n<p>Refreshing every {$time_delay} seconds or <a href=\"./result_cycler.php?key={$key}&event={$event}&" .
                       "lines_to_show={$lines_to_show}&columns={$columns}&time_delay={$time_delay}&" .
                       "prior_page_cookie=" . urlencode($prior_page_end) . "&{$options_string}\">show next now</a>\n";
}

echo get_web_page_header(true, true, false);


echo $output;


echo get_web_page_footer();

function get_column_data($prior_page_end) {
  global $course_list, $lines_to_show, $courses_path, $event, $key, $things_to_show, $show_by_course, $show_by_class, $classification_info;
  global $TYPE_FIELD, $SCORE_O_COURSE, $MAX_SCORE_FIELD;
  global $color_mapping_hash;

  $last_marker_pieces = explode(",", $prior_page_end);
  $thing_to_show = $last_marker_pieces[0];
  $next_place_to_show = $last_marker_pieces[1];
  $current_lines = 0;
  $current_output;

  $header_info = get_column_headers($thing_to_show);
  $score_course = $header_info[1];
  $max_score = $header_info[2];
  $current_output = array();
  $current_output = array_merge($current_output, $header_info[0]);
  $current_lines += count($header_info[0]);

  if ($show_by_course) {
    $results_array = get_course_results_as_array($event, $key, $thing_to_show, $score_course, $max_score);
  }
  elseif ($show_by_class) {
    $results_array = get_class_results_as_array($event, $key, $thing_to_show, $score_course, $max_score);
  }

  while ($current_lines < $lines_to_show) {

    if ($next_place_to_show >= count($results_array)) {
      $pick_next_item = false;
      $found_item = false;
      foreach ($things_to_show as $one_thing) {
        if ($pick_next_item) {   # Need to decide what to do about removed courses here
          $found_item = true;
	  $thing_to_show = $one_thing;
	  break;
	}

        if ($one_thing == $thing_to_show) {
          $pick_next_item = true;
	}
      }

      if ($found_item) {
	if (($current_lines + 4) >= $lines_to_show) {   # Only start showing the next course if we can show at least one entry
          $current_output = array_pad($current_output, $lines_to_show, "<td></td><td></td><td></td><td></td>\n");
          return(array("{$thing_to_show},0", $current_output, false));  # Start with the next course
	}

        $header_info = get_column_headers($thing_to_show);
	$score_course = $header_info[1];
	$max_score = $header_info[2];

        $current_output[] = "<td></td><td></td><td></td><td></td>\n";
        $current_output = array_merge($current_output, $header_info[0]);
	$current_lines += count($header_info[0]) + 1;

        if ($show_by_course) {
          $results_array = get_course_results_as_array($event, $key, $thing_to_show, $score_course, $max_score);
        }
        elseif ($show_by_class) {
          $results_array = get_class_results_as_array($event, $key, $thing_to_show, $score_course, $max_score);
        }
	$next_place_to_show = 0;
	continue;
      }
      else {
        $current_output = array_pad($current_output, $lines_to_show, "<td></td><td></td><td></td><td></td>\n");
        return(array("{$things_to_show[0]},0", $current_output, true));  # Start over next time
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
    $pick_next_item = false;
    $found_item = false;
    foreach ($things_to_show as $one_thing) {
      if ($pick_next_item) {   # Need to decide what to do about removed courses here
          $found_item = true;
	  $thing_to_show = $one_thing;
	  break;
	}

      if ($one_thing == $thing_to_show) {
        $pick_next_course = true;
      }
    }

    if ($found_item) {
      return(array("{$thing_to_show},0", $current_output, false));  # Start with the next course
    }
    else {
      return(array("{$things_to_show[0]},0", $current_output, true));  # Start over next time
    }
  }
  return(array("{$thing_to_show},{$next_place_to_show}", $current_output, false));  # Marker of where to start next
}

function get_column_headers($thing_to_show) {
  global $course_list, $courses_path, $things_to_show, $show_by_course, $show_by_class, $classification_info;
  global $TYPE_FIELD, $SCORE_O_COURSE, $MAX_SCORE_FIELD;
  global $color_mapping_hash;

  if ($show_by_course) {
    $readable_course_name = ltrim($thing_to_show, "0..9-");
    $course_to_show = $thing_to_show;
  }
  elseif ($show_by_class) {
    $course_to_show = $course_list[0];   // Have some default, event if it makes no sense
    $readable_course_name = get_course_for_class($classification_info, $thing_to_show);
    foreach ($course_list as $this_course) {
      if (strtolower($readable_course_name) == strtolower(ltrim($this_course, "0..9-"))) {
        $course_to_show = $this_course;
      }
    }
  }
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
  if ($show_by_course) {
    $column_header = $readable_course_name;
  }
  else {
    $column_header = "{$thing_to_show}:{$readable_course_name}";
  }
  $column_header_output = array();
  $column_header_output[] = "<td></td><td {$bgcolor}><strong><u>{$column_header}</u></strong></td><td></td><td width=20></td>\n";
  $column_header_output[] = "<td><strong>Pl</strong></td><td><strong>Name</strong></td><td><strong>Time</strong></td><td><strong>{$label_points_column}</strong></td>\n";

  return(array($column_header_output, $score_course, $max_score));
}

?>
