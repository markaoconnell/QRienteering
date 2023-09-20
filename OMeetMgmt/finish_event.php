<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/time_routines.php';

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

function is_event_recently_closed($filename) {
  global $base_path, $recent_event_cutoff;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && file_exists("{$base_path}/{$filename}/done") &&
          (stat("{$base_path}/{$filename}/done")["mtime"] > $recent_event_cutoff));
}

function open_event_to_close_link($event_id) {
  global $base_path, $key;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./finish_event.php?event=${event_id}&key={$key}&action=close>{$event_fullname}</a>\n");
}

function closed_event_to_reopen_link($event_id) {
  global $base_path, $key;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./finish_event.php?event=${event_id}&key={$key}&action=reopen>{$event_fullname}</a>\n");
}

echo "<p>\n";

$event_name = $_GET["event"];
$key = $_GET["key"];

if (!key_is_valid($key)) {
  error_and_exit("Unknown management key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key, "..");

if (isset($_GET["recent_event_timeout"])) {
  $recent_event_timeout = time_limit_to_seconds($_GET["recent_event_timeout"]);
}
else {
  $recent_event_timeout = 86400 * 30;  // One month cutoff
}

$recent_event_cutoff = time() - $recent_event_timeout;


if (strcmp($event_name, "") == 0) {
  $event_list = scandir($base_path);
  $open_event_list = array_filter($event_list, "is_event_open");
  $closed_event_list = array_filter($event_list, "is_event_recently_closed");
  $open_event_output_array = array_map("open_event_to_close_link", $open_event_list);
  $closed_event_output_array = array_map("closed_event_to_reopen_link", $closed_event_list);

  if (count($open_event_output_array) > 0) {
    echo "<p>Choose event to close:<p>\n<ul>\n" . implode("\n", $open_event_output_array) . "</ul>";
  }
  else {
    echo "<p>No open events found.\n";
  }

  if (count($closed_event_output_array) > 0) {
    echo "<p>Choose event to reopen:<p>\n<ul>\n" . implode("\n", $closed_event_output_array) . "</ul>";
  }
  else {
    echo "<p>No closed events found.\n";
  }
}
else {
  $event_path = get_event_path($event_name, $key, "..");
  if (!isset($_GET["action"])) {
    error_and_exit("No action (close or reopen) specified, please retry.\n");
  }

  $close_an_event = ($_GET["action"] == "close");
  $open_an_event = ($_GET["action"] == "reopen");

  if (!$close_an_event && !$open_an_event) {
    error_and_exit("Action was neither close nor reopen, please retry.\n");
  }

  if (!is_dir($event_path) || !file_exists("{$event_path}/description")) {
    echo "<p>Bad event key specified \"{$event_name}\", please retry.\n";
  }
  else {
    $event_fullname = file_get_contents("{$event_path}/description");
    if (file_exists("{$event_path}/done")) {
      if ($open_an_event) {
        unlink("{$event_path}/done");
        echo "<p>Event {$event_fullname} has been reopened.";
      }
      else {
        echo "<p>Event {$event_fullname} already closed.";
      }
    }
    else {
      if ($close_an_event) {
        file_put_contents("{$event_path}/done", "");
        echo "<p>Event {$event_fullname} closed.";
      }
      else {
        echo "<p>Event {$event_fullname} already closed.";
      }
    }
  }
}

echo get_web_page_footer();
?>
