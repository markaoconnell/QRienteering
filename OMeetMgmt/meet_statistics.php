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
  return ("<li><a href=./view_results.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
}

function get_percent_data_item($partial, $total) {
  if ($total != 0) {
    $result_string = "<td style=\"text-align:center\">{$partial}<br>" . floor(100 * ($partial / $total)) . "%</td>";
  }
  else {
    $result_string = "<td style=\"text-align:center\">0</td>";
  }

  return($result_string);
}

ck_testing();

set_page_title("Orienteering Meet Statistics Page");

// Get the submitted info
// echo "<p>\n";
$event = isset($_GET["event"]) ? $_GET["event"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
$download_csv_flag = isset($_GET["download_csv"]) ? $_GET["download_csv"] : "";
$download_csv = ($download_csv_flag != "");


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

$results_path = get_results_path($event, $key);
$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

set_timezone($key);
$event_name = file_get_contents(get_event_path($event, $key) . "/description");

$results_string = "";
if ($download_csv) {
  $results_string = "<pre>\n";
  $results_string .= "starts,members,non-members,qr_coders,self_reported,si_unit,dnfs,complete\n";
}
else {
  $results_string = "<table><tr><th>Course</th><th>Starts</th><th>Members</th><th>Non-Members</th><th>QR code users</th>";
  $results_string .=           "<th>Self Reported</th><th>SI unit users</th><th>DNFs</th><th>Completed</th></tr>\n";
}


$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));

$totals_array = array("starts" => 0, "members" => 0, "non-members" => 0, "qr_coders" => 0, "self_reported" => 0,
                      "si_unit" => 0, "dnfs" => 0, "complete" => 0, "unique_starts" => 0, "total_participants" => 0);
$course_stats = array();
$unique_names = array();

foreach ($course_list as $one_course) {
  $show_course = true;
  if (file_exists("{$courses_path}/{$one_course}/removed")) {
    // Show a removed course if there are finishers
    if (file_exists("{$results_path}/{$one_course}")) {
      $results_list = scandir("{$results_path}/{$one_course}");
      $results_list = array_diff($results_list, array(".", ".."));
      $show_course = (count($results_list) > 0);
    }
    else {
      $show_course = false;
    }
  }

  if ($show_course || isset($_GET["show_all_courses"])) {
    $course_properties = get_course_properties("{$courses_path}/{$one_course}");
    $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
    $max_score = 0;
    if ($score_course) {
      $max_score = $course_properties[$MAX_SCORE_FIELD];
    }

    $course_stats_array = get_course_stats($event, $key, $one_course);
    $totals_array["starts"] += $course_stats_array["starts"];
    $totals_array["members"] += $course_stats_array["members"];
    $totals_array["non-members"] += $course_stats_array["non-members"];
    $totals_array["qr_coders"] += $course_stats_array["qr_coders"];
    $totals_array["self_reported"] += $course_stats_array["self_reported"];
    $totals_array["si_unit"] += $course_stats_array["si_unit"];
    $totals_array["dnfs"] += $course_stats_array["dnfs"];
    $totals_array["complete"] += $course_stats_array["complete"];
    $course_stats[$one_course] = $course_stats_array;

    foreach (array_keys($course_stats_array["start_names"]) as $possible_unique_starter) {
      if (!isset($unique_names[$possible_unique_starter])) {
        $unique_names[$possible_unique_starter] = 1;
	$totals_array["unique_starts"]++;
	// Try and figure out how many people went out together - this isn't perfect, but hopefully it works reasonably
	// If there is a number at the end, then assume that it the number of extra people - so assume an entry like
	// John Doe +1 or Jane Dough (1) or Jonah Donut - 3
	// Lastly, also accept Bill Bob 4 as 5 people
	// So 2 participlants in the first two and 4 in the last one
	$regex_matches = array();
        $totals_array["total_participants"]++;
	if (preg_match('/[+-]\s*(\d+)\s*$/', $possible_unique_starter, $regex_matches)) {
	  $totals_array["total_participants"] += $regex_matches[1];
	}
	else if (preg_match('/\(\s*(\d+)\s*\)\s*$/', $possible_unique_starter, $regex_matches)) {
	  $totals_array["total_participants"] += $regex_matches[1];
	}
	else if (preg_match('/\s*(\d+)\s*$/', $possible_unique_starter, $regex_matches)) {
	  $totals_array["total_participants"] += $regex_matches[1];
	}
	else {
	  // No number found - assume a single participant
	  // If ambitious, one could look at the number of spaces to see if maybe multiple names
	  // were listed, but I'm not that ambitious, at least not yet
	}
      }
    }

    if ($download_csv) {

    }
    else {
      $results_string .= "<tr><td>" . ltrim($one_course, "0..9-") . "</td>\n";
      $results_string .= "<td style=\"text-align:center\">{$course_stats_array["starts"]}</td>\n";
      $results_string .= get_percent_data_item($course_stats_array["members"], $course_stats_array["starts"]);
      $results_string .= get_percent_data_item($course_stats_array["non-members"], $course_stats_array["starts"]);
      $results_string .= get_percent_data_item($course_stats_array["qr_coders"], $course_stats_array["starts"]);
      $results_string .= get_percent_data_item($course_stats_array["self_reported"], $course_stats_array["starts"]);
      $results_string .= get_percent_data_item($course_stats_array["si_unit"], $course_stats_array["starts"]);
      $results_string .= get_percent_data_item($course_stats_array["dnfs"], $course_stats_array["starts"]);
      $results_string .= get_percent_data_item($course_stats_array["complete"], $course_stats_array["starts"]);
      $results_string .= "</tr>\n";
    }
  }
}

$results_string .= "<tr><td>Meet Totals</td>\n";
$results_string .= "<td style=\"text-align:center\">{$totals_array["starts"]}</td>\n";
$results_string .= get_percent_data_item($totals_array["members"], $totals_array["starts"]);
$results_string .= get_percent_data_item($totals_array["non-members"], $totals_array["starts"]);
$results_string .= get_percent_data_item($totals_array["qr_coders"], $totals_array["starts"]);
$results_string .= get_percent_data_item($totals_array["self_reported"], $totals_array["starts"]);
$results_string .= get_percent_data_item($totals_array["si_unit"], $totals_array["starts"]);
$results_string .= get_percent_data_item($totals_array["dnfs"], $totals_array["starts"]);
$results_string .= get_percent_data_item($totals_array["complete"], $totals_array["starts"]);
$results_string .= "</tr>\n";
$results_string .= "</table>\n";
$results_string .= "<p>{$totals_array["unique_starts"]} unique starts\n";
$results_string .= "<p>{$totals_array["total_participants"]} total participants (best guess)\n";

if ($download_csv) {
  $results_string .= "</pre>\n";
}

echo get_web_page_header(true, true, false);
echo "<p>Overall meet statistics for: <strong>{$event_name}</strong>\n";

echo $results_string;

echo get_web_page_footer();
?>
