<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$time_and_competitor = $_GET["entry"];
$event = $_GET["event"];
$key = $_GET["key"];

if (($event == "") || (!key_is_valid($key))) {
  error_and_exit("Empty event \"{$event}\" or bad location key \"{$key}\", is this an unauthorized link?\n");
}

set_timezone($key);

$result_pieces = explode(",", $time_and_competitor);
$competitor_id = $result_pieces[2];


$competitor_path = get_competitor_path($competitor_id, $event, $key, ".."); 

if (!is_dir($competitor_path)) {
  error_and_exit("Cannot find competitor \"{$competitor_id}\" for {$event} and {$key}, please check that this is an authorized link.\n");
}

$splits_output = "";
if (file_exists("{$competitor_path}/dnf")) {
  $output_array = get_splits_dnf($competitor_id, $event, $key);
  if ($output_array["output"] != "") {
    $splits_output = $output_array["output"];
  }

  if ($output_array["error"] != "") {
    $splits_output .= $output_array["error"];
  }
}
else {
  $splits_output = get_splits_output($competitor_id, $event, $key, $time_and_competitor);
}

echo get_web_page_header(true, true, false);

echo $splits_output;

echo get_web_page_footer();
?>
