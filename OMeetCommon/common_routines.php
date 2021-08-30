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
  if (preg_match('/^[0-9]+M/', $remaining_time)) {
    $month_location = strpos($remaining_time, "M");
    $limit_in_seconds += substr($remaining_time, 0, $month_location) * 86400 * 30;
    $remaining_time = substr($remaining_time, $month_location + 1);
  }

  $remaining_time = trim($remaining_time);
  if (preg_match('/^[0-9]+d/', $remaining_time)) {
    $d_location = strpos($remaining_time, "d");
    $limit_in_seconds += substr($remaining_time, 0, $d_location) * 86400;
    $remaining_time = substr($remaining_time, $d_location + 1);
  }

  $remaining_time = trim($remaining_time);
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


// Return a string with the elapsed time in seconds formatted for easy parsing
function format_time_as_minutes_since_midnight($unix_timestamp) {
  $hours_mins_secs = strftime("%T", $unix_timestamp);
  $time_pieces = explode(":", $hours_mins_secs);

  $mins = ($time_pieces[0] * 60) + $time_pieces[1];
  $secs = $time_pieces[2];

  return sprintf("%4d:%02d", $mins, $secs);
}


// Am I running on a mobile device?
function is_mobile () {
  return (isset($_SERVER['HTTP_USER_AGENT']) && is_numeric(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "mobile")));
}


$bg_color = "";

// Print out the default headers
function get_web_page_header($paragraph_style, $table_style, $form_style) {
  global $bg_color;

  $headers_to_show = <<<END_OF_HEADERS
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>Orienteering Event Management</title>
  <meta content="Mark O'Connell" name="author">
  <style>body{padding-left:1.5em;}</style>
END_OF_HEADERS;

  if ($bg_color != "") {
    $headers_to_show .= get_bg_color_element($bg_color);
  }

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

  set_error_background();

  echo get_web_page_header(true, false, false);
  echo $error_string;
  echo get_web_page_footer();
  exit(1);
}

function set_success_background() {
  global $bg_color;
  $bg_color = "#66ff33";
}

function set_error_background() {
  global $bg_color;
  $bg_color = "#cc3300";
}


function get_error_info_string() {
  $error_file = get_error_msg_file();
  if (file_exists($error_file)) {
    $extra_error_info = file_get_contents($error_file);
  }
  else {
    $extra_error_info = <<<END_OF_ERROR_INFO
<br><p>This is a BYOM (Bring Your Own Map) Orienteering control.  For more information on orienteering, 
visit <a href="https://newenglandorienteering.org/">https://newenglandorienteering.org/</a>
to learn about the sport and to find events in your area.
If this is hanging in the woods, please leave it alone so as not to ruin an existing orienteering course that
others may be currently enjoying.
END_OF_ERROR_INFO;
  }
  return ($extra_error_info);
}

// get the background color
function get_bg_color_element($bg_color) {
  return "<style>\n body {\nbackground-color: {$bg_color};\n}\n</style>\n";
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
  if (is_mobile()) {	// JP 3-11-21
    return "<style>input {\nfont-size: 110%;\n}\ninput[type=submit] {\nheight : 110%;background-color:#ffff33;\n}\n" .
    	   "input[type=radio]\n{\ntransform:scale(4);margin:0 0.8em;\n}\n" .
           "optgroup {\nfont-size: 20px;\n}\n" .
           "input[type=checkbox]\n{\ntransform:scale(4)\n}\n</style>\n";
  }
  else {
    return "";
  }
}


// Show the results for a course
function show_results($event, $key, $course, $show_points, $max_points, $path_to_top = "..") {
  $result_string = "";
  $result_string .= "<p>Results on " . ltrim($course, "0..9-") . "\n";

  $results_path = get_results_path($event, $key);
  if (!is_dir("{$results_path}/${course}")) {
    $result_string .= "<p>No Results yet<p><p><p>\n";
    return($result_string);
  }
  $results_list = scandir("{$results_path}/${course}");
  $results_list = array_diff($results_list, array(".", ".."));
  if (count($results_list) == 0) {
    $result_string .= "<p>No Results yet<p><p><p>\n";
    return($result_string);
  }

  if ($show_points) {
    $points_header = "<th>Points</th>";
  }
  else {
    $points_header = "";
  }

  $finish_place = 0;

  $result_string .= "<table border=1><tr><th>Place</th><th>Name</th><th>Time</th>{$points_header}</tr>\n";
  $dnfs = "";
  foreach ($results_list as $this_result) {
    $finish_place++;
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key);
    $competitor_name = file_get_contents("${competitor_path}/name");
    if ($show_points) {
      $points_value = "<td>" . ($max_points - $result_pieces[0]) . "</td>";
    }
    else {
      $points_value = "";
    }

    if (file_exists("./{$competitor_path}/self_reported")) {
      if (file_exists("./{$competitor_path}/dnf")) {
        $dnfs .= "<tr><td>{$finish_place}</td><td>${competitor_name}</td><td>DNF</td>{$points_value}</tr>\n";
      }
      else if (file_exists("./{$competitor_path}/no_time")) {
        $result_string .= "<tr><td>{$finish_place}</td><td>${competitor_name}</td><td>No time</td>{$points_value}</tr>\n";
      }
      else {
        $result_string .= "<tr><td>{$finish_place}</td><td>${competitor_name}</td><td>" . formatted_time($result_pieces[1]) . "</td>{$points_value}</tr>\n";
      }
    }
    else if (!file_exists("./${competitor_path}/dnf")) {
      $result_string .= "<tr><td>{$finish_place}</td><td><a href=\"./show_splits.php?course=${course}&event=${event}&key={$key}&entry=${this_result}\">${competitor_name}</a></td><td>" . formatted_time($result_pieces[1]) . "</td>{$points_value}</tr>\n";
    }
    else {
      // For a scoreO course, there are no DNFs, so $points_value should always be "", but show it just in case
      $dnfs .= "<tr><td>{$finish_place}</td><td><a href=\"./show_splits.php?course=${course}&event=${event}&key={$key}&entry=${this_result}\">${competitor_name}</a></td><td>DNF</td>{$points_value}</tr>\n";
    }
  }
  $result_string .= "${dnfs}</table>\n<p><p><p>";
  return($result_string);
}

// Show the results for a course as a csv
function get_csv_results($event, $key, $course, $show_points, $max_points, $path_to_top = "..") {
  $result_string = "";
  $readable_course_name = ltrim($course, "0..9-");

  // No results yet - .csv is empty
  $results_path = get_results_path($event, $key);
  if (!is_dir("{$results_path}/{$course}")) {
    return("");
  }
  
  $results_list = scandir("${results_path}/{$course}");
  $results_list = array_diff($results_list, array(".", ".."));

  $dnfs = "";
  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key);
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

// Get the results for a course as an array
function get_results_as_array($event, $key, $course, $show_points, $max_points, $path_to_top = "..") {
  $result_array = array();
  $readable_course_name = ltrim($course, "0..9-");

  // No results yet - .csv is empty
  $results_path = get_results_path($event, $key);
  if (!is_dir("{$results_path}/{$course}")) {
    return($result_array);
  }
  
  $results_list = scandir("${results_path}/{$course}");
  $results_list = array_diff($results_list, array(".", ".."));

  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key);
    $competitor_name = file_get_contents("${competitor_path}/name");
    if ($show_points) {
      $points_value = $max_points - $result_pieces[0];
    }
    else {
      $points_value = 0;
    }

    $competitor_result_array = array();
    $competitor_result_array["competitor_id"] = $result_pieces[2];
    $competitor_result_array["competitor_name"] = $competitor_name;
    $competitor_result_array["time"] = csv_formatted_time($result_pieces[1]);
    $competitor_result_array["dnf"] = file_exists("{$competitor_path}/dnf");
    if (file_exists("{$competitor_path}/si_stick")) {
      $competitor_result_array["si_stick"] = file_get_contents("{$competitor_path}/si_stick");
    }
    $competitor_result_array["scoreo_points"] = $points_value;
    $result_array[] = $competitor_result_array;
  }
  return($result_array);
}

function get_all_course_result_links($event, $key, $path_to_top = "..") {
  $courses_path = get_courses_path($event, $key);
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  $links_string = "<p>Show results for ";
  foreach ($course_list as $one_course) {
    if (!file_exists("{$courses_path}/{$one_course}/removed")) {
      $links_string .= "<a href=\"../OMeet/view_results.php?event=${event}&key={$key}&course=$one_course\">" . ltrim($one_course, "0..9-") . "</a> \n";
    }
  }
  $links_string .= "<a href=\"../OMeet/view_results.php?event=${event}&key={$key}\">All</a> \n";

  return($links_string);
}

function get_email_course_result_links($event, $key, $path_to_top = "..") {
  if (isset($_SERVER["HTTPS"])) {
    $proto = "https://";
  }
  else {
    $proto = "http://";
  }
  $base_path_for_links = $proto . $_SERVER["SERVER_NAME"] . dirname(dirname($_SERVER["REQUEST_URI"]));

  $courses_path = get_courses_path($event, $key);
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  $links_string = "<p>Show results for ";
  foreach ($course_list as $one_course) {
    if (!file_exists("{$courses_path}/{$one_course}/removed")) {
      $links_string .= "<a href=\"{$base_path_for_links}/OMeet/view_results.php?event=${event}&key={$key}&course=$one_course\">" . ltrim($one_course, "0..9-") . "</a> \n";
    }
  }
  $links_string .= "<a href=\"{$base_path_for_links}/OMeet/view_results.php?event=${event}&key={$key}\">All</a> \n";

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

  return(isset($keys_hash[$key]));
}

function key_password_ok($key, $password) {
  global $keys_hash;

  // Key must have been checked for validity already
  return(key_is_valid($key) && ($keys_hash[$key][1] == $password));
}

function key_to_path($key) {
  global $keys_hash;

  // Key must have been checked for validity already
  if (key_is_valid($key)) {
    return($keys_hash[$key][0]);
  }

  return "";
}

function get_courses_path($event, $key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key) . "/{$event}/Courses");
}

function get_competitor_directory($event, $key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key) . "/{$event}/Competitors");
}

function get_competitor_path($competitor, $event, $key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key) . "/{$event}/Competitors/{$competitor}");
}

function get_results_path($event, $key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key) . "/{$event}/Results");
}

function get_event_path($event, $key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key) . "/{$event}");
}

function get_base_path($key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key));
}

function get_members_path($key, $member_properties) {
  if (isset($member_properties['member_list_file'])) {
    return("../OMeetData/" . key_to_path($key) . "/{$member_properties['member_list_file']}");
  }
  else if (isset($member_properties['member_list_path'])) {
    return($member_properties['member_list_path']);
  }
  else {
    return("../OMeetData/" . key_to_path($key) . "/members.csv");
  }
}

function get_nicknames_path($key, $member_properties) {
  if (isset($member_properties['nickname_list_file'])) {
    return("../OMeetData/" . key_to_path($key) . "/{$member_properties['nickname_list_file']}");
  }
  else if (isset($member_properties['nickname_list_path'])) {
    return($member_properties['nickname_list_path']);
  }
  else {
    return("../OMeetData/" . key_to_path($key) . "/nicknames.csv");
  }
}

function get_club_name($key, $member_properties) {
  if (isset($member_properties['club_name'])) {
    return($member_properties['club_name']);
  }
  else {
    return("NEOC");
  }
}

function get_extra_prompts_file($key) {
  return("../OMeetData/" . key_to_path($key) . "/extra_prompts.txt");
}

// Get the extra prompts, if any
// Lines beginning with a # are ignored as comments
// Otherwise each prompt is listed on a separate line
function get_extra_prompts($key) {
  $additional_prompts_file = get_extra_prompts_file($key);
  if (file_exists($additional_prompts_file)) {
    $additional_prompts = file($additional_prompts_file);
    $filtered_prompts = array_filter($additional_prompts, function ($line) { return (ltrim($line)[0] != "#"); });
    return(array_values($filtered_prompts));
  }

  // No additional prompts
  return (array());
}

function get_qr_code_html_footer_file($key) {
  return("../OMeetData/" . key_to_path($key) . "/qr_code_footer.html");
}

function get_error_msg_file() {
  return("../site_error_msg.txt");
}

function set_timezone($key) {
  $timezone = "America/New_York";   // Something has to be a default
  $default_timezone = "America/New_York";   // Something has to be a default

  if (file_exists("../OMeetData/" . key_to_path($key) . "/timezone.txt")) {
    $timezone = file_get_contents("../OMeetData/" . key_to_path($key) . "/timezone.txt");
  } 
  else if (file_exists("../timezone.txt")) {
    $timezone = file_get_contents("../timezone.txt");
  }

  if (!date_default_timezone_set($timezone)) {
    date_default_timezone_set($default_timezone);
  }

  return;
}

function use_secure_http_for_qr_codes() {
  return(file_exists("../secure_http"));
}

function redirect_to_secure_http_if_no_key_cookie() {
  return(file_exists("../try_secure_http"));
}

?>
