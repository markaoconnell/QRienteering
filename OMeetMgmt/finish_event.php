<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

// Get some phpinformation, just in case
// Verify that php is running properly
// echo 'Current PHP version: ' . phpversion();
// phpinfo();

function is_event($filename) {
  return (is_dir($filename) && (substr($filename, -5) == "Event"));
}

function name_to_link($pathname) {
  $final_element = basename($pathname);
  return ("<li><a href=./finish_event.php?event=${final_element}>${final_element}</a>\n");
}

echo "<p>\n";

$event_name = $_GET["event"];
$key = $_GET["key"];

if (!key_is_valid($key)) {
  error_and_exit("Unknown management key \"$key\", are you using an authorized link?\n");
}

if (strcmp($event_name, "") == 0) {
  $event_list = scandir(get_base_path($key, ".."));
  $event_list = array_filter($event_list, is_event);
  $event_output_array = array_map(name_to_link, $event_list);
  echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
}
else {
  if (substr($event_name, -5) == ".done") {
    echo "<p>Event {$event_name} already completed.";
  }
  else {
    $event_path = get_event_path($event_name, $key, "..");

    rename("{$event_path}", "{$event_path}.done");
    echo "<p>Event {$event_name} completed.";
  }
}

echo get_web_page_footer();
?>
