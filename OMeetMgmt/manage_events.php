<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

function is_event_open($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done"));
}

function is_event_recently_closed($filename) {
  global $base_path, $recent_event_cutoff;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && file_exists("{$base_path}/{$filename}/done") &&
          (stat("{$base_path}/{$filename}/done")["mtime"] > $recent_event_cutoff));
}

function name_to_registration_link($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li>{$event_fullname}<ul><li><a href={$base_path_for_links}/OMeetRegistration/register.php?event=${event_id}&key={$key}>BYOM Registration</a>" .
                                   "<li><a href={$base_path_for_links}/OMeetWithMemberList/competition_register.php?key={$key}&member=1>Member meet Registration</a>" .
                                   "<li><a href={$base_path_for_links}/OMeetWithMemberList/competition_register.php?key={$key}>Non-member meet Registration</a></ul>\n");
}

function name_to_results_link($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href={$base_path_for_links}/OMeet/view_results.php?event={$event_id}&key={$key}>Results for {$event_fullname}</a>" . 
          "<ul><li><a href={$base_path_for_links}/OMeet/on_course.php?event={$event_id}&key={$key}>Still on course</a>" . 
              "<li><a href={$base_path_for_links}/OMeetMgmt/competitor_info.php?event={$event_id}&key={$key}>" .
	                                                                                        "Meet Director view of competitors</a>" .
	      "<li><a href={$base_path_for_links}/OMeetRegistration/self_report_1.php?event={$event_id}&key={$key}>Self report a result</a></ul>\n");
}

function name_to_add_course_link($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href={$base_path_for_links}/OMeetMgmt/add_course_to_event.php?event={$event_id}&key={$key}>Add new course to {$event_fullname}</a> -- (" . 
          "<a href={$base_path_for_links}/OMeetMgmt/create_event.php?clone_event={$event_id}&key={$key}>create a copy of this event</a>)");
}

function name_to_remove_course_link($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href={$base_path_for_links}/OMeetMgmt/remove_course_from_event.php?event={$event_id}&key={$key}>Remove course from {$event_fullname} (or undo prior removal)</a>\n");
}

function name_to_clone_course_link($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href={$base_path_for_links}/OMeetMgmt/create_event.php?clone_event={$event_id}&key={$key}> Create a copy of {$event_fullname}</a>");
}

function name_to_download_links($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li>Download <a href={$base_path_for_links}/OMeetMgmt/download_results_csv.php?event={$event_id}&key={$key}> winsplits </a> / " .
          "<a href={$base_path_for_links}/OMeetMgmt/download_results_iofxml.php?event={$event_id}&key={$key}> IOF XML 3.0 </a> results for {$event_fullname}");
}

function name_to_stats_links($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li>Get stats for <a href={$base_path_for_links}/OMeetMgmt/meet_statistics.php?event={$event_id}&key={$key}> {$event_fullname} </a>");
}

function name_to_get_qrcodes_link($event_id) {
  global $base_path, $key, $base_path_for_links;
  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href={$base_path_for_links}/OMeetMgmt/get_event_qr_codes.php?event={$event_id}&key={$key}> Get QR codes for {$event_fullname}</a>");
}

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key, "..");
if (!is_dir($base_path)) {
  error_and_exit("No event directory found, please contact administrator to create \"{$base_path}\"");
}


if (isset($_GET["server-path-override"])) {
  // Use this override if testing with a server that doesn't work with the normal base_path
  $base_path_for_links = $_GET["server-path-override"];
}
else {
  if (isset($_SERVER["HTTPS"])) {
    $proto = "https://";
    $port = get_secure_http_port_spec();
  }
  else {
    $proto = "http://";
    $port = get_http_port_spec();
  }
  // Make sure that the base path doesn't end in a /, this makes life easier when crafting the links
  $base_path_for_links = $proto . $_SERVER["SERVER_NAME"] . $port . dirname(dirname($_SERVER["REQUEST_URI"]));
  if (substr($base_path_for_links, -1) == "/") {
    $base_path_for_links = substr($base_path_for_links, 0, -1);
  }
}

if (isset($_GET["recent_event_timeout"])) {
  $recent_event_timeout = time_limit_to_seconds($_GET["recent_event_timeout"]);
}
else {
  $recent_event_timeout = 86400 * 30;  // One month cutoff
}

$recent_event_cutoff = time() - $recent_event_timeout;

$event_list = scandir($base_path);
$open_event_list = array_filter($event_list, "is_event_open");
$closed_event_list = array_filter($event_list, "is_event_recently_closed");
$open_event_links = array_map("name_to_registration_link", $open_event_list);
$add_course_links = array_map("name_to_add_course_link", $open_event_list);
$add_course_links2 = array_map("name_to_clone_course_link", $closed_event_list);
$remove_course_links = array_map("name_to_remove_course_link", $open_event_list);
$open_event_result_links = array_map("name_to_results_link", $open_event_list);
$qrcode_links = array_map("name_to_get_qrcodes_link", $open_event_list);
$closed_event_result_links = array_map("name_to_results_link", $closed_event_list);
$closed_event_download_links = array_map("name_to_download_links", $closed_event_list);
$open_event_download_links = array_map("name_to_download_links", $open_event_list);
$closed_event_stats_links = array_map("name_to_stats_links", $closed_event_list);
$open_event_stats_links = array_map("name_to_stats_links", $open_event_list);


echo get_web_page_header(true, false, false);
?>
<br>
<p>Orienteering Event Management
<p>
<ol>
<li> <a href=<?php echo "./create_event.php?key={$key}"; ?>>Create a new event</a>
<li> Manipulate existing events
<?php
  if (count($open_event_list) > 0) {
    echo "<ul>" .  implode("\n", $add_course_links) . "</ul>\n";
  }
  if (count($closed_event_list) > 0) {
    echo "<ul>" .  implode("\n", $add_course_links2) . "</ul>\n";
  }
  if (count($open_event_list) > 0) {
    echo "<ul><li>Remove courses\n<ul>\n" .  implode("\n", $remove_course_links) . "</ul></ul>\n";
  }
?>

<li> Get a registration link: 
<ul>
<?php echo implode("\n", $open_event_links); ?>
</ul>

<li> Get QR codes
<ul>
<?php echo implode("\n", $qrcode_links); ?>
</ul>

<li> <a href=<?php echo "./mass_start.php?key={$key}"; ?>>Mass start an event</a>

<li> View recent results: 
  <ul>
    <li> Current events
      <ul>
      <?php echo implode("\n", $open_event_result_links); ?>
      </ul>
    <li> Recently closed events
      <ul>
      <?php echo implode("\n", $closed_event_result_links); ?>
      </ul>
    <li> Download results
      <ul>
      <?php echo implode("\n", $closed_event_download_links); ?>
      <?php echo implode("\n", $open_event_download_links); ?>
      </ul>
    <li> Statistics
      <ul>
      <?php echo implode("\n", $closed_event_stats_links); ?>
      <?php echo implode("\n", $open_event_stats_links); ?>
      </ul>
  </ul>

<li> <a href=<?php echo "./finish_event.php?key={$key}"; ?>>Finish an event</a>
</ol>

<?php
echo get_web_page_footer();
?>
