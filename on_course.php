<?php
require 'common_routines.php';

ck_testing();

function non_empty($string_value) {
  return(strlen(trim($string_value)) > 0);
}

// Get the submitted info
// echo "<p>\n";
if ($_GET["TIME_LIMIT"] == "") {
  $TIME_LIMIT = 86400;  // One day in seconds
}
else {
  $TIME_LIMIT = intval($_GET["TIME_LIMIT"]);
}

$event = $_GET["event"];
$include_competitor_id = ($_GET["include_competitor_id"] != "");

$results_string = "";
$competitor_directory = "./${event}/Competitors";
$competitor_list = scandir("${competitor_directory}");
$competitor_list = array_diff($competitor_list, array(".", ".."));

$courses_array = scandir('./' . $_GET["event"] . '/Courses');
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries

$current_time = time();

$not_started = array();
$on_course = array();
foreach ($courses_array as $course) {
  $on_course[$course] = array();
}

foreach ($competitor_list as $competitor) {
  if (!file_exists("${competitor_directory}/${competitor}/controls_found/finish")) {
    if (!file_exists("${competitor_directory}/${competitor}/controls_found/start")) {
      $file_info = stat("{$competitor_directory}/{$competitor}");
      // Weed out people who's registration time is too old (one day in seconds)
      if (($current_time - $file_info["mtime"]) < $TIME_LIMIT) {
        $not_started[] = $competitor;
      }
    }
    else {
      $course = file_get_contents("${competitor_directory}/${competitor}/course");
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
if (count($not_started) > 0) {
  $outstanding_entrants = true;
  $results_string .= "<p>Registered but not started\n";
  $results_string .= "<table><tr><th>Name</th></tr>\n";
  foreach ($not_started as $competitor) {
    $competitor_name = file_get_contents("${competitor_directory}/${competitor}/name");
    if ($include_competitor_id) {
      $competitor_name .= " ({$competitor})";
    }
    $results_string .= "<tr><td>${competitor_name}</td></tr>";
  }
  $results_string .= "</table>\n<p><p><p>\n";
}

foreach (array_keys($on_course) as $course) {
//  echo "Looking at $course.\n";
//  print_r($on_course[$course]);
  if (count($on_course[$course]) > 0) {
    $outstanding_entrants = true;
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
      $controls_done = array_diff($controls_done, array(".", "..", "start", "finish"));
      $num_controls_done = count($controls_done);
      if ($num_controls_done > 0) {
        // The format of the filename is <time>,<control_id>
        $last_control_entry = $controls_done[$num_controls_done - 1];
        $last_control_info_array = explode(",", $last_control_entry);
        
        $last_control_time = $last_control_info_array[0];

        // For the split times, controls are 0 based, but for printing, make them 1 based
        $last_control = $num_controls_done;
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

if (outstanding_entrants) {
  echo $results_string;
}
else {
  echo "<p>No outstanding entrants at this point.\n";
}

echo get_web_page_footer();
?>
