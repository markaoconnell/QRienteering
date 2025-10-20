<?php

function ck_testing() {
  if (file_exists("./testing_mode.php")) {
    require "./testing_mode.php";
    artificial_input_file_parse();
  }
}


// Am I running on a mobile device?
function is_mobile () {
	#  return (isset($_SERVER['HTTP_USER_AGENT']) && is_numeric(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "mobile")));
	return false;
}


$page_title = "Orienteering Event Managment";
function set_page_title($new_title) {
  global $page_title;
  $page_title = $new_title;
}

function find_get_key_or_empty_string($parameter_name) {
  return(isset($_GET[$parameter_name]) ? $_GET[$parameter_name] : "");
}


$bg_color = "";
$font_color_override = "";
$redirect = "";

function set_redirect($redirection_string) {
  global $redirect;
  $redirect = $redirection_string;
}

// Print out the default headers
function get_web_page_header($paragraph_style, $table_style, $form_style) {
  global $bg_color, $page_title, $font_color_override, $redirect;

  $headers_to_show = <<<END_OF_HEADERS
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1"
 http-equiv="content-type">
  <title>{$page_title}</title>
  <meta content="Mark O'Connell" name="author">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta charset="utf-8">
  <link rel="stylesheet" href="../OMeetCommon/styles.css"></link>

END_OF_HEADERS;

  if ($redirect != "") {
    $headers_to_show .= $redirect;
  }

  if ($bg_color != "") {
    $headers_to_show .= get_bg_color_element($bg_color);
  }

  if ($font_color_override != "") {
    $headers_to_show .= $font_color_override;
  }

  if ($paragraph_style) {
    $headers_to_show .= get_paragraph_style_header();
  }

  if ($table_style) {
    $headers_to_show .= get_table_style_header();
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
  global $bg_color, $font_color_override;
  $bg_color = "#cc3300";
  $font_color_override = "<style>\np {\ncolor: yellow ;\n}\n";
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
  // new results page styling
  return `<link rel="stylesheet" href="../OMeetCommon/results_page.css"></link>`;
  // old table styling, code will not be reached
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
$key_translation_hash = array();

// For testing purposes only
function key_reset() {
  global $keys_read, $keys_hash;

  $keys_read = false;
  $keys_hash = array();
  $key_translation_hash = array();
}

function read_key_file() {
  global $keys_read, $keys_hash, $key_translation_hash;

  if (!$keys_read) {
    if (file_exists("../keys")) {
      $key_file_lines = file("../keys", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($key_file_lines as $key_line) {
        // Format is key, path, password
        // or xlation_key, real_key  (xlation_key must begin with XLT:)
        $line_pieces = explode(",", $key_line);
        $number_pieces = count($line_pieces);
        if ($number_pieces == 3) {
          $keys_hash[trim($line_pieces[0])] = array(trim($line_pieces[1]), trim($line_pieces[2]));
        }
        else if ($number_pieces == 2) {
          if (substr($line_pieces[0], 0, 4) == "XLT:") {
            $key_translation_hash[substr($line_pieces[0], 4)] = $line_pieces[1];
          }
          else {
          // Do nothing and skip this entry - should really figure out a way to report this somewhere
          }
        }
        else {
          // Do nothing and skip this entry - should really figure out a way to report this somewhere
        }
      }

      $keys_read = true;
    }
    else {
      $keys_read = true;
    }
  }

  return;
}

function translate_key($key) {
  global $keys_read, $key_translation_hash;

  if (!$keys_read) {
    read_key_file();
  }

  if (isset($key_translation_hash[$key])) {
    return ($key_translation_hash[$key]);
  }
  else {
    return($key);
  }
}

function key_is_valid($key) {
  global $keys_read, $keys_hash;

  if (!$keys_read) {
    read_key_file();
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

function get_results_per_class_path($event, $key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key) . "/{$event}/ResultsPerClass");
}

function get_stick_xlation_path($event, $key) {
  return("../OMeetData/" . key_to_path($key) . "/{$event}/StickXlations");
}

function get_stick_xlation($event, $key, $stick) {
  $event_path = get_event_path($event, $key);
  if (file_exists("{$event_path}/StickXlations/{$stick}")) {
    return (file_get_contents("{$event_path}/StickXlations/{$stick}"));
  }
  return("");
}

function put_stick_xlation($event, $key, $competitor_id, $stick) {
  $event_path = get_event_path($event, $key);
  file_put_contents("{$event_path}/StickXlations/{$stick}", $competitor_id);
}

function clear_stick_xlation($event, $key, $stick) {
  $event_path = get_event_path($event, $key);

  if (file_exists("{$event_path}/StickXlations/{$stick}")) {
    unlink("{$event_path}/StickXlations/{$stick}");
  }
}

// Format returned is a hash of course (e.g. 01-White) to list of controls (e.g. 101:60,105:60) meaning control 101 is untimed (max 60s),
// control 105 is also untimed (max 60s), etc.
function get_untimed_controls($event, $key) {
  $untimed_controls = array();
  $event_path = get_event_path($event, $key);
  if (file_exists("{$event_path}/untimed_controls")) {
    $file_contents = file("{$event_path}/untimed_controls", FILE_IGNORE_NEW_LINES);
    array_map(function ($elt) use (&$untimed_controls) { $pieces = explode(";", $elt); $untimed_controls[$pieces[0]] = $pieces[1]; }, $file_contents);
  }

  return($untimed_controls);
}

function put_untimed_controls($event, $key, $untimed_controls) {
  $event_path = get_event_path($event, $key);
  if (count($untimed_controls) > 0) {
    file_put_contents("{$event_path}/untimed_controls",
	  implode("\n", array_map(function ($elt) use ($untimed_controls) { return("{$elt};{$untimed_controls[$elt]}"); }, array_keys($untimed_controls))));
  }
  else {
    if (file_exists("{$event_path}/untimed_controls")) {
      unlink("{$event_path}/untimed_controls");
    }
  }
  return;
}

function untimed_control_entry_to_hash($course_entry) {
  $course_entry_hash = array();
  array_map(function ($elt) use (&$course_entry_hash) { $pieces = explode(":", $elt); $course_entry_hash[$pieces[0]] = $pieces[1]; }, explode(",", $course_entry));
  return($course_entry_hash);
}


function get_event_path($event, $key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key) . "/{$event}");
}

function get_base_path($key, $path_to_top = "..") {
  return("../OMeetData/" . key_to_path($key));
}

function get_control_description($event_path, $control) {
  if (file_exists("{$event_path}/ControlDescriptions/{$control}")) {
    return(file_get_contents("{$event_path}/ControlDescriptions/{$control}"));
  }
  else {
    return("");
  }
}

function get_control_extra_info($event_path, $control) {
  if (file_exists("{$event_path}/ControlExtraInfo/{$control}")) {
    return(file_get_contents("{$event_path}/ControlExtraInfo/{$control}"));
  }
  else {
    return("");
  }
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

function get_default_club_name($key, $member_properties) {
  if (isset($member_properties['club_name']) && ($member_properties["club_name"] != "")) {
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

function get_secure_http_port_spec() {
  if (file_exists("../secure_http_port")) {
    $port_spec_file_lines = file("../secure_http_port", FILE_IGNORE_NEW_LINES);
    return (":" . $port_spec_file_lines[0]);
  }
  else {
    return ("");
  }
}

function get_http_port_spec() {
  if (file_exists("../http_port")) {
    $port_spec_file_lines = file("../http_port", FILE_IGNORE_NEW_LINES);
    return (":" . $port_spec_file_lines[0]);
  }
  else {
    return ("");
  }
}

function redirect_to_secure_http_if_no_key_cookie() {
  return(file_exists("../try_secure_http"));
}
?>
