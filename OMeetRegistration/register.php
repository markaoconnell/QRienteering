<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

// Try setting a cookie here and then reading it later during registration.
// If this doesn't work, then something is wrong with the cookie support on the
// phone or device and the rest of control scanning won't work either
// But at least we can show a good error message early
// One hour timeout should be plenty to just complete registration
$cookie_path = dirname(dirname($_SERVER["REQUEST_URI"]));
setcookie("testing_cookie_support", "can this be read?", time() + 3600, $cookie_path);

echo get_web_page_header(true, false, true);


// Get some phpinformation, just in case
// Verify that php is running properly
// echo 'Current PHP version: ' . phpversion();
// phpinfo();

function is_event($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("${base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function name_to_link($event_id) {
  global $raw_registration_info, $registration_info_supplied, $show_reregister_link, $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  $show_reregister_string = $show_reregister_link ? "&show_reregister_link=1" : "";

  if (!$registration_info_supplied) {
    return ("<li><a href=./register.php?event={$event_id}&key={$key}{$show_reregister_string}>{$event_fullname}</a>\n");
  }
  else {
    return ("<li><a href=./register.php?event={$event_id}&key={$key}{$show_reregister_string}&registration_info={$raw_registration_info}>{$event_fullname}</a>\n");
  }
}

echo "<p>\n";

$default_name = "";
$default_email = "";
if (isset($_GET["registration_info"])) {
  $registration_info_supplied = true;
  $raw_registration_info = $_GET["registration_info"];
  $registration_info = parse_registration_info($raw_registration_info);
}
else {
  // See if there is a cookie about the byom registration remembered on the phone
  $registration_info_supplied = false;
  $byom_registration_info = isset($_COOKIE["byom_registration_info"]) ? $_COOKIE["byom_registration_info"] : "";
  if ($byom_registration_info != "") {
    $byom_registration_pieces = explode(",", $byom_registration_info);
    $default_name = base64_decode($byom_registration_pieces[0]);
    $default_email = base64_decode($byom_registration_pieces[1]);
  }
}

$event = isset($_GET["event"]) ? $_GET["event"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
// Only translate the key if no event is specified - otherwise the key should be correct already
if ($event == "") {
    $key = translate_key($key);
}
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key, "..");
$show_reregister_link = isset($_GET["show_reregister_link"]);

//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
if (strcmp($event, "") == 0) {
  $event_list = scandir($base_path);
  //print_r($event_list);
  $event_list = array_filter($event_list, "is_event");
  //print_r($event_list);
  if (count($event_list) == 1) {
    $event = basename(current($event_list));
    //echo "Identified event as ${event}\n<p>";
  }
  else if (count($event_list) > 1) {
    $event_output_array = array_map("name_to_link", $event_list);
    echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
    return;
  }
  else {
    echo "<p>No available events.\n";
    return;
  }
}

if (file_exists("{$base_path}/{$event}/done")) {
  error_and_exit("Event " . file_get_contents("{$base_path}/{$event}/description") . " has completed and registrations are no longer possible.\n");
}

$courses_path = get_courses_path($event, $key);
$courses_array = scandir($courses_path);
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
// print_r($courses_array);
echo "<p>\n";

echo "<p>Registration for orienteering event: " . file_get_contents("{$base_path}/{$event}/description") . "\n<br>";
echo "<form action=\"./register_competitor.php\">\n";

if ($registration_info_supplied) {
  $escaped_name = htmlentities("{$registration_info["first_name"]} {$registration_info["last_name"]}", ENT_QUOTES, 'iso8859-1');
  echo "<br><p>Welcome:<br>\n";
  echo "<input type=\"text\" name=\"competitor_name\" value=\"{$escaped_name}\" readonly><br>\n";
  echo "<input type=\"hidden\" name=\"registration_info\" value=\"{$raw_registration_info}\">\n";
}
else {
  echo "<br><p>What is your name?<br>\n";
  echo "<input type=\"text\" size=30 name=\"competitor_name\" value=\"{$default_name}\"><br>\n";
}

$additional_prompts = get_extra_prompts($key);
if (count($additional_prompts) > 0) {
  for ($i = 0; $i < count($additional_prompts); $i++) {
    echo "<br><p>{$additional_prompts[$i]}<br>\n";
    echo "<input type=\"text\" size=30 name=\"extra-{$i}\"><br>\n";
  }
}

echo "<input type=\"hidden\" name=\"event\" value=\"{$event}\">\n";
echo "<input type=\"hidden\" name=\"key\" value=\"{$key}\">\n";
if ($show_reregister_link) {
  echo "<input type=\"hidden\" name=\"show_reregister_link\" value=\"1\">\n";
}


$preselected_course = isset($_GET["course"]) ? $_GET["course"] : "";
$parseable_result_string = "\n<!--\n";
echo "<br><p>Select a course:<br>\n";
foreach ($courses_array as $course_name) {
  if (!file_exists("{$courses_path}/{$course_name}/removed") && !file_exists("{$courses_path}/{$course_name}/no_registrations")) {
    $prechecked_value = (ltrim($course_name, "0..9-") == $preselected_course) ? "checked" : "";
    // Decide at some point if this should specify the full name or just the human readable name
    // For now, only support the human readable name for simplicity
    // Not sure what this will do if there are multiple courses with the same name that wind up checked,
    // though that would be confusing anyway so I'm not sure it is worth worrying about
    #if ($prechecked_value == "") {
    #  $prechecked_value = ($course_name == $preselected_course) ? "checked" : "";
    #}
    echo "<p><input type=\"radio\" name=\"course\" value=\"{$course_name}\" {$prechecked_value}>" . ltrim($course_name, "0..9-") . " <br>\n";
    $parseable_result_string .= "\n####,COURSE," . ltrim($course_name, "0..9-") . ",{$course_name}\n";
  }
}

if (!$registration_info_supplied) {
  $email_properties = get_email_properties(get_base_path($key, ".."));
  $email_enabled = isset($email_properties["from"]) && isset($email_properties["reply-to"]);
  if ($email_enabled) {
    echo "<br><p>If you would like your results emailed to you, please supply a valid email (optional):<br>\n";
    echo "<input type=\"text\" size=50 name=\"email_address\" value=\"{$default_email}\"><br>\n";
  }
}

// If we are using NRE classes in a BYOM, this is where we would prompt for birth_year and gender
// I actually can't imagine we'll ever do this, but, then again, I could never have imagined
// that we'd use QRienteering for a local club NRE with reporting based on OUSA classes, so
// never say never...
if ((event_is_using_nre_classes($event, $key)) && !$registration_info_supplied) {
  echo "<br><br><p>If you would like your time to count for national ranking purposes, please enter your birth year and gender.\n";
  echo "<p>Please leave blank if you are orienteering recreationally or going out in a group (more than 1 person).\n";

  echo "<p>(Optional) Birth year (for ranking purposes), please use 4 digits, e.g. 1973, 2001, etc.<br>\n";
  $presupplied_birth_year = "value=\"\"";
  echo "<input type=\"text\" size=50 name=\"birth_year\" {$presupplied_birth_year} ><br><br>\n";

  $male_checked = "";
  $female_checked = "";
  $other_checked = "";
  echo "<p>(Optional) Gender (for ranking purposes): <br>";
  echo "<input type=radio name=\"gender\" value=\"f\" {$male_checked} >  Female<br>\n";
  echo "<input type=radio name=\"gender\" value=\"m\" {$female_checked} >  Male<br>\n";
  echo "<input type=radio name=\"gender\" value=\"o\" {$other_checked} >  Other<br>\n";
}


echo "<p><input type=\"submit\" value=\"Submit Registration\">\n";
echo "</form>";
echo "{$parseable_result_string}-->\n";


echo get_web_page_footer();
?>
