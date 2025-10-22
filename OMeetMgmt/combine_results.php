<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/course_properties.php';

ck_testing();

set_page_title("Results Predictor setup");

function is_event_open($filename) {
  global $base_path, $key;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done") &&
          event_is_using_nre_classes($filename, $key));
}

function is_event_recently_closed($filename) {
  global $base_path, $recent_event_cutoff, $key;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && file_exists("{$base_path}/{$filename}/done") &&
          (stat("{$base_path}/{$filename}/done")["mtime"] > $recent_event_cutoff) &&
          event_is_using_nre_classes($filename, $key));
}


$key = isset($_GET["key"]) ? $_GET["key"] : "";
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key);
if (!is_dir($base_path)) {
  // Note: This will not create the full directory path, only the bottom directory if it does not exist
  // Not sure if I want to change this or not, I'll leave it alone for the moment
  mkdir($base_path);
  #error_and_exit("No event directory found, please contact administrator to create \"{$base_path}\"");
}


if (isset($_GET["recent_event_timeout"])) {
  $recent_event_timeout = time_limit_to_seconds($_GET["recent_event_timeout"]);
}
else {
  $recent_event_timeout = 86400 * 7;  // Seven day cutoff
}

$recent_event_cutoff = time() - $recent_event_timeout;

$event_list = scandir($base_path);
$open_event_list = array_filter($event_list, "is_event_open");
$closed_event_list = array_filter($event_list, "is_event_recently_closed");


echo get_web_page_header(true, false, false);

?>
<br>
<p>Orienteering Event Results predictor
<p>
<p> Choose the events whose results should be combined
<p> Fill in the boxes for events still in progress to give unfinished competitors a possible
time for the course - the award time minus their start time (if known), minus the last start
otherwise.  This allows seeing if any outstanding runners <strong>could</strong> be eligible for an award.
Format times as hh:mm, make sure to use 24 hour time (e.g. 13:45, not 1:45, which is 1:45 AM!)
<form action="./combine_results_2.php">
<?php
echo "<input type=hidden name=\"key\" value=\"{$key}\">\n";
echo "<ul>\n";
echo implode("\n", array_map(function ($elt) use ($base_path, $key)
                             { return (
	                       "<li> <input type=checkbox name=\"{$elt}\" value=1> " . (file_get_contents("{$base_path}/{$elt}/description")) .
			       " - <input type=checkbox name=\"{$elt}_in_progress\" value=1> Still in progress, " .
                               " - Time of awards: <input type=text size=6 name=\"{$elt}_award_time\">, Last start <input type=text size=6 name=\"{$elt}_last_start\">" );  }, 
                             $open_event_list));
echo implode("\n", array_map(function ($elt) use ($base_path, $key)
                             { return (
	                       "<li> <input type=checkbox name=\"{$elt}\" value=1> " . (file_get_contents("{$base_path}/{$elt}/description")) .
			       " - <input type=checkbox name=\"{$elt}_in_progress\" value=1> Still in progress, " .
                               " - Time of awards: <input type=text size=6 name=\"{$elt}_award_time\">, Last start <input type=text size=6 name=\"{$elt}_last_start\">" );  }, 
                             $closed_event_list));

echo "</ul>\n";
echo "<p>Suppress errors? <input type=checkbox name=suppress_errors value=1>\n";
echo "<p><input type=submit>\n</form>\n";


echo get_web_page_footer();
?>
