<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

function is_event($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("${base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function name_to_link($event_id) {
  global $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./on_course.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
}

function non_empty($string_value) {
  return(strlen(trim($string_value)) > 0);
}

ck_testing();

$event = $_GET["event"];
$key = isset($_GET["key"]) ?  $_GET["key"] : "";
$key = translate_key($key);

if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}
$base_path = get_base_path($key, "..");

if ($event == "") {
  // No event specified - show a list
  // If there is only one, then auto-choose it
  $event_list = scandir($base_path);
  $event_list = array_filter($event_list, is_event);
  if (count($event_list) == 1) {
    $event = basename(current($event_list));
  }
  else if (count($event_list) > 1) {

    echo get_web_page_header(true, true, false);
    $event_output_array = array_map(name_to_link, $event_list);
    echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
    echo get_web_page_footer();

    return;
  }
  else {
    echo get_web_page_header(true, true, false);
    echo "<p>No available events.\n";
    echo get_web_page_footer();
    return;
  }
}


// Get the submitted info
// echo "<p>\n";
if (!isset($_GET["TIME_LIMIT"]) || ($_GET["TIME_LIMIT"] == "")) {
  $TIME_LIMIT = 86400;  // One day in seconds
}
else {
  $TIME_LIMIT = intval($_GET["TIME_LIMIT"]);
}

$include_competitor_id = isset($_GET["include_competitor_id"]);

if (($event == "") || (!key_is_valid($key))) {
  error_and_exit("Empty event \"{$event}\" or bad location key \"{$key}\", is this an unauthorized link?\n");
}

set_timezone($key);
$event_name = file_get_contents(get_event_path($event, $key) . "/description");

$results_string = "";
$competitor_directory = get_competitor_directory($event, $key, "..");
$competitor_list = scandir("${competitor_directory}");
$competitor_list = array_diff($competitor_list, array(".", ".."));

$courses_path = get_courses_path($event, $key, "..");
$courses_array = scandir($courses_path);
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries

$current_time = time();

$not_started = array();
$on_course = array();
foreach ($courses_array as $course) {
  $on_course[$course] = array();
  $not_started[$course] = array();
}

$found_registered_not_started = false;
foreach ($competitor_list as $competitor) {
  $course = file_get_contents("${competitor_directory}/${competitor}/course");
  if (!file_exists("${competitor_directory}/${competitor}/controls_found/finish")) {
    if (!file_exists("${competitor_directory}/${competitor}/controls_found/start")) {
      $file_info = stat("{$competitor_directory}/{$competitor}");
      // Weed out people who's registration time is too old (one day in seconds)
      // or if they have self reported (they have no start time but really are done)
      if (!file_exists("{$competitor_directory}/{$competitor}/self_reported")) {
	if (($current_time - $file_info["mtime"]) < $TIME_LIMIT) {
          $not_started[$course][] = $competitor;
          $found_registered_not_started = true;
	}
      }
    }
    else {
      $start_time = file_get_contents("{$competitor_directory}/${competitor}/controls_found/start");
      // Weed out people who started more than one day ago
      if (($current_time - $start_time) < $TIME_LIMIT) {
        $on_course[$course][] = $competitor;
      }
    }
  }
}

$outstanding_entrants = false;
$results_string = "";
if ($found_registered_not_started) {
  $results_string .= "<p>Registered competitors\n";
  $results_string .= "<table><tr><th>Name</th><th>Course</th><th>Status</th></tr>\n";
  foreach (array_keys($not_started) as $course) {
    if (count($not_started[$course]) > 0) {
      $outstanding_entrants = true;
      foreach ($not_started[$course] as $competitor) {
        $competitor_name = file_get_contents("${competitor_directory}/${competitor}/name");
        if ($include_competitor_id) {
          $competitor_name .= " ({$competitor})";
        }
        if (file_exists("{$competitor_directory}/{$competitor}/si_stick")) {
          $status_field = "si runner";
        }
        else {
          $status_field = "not started";
        }
        $results_string .= "<tr><td>${competitor_name}</td><td>" . ltrim($course, "0..9-") . "</td><td>{$status_field}</td></tr>";
      }
    }
  }
  $results_string .= "</table>\n<p><p><p>\n";
}

foreach (array_keys($on_course) as $course) {
//  echo "Looking at $course.\n";
//  print_r($on_course[$course]);
  if (count($on_course[$course]) > 0) {
    $outstanding_entrants = true;
    $course_properties = get_course_properties("${courses_path}/${course}");
    $is_score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));

    $results_string .= "<p>Currently on " . ltrim($course, "0..9-") . "\n";
    $results_string .= "<table><tr><th>Name</th><th>Start time</th><th>Last control</th><th>Last control time</th></tr>\n";
    foreach ($on_course[$course] as $competitor) {
      $competitor_path = "${competitor_directory}/${competitor}";
      $competitor_name = file_get_contents("${competitor_path}/name");
      if ($include_competitor_id) {
        $competitor_name .= " ({$competitor})";
      }
      $start_time = file_get_contents("${competitor_path}/controls_found/start");
      $controls_done = scandir("${competitor_path}/controls_found");
      $controls_done = array_values(array_diff($controls_done, array(".", "..", "start", "finish")));
      $num_controls_done = count($controls_done);
      if ($num_controls_done > 0) {
        // The format of the filename is <time>,<control_id>
        $last_control_entry = $controls_done[$num_controls_done - 1];
        $last_control_info_array = explode(",", $last_control_entry);
        
        $last_control_time = $last_control_info_array[0];

        // For the split times, controls are 0 based, but for printing, make them 1 based
        // For a scoreO, just report the control id
        if ($is_score_course) {
          $last_control = $last_control_info_array[1];
        }
        else {
          $last_control = $num_controls_done;
        }
      }
      else {
        $last_control = "start";
        $last_control_time = $start_time;
      }
    
      // See if they have mispunched anything more recently than the last correct punch - if so,
      // report this
      if (file_exists("{$competitor_path}/extra")) {
        $extra_controls = explode("\n", file_get_contents("{$competitor_path}/extra"));
        $extra_controls = array_values(array_filter($extra_controls, non_empty));
        $num_extra_controls = count($extra_controls);
        $last_extra_control_info = $extra_controls[$num_extra_controls - 1];
        $last_extra_control_pieces = explode(",", $last_extra_control_info);   // Format is time,control-id

        if (intval($last_extra_control_pieces[0]) > intval($last_control_time)) {
           $last_control_time = $last_extra_control_pieces[0];
           $last_control = "{$last_extra_control_pieces[1]} (not on course)";
        }
      }

      $results_string .= "<tr><td>${competitor_name}</td>\n<td>" . strftime("%T", $start_time) . "</td>\n";
      $results_string .= "<td>${last_control}</td>\n<td>" . strftime("%T", $last_control_time) . "</td></tr>\n";
    }
    $results_string .= "</table><p><p><p>\n";
  }
}

echo get_web_page_header(true, true, false);

echo "<p>Competitors not yet finished for: <strong>{$event_name}</strong><br>\n";

if ($outstanding_entrants) {
  echo $results_string;
}
else {
  echo "<p>No outstanding entrants at this point.\n";
}

echo get_web_page_footer();
?>
