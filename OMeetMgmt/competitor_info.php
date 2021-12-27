<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

function non_empty($string_value) {
  return(strlen(trim($string_value)) > 0);
}

function get_competitor_info($competitor_base_path, $competitor_id, $status, $registration_info, $si_stick) {
  global $include_competitor_id, $show_removed_competitors, $include_date, $key, $event; 
  $competitor_string = "<tr>";
  $competitor_name = file_get_contents("{$competitor_base_path}/{$competitor_id}/name");
  $is_self_reported = file_exists("{$competitor_base_path}/{$competitor_id}/self_reported");
  $competitor_course = ltrim(file_get_contents("{$competitor_base_path}/{$competitor_id}/course"), "0..9-");

  $competitor_string .= "<td><input type=checkbox name=\"Remove-{$competitor_id}\" value=\"{$competitor_id}\"></td>";
  $competitor_string .= "<td>{$competitor_course}</td>";
  $competitor_string .= "<td>{$competitor_name}";
  if ($include_competitor_id) {
    $competitor_string .= " ({$competitor_id})";
  }
  if ($include_date) {
    $competitor_string .= "<br>(" . strftime("%a - %d", stat("{$competitor_base_path}/{$competitor_id}/name")["mtime"]) . ")";
  }
 
  $competitor_string .= "</td><td>{$status}</td><td><a href=\"./update_stick.php?key={$key}&event={$event}&competitor={$competitor_id}\">$si_stick</a></td>";
  if ($is_self_reported || $show_removed_competitors) {
    $competitor_string .= "<td>No splits</td>";
  }
  else {
    $competitor_string .= "<td><a href=\"../OMeetMgmt/edit_punches.php?event={$event}&key={$key}&competitor={$competitor_id}\">show</a> / ";
    $competitor_string .=     "<a href=\"../OMeetMgmt/edit_punches.php?event={$event}&key={$key}&competitor={$competitor_id}&allow_editing=1\">edit</a></td>";
  }

  if (count($registration_info) > 0) {
    $registration_info_strings = array_map(function ($key) use ($registration_info) { return("{$key} = " . htmlentities($registration_info[$key])); },
                                                                                                                array_diff(array_keys($registration_info),
                                                                                                                           array("first_name", "last_name")));
    $competitor_string .= "<td>" . implode(", ", $registration_info_strings)  . "</td>";
  }
  else {
    $competitor_string .= "<td></td>";
  }
  $competitor_string .= "</tr>";
  
  return($competitor_string);
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
$key = $_GET["key"];
$include_competitor_id = ($_GET["include_competitor_id"] != "");
$include_date = ($_GET["include_date"] != "");
$include_finishers = ($_GET["include_finishers"] != "");
$show_removed_competitors = isset($_GET["show_removed"]);

if (($event == "") || (!key_is_valid($key))) {
  error_and_exit("Empty event \"{$event}\" or bad location key \"{$key}\", is this an unauthorized link?\n");
}

if (!file_exists(get_event_path($event, $key, ".."))) {
  error_and_exit("No such event \"{$event}\", is this an authorized link?\n");
}

set_timezone($key);

$results_string = "";
if ($show_removed_competitors) {
  $competitor_directory = get_event_path($event, $key) . "/removed_competitors";
}
else {
  $competitor_directory = get_competitor_directory($event, $key, "..");
}

if (is_dir($competitor_directory)) {
  $competitor_list = scandir("${competitor_directory}");
  $competitor_list = array_diff($competitor_list, array(".", ".."));
}
else {
  $competitor_list = array();
}

$event_name = file_get_contents(get_event_path($event, $key) . "/description");

$current_time = time();
$name_hash = array();
$obsolete_registrations = array();


$competitor_outputs = array();
foreach ($competitor_list as $competitor) {
  $course = file_get_contents("${competitor_directory}/${competitor}/course");
  $is_self_reported = file_exists("{$competitor_directory}/{$competitor}/self_reported");
  $finish_file_exists = file_exists("{$competitor_directory}/{$competitor}/controls_found/finish");
  $has_finished = $finish_file_exists || $is_self_reported;
  $competitor_name = file_get_contents("{$competitor_directory}/{$competitor}/name");
  if (!$has_finished || $include_finishers) {
    if (file_exists("{$competitor_directory}/{$competitor}/registration_info")) {
      $registration_info = parse_registration_info(file_get_contents("{$competitor_directory}/{$competitor}/registration_info"));
    }
    else {
      $registration_info = array();
    }

    if (file_exists("{$competitor_directory}/{$competitor}/si_stick")) {
      $si_stick = file_get_contents("{$competitor_directory}/{$competitor}/si_stick");
    }
    else {
      $si_stick = "none";
    }
    
    if (file_exists("${competitor_directory}/${competitor}/controls_found/finish")) {
      $status = "finished";
    }
    else if (file_exists("{$competitor_directory}/${competitor}/controls_found/start")) {
      $status = "on course";
    }
    else if (file_exists("{$competitor_directory}/{$competitor}/si_stick")) {
      $status = "registered";
    }
    else if ($is_self_reported) {
      $status = "self reported";
    }
    else {
      $status = "not started";
    }

    # The start time for an si_stick user is not a good indicator of how old the entry is, so always use
    # the registration time for si_stick users
    $competitor_info = get_competitor_info($competitor_directory, $competitor, $status, $registration_info, $si_stick);
    if (!file_exists("${competitor_directory}/${competitor}/controls_found/start") || ($si_stick != "none")) {
      $file_info = stat("{$competitor_directory}/{$competitor}");
      // Weed out people who's registration time is too old (one day in seconds)
      if (($current_time - $file_info["mtime"]) < $TIME_LIMIT) {
        $competitor_outputs[] = $competitor_info;
      }
    }
    else {
      $start_time = file_get_contents("{$competitor_directory}/${competitor}/controls_found/start");
      // Weed out people who started more than one day ago
      if (($current_time - $start_time) < $TIME_LIMIT) {
        $competitor_outputs[] = $competitor_info;
      }
    }
  }

  // Look for obsolete registrations
  // Use the fact that the registrations are listed in the order they were entered
  // If person A registers and has not yet finished, remember that (in $name_hash)
  // If we see another registration for person A, the remembered one is obsolete and should be deleted
  // If the registration is finished, then don't remember it (person A could run a second course legitimately)
  // Possible problem - two people who have the EXACT same name - problem for another day
  if (isset($name_hash[$competitor_name])) {
    $obsolete_registrations[] = $name_hash[$competitor_name];
    if ($has_finished) {
      unset($name_hash[$competitor_name]);
    }
    else {
      $name_hash[$competitor_name] = $competitor_info;
    }
  }
  else {
    if (!$has_finished) {
      $name_hash[$competitor_name] = $competitor_info;
    }
  }
}

$time_limit_string = "<form action=./competitor_info.php>\n";
$time_limit_string .= "<p>Show competitors registered within the past:\n";
$time_limit_string .= "<ul><li><input type=radio name=TIME_LIMIT value=86400 " . (($TIME_LIMIT == 86400) ? " checked " : "") . "> 1 day\n";
$time_limit_string .= "<li><input type=radio name=TIME_LIMIT value=604800 " . (($TIME_LIMIT == 604800) ? " checked " : "") . "> 1 week\n";
$time_limit_string .= "<li><input type=radio name=TIME_LIMIT value=2678400 " . (($TIME_LIMIT == 2678400) ? " checked " : "") . "> 1 month\n";
$time_limit_string .= "</ul>\n";
$time_limit_string .= "<input type=hidden name=\"key\" value=\"${key}\">\n";
$time_limit_string .= "<input type=hidden name=\"event\" value=\"${event}\">\n";
$time_limit_string .= "<p>Include finished competitors? <input type=checkbox name=\"include_finishers\" value=\"1\"" . ($include_finishers ? " checked " : "")  . ">\n";
$time_limit_string .= "<p>Show removed competitors? <input type=checkbox name=\"show_removed\" value=\"1\"" . ($show_removed_competitors ? " checked " : "")  . ">\n";
$time_limit_string .= "<p><input type=submit value=\"Update competitor list\"></form>\n";


$results_string = "<p>Competitors for {$event_name}<p><p>\n";
if ($show_removed_competitors) {
  $results_string .= "<form action=\"../OMeetMgmt/restore_to_event.php\">\n";
}
else {
  $results_string .= "<form action=\"../OMeetMgmt/remove_from_event.php\">\n";
}
$results_string .= "<input type=hidden name=\"key\" value=\"${key}\">\n";
$results_string .= "<input type=hidden name=\"event\" value=\"${event}\">\n";
$button_label = $show_removed_competitors ? "Restore" : "Remove";
$results_string .= "\n<table><tr><th><input type=submit value=\"{$button_label}\"></th><th>Course</th><th>Competitor</th><th>Status</th><th>Si Unit</th>" .
                                "<th>Punches</th><th>Info</th></tr>\n";
$results_string .= implode("\n", $competitor_outputs);
if (count($obsolete_registrations) > 0) {
  $results_string .= "<tr><td colspan=\"7\">&nbsp;</td></tr><tr><td colspan=\"7\">&nbsp;</td></tr>\n";
  $results_string .= "<tr><td colspan=\"7\"><strong>Obsolete registration entries which likely should be removed</strong></td></tr>\n";
  $results_string .= implode("\n", $obsolete_registrations);
}
$results_string .= "\n</table>\n</form>\n";


echo get_web_page_header(true, true, true);

echo $time_limit_string;

echo $results_string;


echo get_web_page_footer();
?>
