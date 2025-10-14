<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';

ck_testing();

set_page_title("Toggle Award Eligibility");

// Get the submitted info
// echo "<p>\n";
$event = isset($_GET["event"]) ? $_GET["event"] : "";
$key = isset($_GET["key"]) ? $_GET["key"] : "";
$competitor = isset($_GET["competitor"]) ? $_GET["competitor"] : "";

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, cannot toggle award eligibility.\n");
}

if ($competitor == "") {
  error_and_exit("<p>ERROR: Competitor not specified, cannot toggle award eligibilityn");
}

$competitor_path = get_competitor_path($competitor, $event, $key);
if (!is_dir($competitor_path)) {
  error_and_exit("<p>ERROR: No such competitor found {$competitor} (possibly already removed or edited?).\n");
}

if (!event_is_using_nre_classes($event, $key)) {
  error_and_exit("<p>ERROR: Event is not using NRE classes.\n");
}

if (file_exists("{$competitor_path}/award_ineligible")) {
  unlink("{$competitor_path}/award_ineligible");
  $award_eligibility = "y";
}
else {
  file_put_contents("{$competitor_path}/award_ineligible", "");
  $award_eligibility = "n";
}

echo get_web_page_header(true, true, false);

echo "<p>Competitor " . file_get_contents("{$competitor_path}/name") . " is now " . (($award_eligibility == "y") ? "eligible" : "<strong>ineligible</strong>") . " for an award.\n";

echo "<p><p><p><a href=\"../OMeetMgmt/competitor_info.php?key={$key}&event={$event}\">Return to main competitor info page</a>\n";

echo get_web_page_footer();
?>
