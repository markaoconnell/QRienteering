<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require 'name_matcher.php';
require 'preregistration_routines.php';

ck_testing();

echo get_web_page_header(true, false, true);

$key = isset($_GET["key"]) ? $_GET["key"] : "invalid";
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key, "..");
$event = isset($_GET["event"]) ? $_GET["event"] : "invalid";

if (!file_exists("{$base_path}/{$event}")) {
  error_and_exit("Event \"{$event}\" does not appear valid, is this a valid link?\n");
}

$event_name = file_get_contents("{$base_path}/{$event}/description");

if (file_exists("{$base_path}/{$event}/done")) {
  error_and_exit("Event {$event_name} has completed and preregistrations are no longer possible.\n");
}

if (!preregistrations_allowed($event, $key)) {
  error_and_exit("Event {$event_name} is not available for preregistration.\n");
}

$courses_path = get_courses_path($event, $key);
$courses_array = scandir($courses_path);
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries


$current_time = time();
$num_remembered_entries = 0;
$remembered_entries_string = "";
if (isset($_COOKIE["{$key}-preregistrations"])) {
  // Format will be base64(first_name:last_name:stick:member_id:timestamp_of_last_registration),base64(first_name:last_name:stick:member_id:timestamp_of_last_registration),...
  $entries = explode(",", $_COOKIE["{$key}-preregistrations"]);
  $time_cutoff = $current_time - (86400 * 90);  // 3 month window
  foreach ($entries as $preregistered_entrant) {
    $this_entrant = explode(":", base64_decode($preregistered_entrant));
    if ($this_entrant[4] > $time_cutoff) {
      $remembered_entries_string .= "{$this_entrant[0]} {$this_entrant[1]}";  // First and last name
      if ($this_entrant[2] != "") {
        $remembered_entries_string .= " ({$this_entrant[2]})";  // si_stick
      }
      $remembered_entries_string .= "<br>";
      foreach ($courses_array as $this_course) {
        $remembered_entries_string .= "<input type=radio name=\"remembered-{$num_remembered_entries}\" value=\"{$preregistered_entrant} {$this_course}\">\n";
        $remembered_entries_string .= "<label for=\"remembered-{$num_remembered_entries}\">" . ltrim($this_course, "0..9-") . "</label><br>\n";
      }
      $remembered_entries_string .= "<br><br>";
      $num_remembered_entries++;
    }
  }
}

$member_properties = get_member_properties(get_base_path($key));
$club_name = get_club_name($key, $member_properties);

?>
  <p class="title"><u><?php echo "{$club_name} {$event_name}" ?> preregistration:</u>
  <form action="./preregister_competitor.php">
<?php
echo "<input type=hidden name=\"num_remembered_entries\" value=\"{$num_remembered_entries}\">\n";
echo "<input type=hidden name=\"key\" value=\"{$key}\">\n";
echo "<input type=hidden name=\"event\" value=\"{$event}\">\n";
if ($num_remembered_entries > 0) {
  echo "<p>Preregistered previously used competitor(s)?<br>\n";
  echo $remembered_entries_string;
}
?>
  <p>First name 
  <input type="text" name="competitor_first_name"><br>
  <p>Last name 
  <input type="text" name="competitor_last_name"><br>
  <p>Si Unit (optional, can be added later)
  <input type="text" name="competitor_si_stick"><br>
  <p><?php echo $club_name; ?> club member?
  <input type="checkbox" name="competitor_is_member"><br>

<?php
  foreach ($courses_array as $this_course) {
    echo "<input type=radio name=\"competitor_course\" value=\"{$this_course}\">\n";
    echo "<label for=\"competitor_course\">" . ltrim($this_course, "0..9-") . "</label><br>\n";
  }

  echo "<input type=\"submit\" value=\"Preregister\">\n";
  echo "</form>\n";
  
echo get_web_page_footer();
?>
