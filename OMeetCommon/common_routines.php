<?php

function ck_testing() {
  if (file_exists("./testing_mode.php")) {
    require "./testing_mode.php";
    artificial_input_file_parse();
  }
}

// Return a string with the elapsed time in seconds pretty printed
function formatted_time($time_in_seconds) {
  $hours = floor($time_in_seconds / 3600);
  $mins = floor(($time_in_seconds / 60) % 60);
//  $hours = 0;
//  $mins = floor($time_in_seconds / 60);
  $secs = ($time_in_seconds % 60);

  if ($hours > 0) {
    return sprintf("%2dh:%02dm:%02ds", $hours, $mins, $secs);
  }
  else if ($mins > 0) {
    return sprintf("   %2dm:%02ds", $mins, $secs);
  }
  else {
    return sprintf("       %2ds", $secs);
  }
}

// Convert a time limit of the form XXhYYmZZs to seconds.
// Note - all fields are optional
// If just a number, it is assumed to be in seconds (backwards compatability)
// Errors return a limit of -1
function time_limit_to_seconds($time_limit_entry) {
  $limit_in_seconds = 0;

  if (preg_match('/^[0-9]+$/', $time_limit_entry)) {
    // old style entry, assume just in seconds
    return ($time_limit_entry);
  }

  $remaining_time = trim($time_limit_entry);
  if (preg_match('/^[0-9]+h/', $remaining_time)) {
    $h_location = strpos($remaining_time, "h");
    $limit_in_seconds += substr($remaining_time, 0, $h_location) * 3600;
    $remaining_time = substr($remaining_time, $h_location + 1);
  }

  $remaining_time = trim($remaining_time);
  if (preg_match('/^[0-9]+m/', $remaining_time)) {
    $m_location = strpos($remaining_time, "m");
    $limit_in_seconds += substr($remaining_time, 0, $m_location) * 60;
    $remaining_time = substr($remaining_time, $m_location + 1);
  }

  $remaining_time = trim($remaining_time);
  if (preg_match('/^[0-9]+s/', $remaining_time)) {
    $s_location = strpos($remaining_time, "s");
    $limit_in_seconds += substr($remaining_time, 0, $s_location);
    $remaining_time = substr($remaining_time, $s_location + 1);
  }

  if (trim($remaining_time) != "") {
    return(-1);
  }

  return($limit_in_seconds);
}



// Return a string with the elapsed time in seconds formatted for easy parsing
function csv_formatted_time($time_in_seconds) {
  $mins = floor($time_in_seconds / 60);
  $secs = ($time_in_seconds % 60);

  return sprintf("%3d:%02d", $mins, $secs);
}


// Am I running on a mobile device?
function is_mobile () {
  return is_numeric(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "mobile"));
}


// Print out the default headers
function get_web_page_header($paragraph_style, $table_style, $form_style) {
  $headers_to_show = <<<END_OF_HEADERS
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>Orienteering Event Management</title>
  <meta content="Mark O'Connell" name="author">
END_OF_HEADERS;

  if ($paragraph_style) {
    $headers_to_show .= get_paragraph_style_header();
  }

  if ($table_style) {
    $headers_to_show .= get_table_style_header();
  }

  if ($form_style) {
    $headers_to_show .= get_input_form_style_header();
  }

  $headers_to_show .= "\n</head>\n<body>\n<br>\n";
  return ($headers_to_show);
}

function get_web_page_footer() {
  $footers_to_show = "\n</body>\n</html>\n";
  return ($footers_to_show);
}

function error_and_exit($error_string) {
  echo get_web_page_header(true, false, false);
  echo $error_string;
  echo get_web_page_footer();
  exit(1);
}

function get_error_info_string() {
  $extra_error_info = <<<END_OF_ERROR_INFO
<br><p>This is a BYOM (Bring Your Own Map) Orienteering control.  For more information on orienteering, 
type "orienteering new england" into Google to learn about the sport and to find events in your area.
If this is hanging in the woods, please leave it alone so as not to ruin an existing orienteering course that
others may be currently enjoying.
END_OF_ERROR_INFO;
  return ($extra_error_info);
}

// get table style elements
function get_table_style_header() {
  if (is_mobile()) {
    return "<style>\n td {\nfont-size: 200%;\n}\n th {\n font-size: 220%;\n}\ntable, th, td {\n border-collapse: collapse;\n border: 1px solid lightgray;\n }\n</style>\n";
  }
  else {
    return "<style>\n td {\nfont-size: 100%;\n}\n th {\n font-size: 120%;\n}\ntable, th, td {\n border-collapse: collapse;\n border: 1px solid lightgray;\n }\n</style>\n";
  }
}

// get paragraph style elements
function get_paragraph_style_header() {
  if (is_mobile()) {
    return "<style>\n p {\nfont-size: 300%;\n}\n .title\n {\nfont-size: 350%;\n}\n </style>\n";
  }
  else {
    return "<style>\n p {\nfont-size: 100%;\n}\n .title\n {\nfont-size: 125%;\n}\n </style>\n";
  }
}

// get input form style elements
function get_input_form_style_header() {
  if (is_mobile()) {
    return "<style>input {\nfont-size: 110%;s\n}\ninput[type=submit] {\nheight : 85%;\n}\ninput[type=radio]\n{\ntransform:scale(2)\n}\n</style>\n";
  }
  else {
    return "";
  }
}


// Show the results for a course
function show_results($event, $key, $course, $show_points, $max_points, $path_to_top) {
  $result_string = "";
  $result_string .= "<p>Results on " . ltrim($course, "0..9-") . "\n";

  $results_path = get_results_path($event, $key, $path_to_top);
  if (!is_dir("{$results_path}/${course}")) {
    $result_string .= "<p>No Results yet<p><p><p>\n";
    return($result_string);
  }
  $results_list = scandir("{$results_path}/${course}");
  $results_list = array_diff($results_list, array(".", ".."));

  if ($show_points) {
    $points_header = "<th>Points</th>";
  }
  else {
    $points_header = "";
  }

  $result_string .= "<table border=1><tr><th>Name</th><th>Time</th>{$points_header}</tr>\n";
  $dnfs = "";
  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key, $path_to_top);
    $competitor_name = file_get_contents("${competitor_path}/name");
    if ($show_points) {
      $points_value = "<td>" . ($max_points - $result_pieces[0]) . "</td>";
    }
    else {
      $points_value = "";
    }

    if (!file_exists("./${competitor_path}/dnf")) {
      $result_string .= "<tr><td><a href=\"./show_splits?course=${course}&event=${event}&key={$key}&entry=${this_result}\">${competitor_name}</a></td><td>" . formatted_time($result_pieces[1]) . "</td>{$points_value}</tr>\n";
    }
    else {
      // For a scoreO course, there are no DNFs, so $points_value should always be "", but show it just in case
      $dnfs .= "<tr><td><a href=\"./show_splits?course=${course}&event=${event}&key={$key}&entry=${this_result}\">${competitor_name}</a></td><td>DNF</td>{$points_value}</tr>\n";
    }
  }
  $result_string .= "${dnfs}</table>\n<p><p><p>";
  return($result_string);
}

// Show the results for a course as a csv
function get_csv_results($event, $key, $course, $show_points, $max_points, $path_to_top) {
  $result_string = "";
  $readable_course_name = ltrim($course, "0..9-");

  // No results yet - .csv is empty
  $results_path = get_results_path($event, $key, $path_to_top);
  if (!is_dir("{$results_path}/{$course}")) {
    return("");
  }
  
  $results_list = scandir("${results_path}/{$course}");
  $results_list = array_diff($results_list, array(".", ".."));

  $dnfs = "";
  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key, $path_to_top);
    $competitor_name = file_get_contents("${competitor_path}/name");
    if ($show_points) {
      $points_value = $max_points - $result_pieces[0];
    }
    else {
      $points_value = "";
    }

    if (!file_exists("{$competitor_path}/dnf")) {
      $result_string .= "${readable_course_name};${competitor_name};" . csv_formatted_time($result_pieces[1]) . ";{$points_value}\n";
    }
    else {
      $result_string .= "${readable_course_name};${competitor_name};DNF;${points_value}\n";
    }
  }
  return($result_string);
}

function get_all_course_result_links($event, $key, $path_to_top) {
  $course_list = scandir(get_courses_path($event, $key, $path_to_top));
  $course_list = array_diff($course_list, array(".", ".."));

  $links_string = "<p>Show results for ";
  foreach ($course_list as $one_course) {
    $links_string .= "<a href=\"../OMeet/view_results?event=${event}&key={$key}&course=$one_course\">" . ltrim($one_course, "0..9-") . "</a> \n";
  }
  $links_string .= "<a href=\"../OMeet/view_results?event=${event}&key={$key}\">All</a> \n";

  return($links_string);
}


function read_controls($filename) {
  $control_list = file($filename);
  $control_list = array_values(array_filter($control_list, function ($string) { return (strlen(trim($string)) > 0); }));
  return (array_map(function ($string) { return(explode(":", trim($string))); },  $control_list));
}


// Turn a string of the form <key>,<base64value>,<key>,<base64value>,.... into a key->value hash
function parse_registration_info($raw_info_string) {
  $pair_list = explode(",", $raw_info_string);
  $return_hash = array();
  for ($i = 0; $i < count($pair_list); $i += 2) {
    $return_hash[$pair_list[$i]] = base64_decode($pair_list[$i + 1]);
  }

  return($return_hash);
}


// Functions to return the paths to commonly used areas
$keys_read = false;
$keys_hash = array();

// For testing purposes only
function key_reset() {
  global $keys_read, $keys_hash;

  $keys_read = false;
  $keys_hash = array();
}

function key_is_valid($key) {
  global $keys_read, $keys_hash;

  if (!$keys_read) {
    if (file_exists("../keys")) {
      $key_file_lines = file("../keys", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($key_file_lines as $key_line) {
        // Format is key, path, password
        $line_pieces = explode(",", $key_line);
        $keys_hash[trim($line_pieces[0])] = array(trim($line_pieces[1]), trim($line_pieces[2]));
      }

      $keys_read = true;
    }
    else {
      $keys_read = true;
    }
  }

  // Legacy hack for the moment - a blank key without a key file is ok
  if (!file_exists("../keys") && ($key == "")) {
    return true;
  }

  return(isset($keys_hash[$key]));
}

function key_password_ok($key, $password) {
  global $keys_hash;

  // Key must have been checked for validity already
  // an empty key is temporarily allowed for backward compatibility
  if ($key != "") {
    return(key_is_valid($key) && ($keys_hash[$key][1] == $password));
  } 

  return true;
}

function key_to_path($key) {
  global $keys_hash;

  // Key must have been checked for validity already
  // an empty key is temporarily allowed for backward compatibility
  if ($key != "") {
    if (key_is_valid($key)) {
      return($keys_hash[$key][0]);
    }
  } 

  return "";
}

function get_courses_path($event, $key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key) . "/{$event}/Courses");
}

function get_competitor_directory($event, $key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key) . "/{$event}/Competitors");
}

function get_competitor_path($competitor, $event, $key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key) . "/{$event}/Competitors/{$competitor}");
}

function get_results_path($event, $key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key) . "/{$event}/Results");
}

function get_event_path($event, $key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key) . "/{$event}");
}

function get_base_path($key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key));
}

function get_members_path($key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key) . "/members.csv");
}

function get_nicknames_path($key, $path_to_top) {
  return($path_to_top . "/OMeetData/" . key_to_path($key) . "/nicknames.csv");
}
?>
