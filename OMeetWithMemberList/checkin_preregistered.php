<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require 'preregistration_routines.php';

ck_testing();

echo get_web_page_header(true, false, true);

$key = isset($_GET["key"]) : $_GET["key"] : "";
$key = translate_key($key);
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}


function is_event_preregistration_enabled($filename) {
  global $base_path;
  return ((substr($filename, 0, 6) == "event-") && is_dir("{$base_path}/{$filename}") && !file_exists("{$base_path}/{$filename}/done") &&
          preregistrations_allowed_by_event_path("{$base_path}/{$filename}"));
}

function name_to_link($event_id) {
  global $key, $base_path;

  $event_fullname = file_get_contents("{$base_path}/{$event_id}/description");
  return ("<li><a href=./checkin_preregistered.php?event={$event_id}&key={$key}>{$event_fullname}</a>\n");
}

echo "<p>\n";

$base_path = get_base_path($key, "..");

$event = isset($_GET["event"]) ? $_GET["event"] : "";
//echo "event is \"${event}\"<p>";
//echo "strcmp returns " . strcmp($event, "") . "<p>\n";
if (strcmp($event, "") == 0) {
  $event_list = scandir($base_path);
  //print_r($event_list);
  $event_list = array_filter($event_list, "is_event_preregistration_enabled");
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
    echo "<p>No available events for checkin.\n";
    return;
  }
}

if (file_exists("{$base_path}/{$event}/done")) {
  error_and_exit("Event " . file_get_contents("{$base_path}/{$event}/description") . " has completed and checkins are no longer possible.\n");
}


$member_properties = get_member_properties(get_base_path($key));
$club_name = get_club_name($key, $member_properties);

?>
  <p class="title"><u><?php echo $club_name ?> event checkin (only for preregistered entrants):</u>
  <form action="./name_lookup.php">
  <p>Lookup by preregistered entrant name:
  <p>First name 
  <input type="text" name="competitor_first_name"><br>
  <p>Last name 
  <input type="text" name="competitor_last_name"><br>
  <input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
  <input type="hidden" name="event" <?php echo "value=\"{$event}\""; ?> >
  <input type="hidden" name="checkin" value="true">
  <input type="submit" value="Name lookup">
  </form>
  
  <form action="./stick_lookup.php">
  <br><p><p>Lookup by Si Unit number:
  <input type="text" name="si_stick"><br>
  <input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
  <input type="hidden" name="event" <?php echo "value=\"{$event}\""; ?> >
  <input type="hidden" name="checkin" value="true">
  <input type="submit" value="SI unit lookup">
  </form>

<?php
echo get_web_page_footer();
?>
