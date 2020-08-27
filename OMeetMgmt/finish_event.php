<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

// Get some phpinformation, just in case
// Verify that php is running properly
// echo 'Current PHP version: ' . phpversion();
// phpinfo();

function is_event_open($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function name_to_link($event_id) {
  global $base_path, $key;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./finish_event.php?event=${event_id}&key={$key}>{$event_fullname}</a>\n");
}

echo "<p>\n";

$event_name = $_GET["event"];
$key = $_GET["key"];

if (!key_is_valid($key)) {
  error_and_exit("Unknown management key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key, "..");

if (strcmp($event_name, "") == 0) {
  $event_list = scandir($base_path);
  $event_list = array_filter($event_list, is_event_open);
  $event_output_array = array_map(name_to_link, $event_list);
  if (count($event_output_array) > 0) {
    echo "<p>Choose your event:<p>\n<ul>\n" . implode("\n", $event_output_array) . "</ul>";
  }
  else {
    echo "<p>No unfinished events found.\n";
  }
}
else {
  $event_path = get_event_path($event_name, $key, "..");
  if (!is_dir($event_path) || !file_exists("{$event_path}/description")) {
    echo "<p>Bad event key specified \"{$event_name}\", please retry.\n";
  }
  else {
    $event_fullname = file_get_contents("{$event_path}/description");
    if (file_exists("{$event_path}/done")) {
      echo "<p>Event {$event_fullname} already completed.";
    }
    else {
      file_put_contents("{$event_path}/done", "");
      echo "<p>Event {$event_fullname} completed.";
    }
  }
}

echo get_web_page_footer();
?>
