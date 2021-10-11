<?php
require '../OMeetCommon/common_routines.php';
require 'preregistration_routines.php';

ck_testing();


$first_name = "";
$last_name = "";
$club_name = "";
$si_stick = "";
$has_preset_id = isset($_GET["member_id"]);
$member_id = $_GET["member_id"];
$key = $_GET["key"];
$event = isset($_GET["event"]) ? $_GET["event"] : "";

if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$is_preregistered_checkin = isset($_GET["checkin"]) && ($_GET["checkin"] == "true");

$saved_registration_info = array();
if (isset($_COOKIE["{$key}-safety_info"])) {
  $saved_registration_info = parse_registration_info($_COOKIE["{$key}-safety_info"]);
}

$stick_override_msg = "";
if ($has_preset_id) {
  if (!isset($_GET["using_stick"])) {
    error_and_exit("No value found for SI unit usage - error in scripting?  Please restart registration.\n");
  }
  
  $using_stick_value = $_GET["using_stick"];
  if (($using_stick_value != "yes") && ($using_stick_value != "no")) {
    error_and_exit("Invalid value \"{$using_stick_value}\" for SI unit usage.  Please restart registration.\n");
  }

  if (isset($_GET["si_stick_number"]) && ($_GET["si_stick_number"] != "") && ($using_stick_value == "no") && !isset($_GET["registered_si_stick"])) {
    $stick_override_msg = "<p class=title style=\"color:red;\"> <strong>SI unit number \"{$_GET["si_stick_number"]}\" entered but QR orienteering selected.\n";
    $stick_override_msg .= "<br>Overriding and using SI unit orienteering.\n";
    $stick_override_msg .= "<br>If this is wrong, please go back and restart registration and make sure that the SI unit field is blank.\n";
    $stick_override_msg .= "</strong><br><br><br><br>\n";
    $using_stick_value = "yes";
  }
  
  if ($using_stick_value == "yes") {
    if (!isset($_GET["si_stick_number"])) {
      error_and_exit("Yes specified for SI unit usage but no SI unit number found.  Please restart registration.\n");
    }
    $si_stick = $_GET["si_stick_number"];

    if ($si_stick == "") {
      error_and_exit("Yes specified for SI unit usage but SI unit number was blank.  Please restart registration.\n");
    }
  }
}
else {
  $first_name = $_GET["competitor_first_name"];
  $last_name = $_GET["competitor_last_name"];
  $club_name = $_GET["club_name"];
  $si_stick = $_GET["si_stick"];

  // Let's do some validations
  if ($first_name == "") {
    error_and_exit("Invalid (empty) first name, please go back and enter a valid first name.\n");
  }
  
  if ($last_name == "") {
    error_and_exit("Invalid (empty) last name, please go back and enter a valid last name.\n");
  }
}

if ($si_stick != "") {
  if (!preg_match("/^[0-9]+$/", $si_stick)) {
    error_and_exit("Invalid si unit id \"{$si_stick}\", only numbers allowed.  Please go back and re-enter.\n");
  }
}

echo get_web_page_header(true, false, true);

echo "<p class=title><u>Safety information</u>\n";
echo "<form action=\"./finalize_registration.php\">\n";
if ($is_preregistered_checkin) {
  echo "<input type=hidden name=\"checkin\" value=\"true\">\n";
  echo "<input type=hidden name=\"event\" value=\"{$event}\">\n";
}

if ($has_preset_id) {
  echo "<input type=hidden name=\"member_id\" value=\"{$member_id}\">\n";
}
else {
  echo "<input type=hidden name=\"competitor_first_name\" value=\"{$first_name}\">\n";
  echo "<input type=hidden name=\"competitor_last_name\" value=\"{$last_name}\">\n";
  echo "<input type=hidden name=\"club_name\" value=\"{$club_name}\">\n";
}
echo "<input type=hidden name=\"si_stick\" value=\"{$si_stick}\">\n";
echo "<input type=hidden name=\"key\" value=\"{$key}\">\n";


// Warn the user if they entered a SI unit number but selected QR code orienteering
if ($stick_override_msg != "") {
  echo $stick_override_msg;
}

if ($is_preregistered_checkin) {
  $entrant_info_path = get_preregistered_entrant($member_id, $event, $key);
  $entrant_info = decode_preregistered_entrant($entrant_info_path);
  $is_member = ($entrant_info["member_id"] != "not_a_member");
}
else {
  $is_member = $has_preset_id;
}

$base_path = get_base_path($key, "..");
if ($is_member) {
  if (file_exists("{$base_path}/member_waiver")) {
    $waiver_html = file_get_contents("{$base_path}/member_waiver");
    echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) {$waiver_html}</strong><br>\n";
  }
  else {
    echo "<p><input type=hidden name=\"waiver_signed\" value=\"signed\"><br>";
  }
}
else {
  if (file_exists("{$base_path}/non_member_waiver")) {
    $waiver_html = file_get_contents("{$base_path}/non_member_waiver");
    echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) {$waiver_html}</strong><br>\n";
  }
  else {
    echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) I am participating of my own accord and hold the organizers harmless for any injury sustained.</strong><br>\n";
  }
}

if (isset($saved_registration_info["cell_phone"])) {
  $presupplied_cell_phone = "value=\"{$saved_registration_info["cell_phone"]}\"";
}
else {
  $presupplied_cell_phone = "";
}

if (isset($saved_registration_info["car_info"])) {
  $presupplied_car_info = "value=\"{$saved_registration_info["car_info"]}\"";
}
else {
  $presupplied_car_info = "";
}

if (isset($saved_registration_info["email_address"]) && ($saved_registration_info["email_address"] != "")) {
  $presupplied_email_address = "value=\"{$saved_registration_info["email_address"]}\"";
}
else {
  $presupplied_email_address = "";
}

?>

<p>It is important that you scan finish at the end of your course so that we know you are safely off the course.
<p>In case there is any question if you have (or have not) safely returned, we need a way to contact you to verify your safety.
<p>This information is maintained while you are on the course and destroyed when you finish the course.

<br><p>(Best option) Your cell phone number, or a parent/guardian's, spouse's, etc.<br>
<input type="text" size=50 name="cell_number" <?php echo $presupplied_cell_phone; ?>><br>
<br><p>What car (make/model/plate) did you come in (we can check the lot to see if you've left)?<br>
<input type="text" size=50 name="car_info" <?php echo $presupplied_car_info; ?>><br>

<p>
<?php
// If the member has a registered email, use this rather than the last email entered
// Since different members may register on the same phone
if ($is_member) {
  $member_email = isset($_GET["member_email"]) ? $_GET["member_email"] : "";
  if ($member_email != "") {
    $presupplied_email_address = "value=\"{$member_email}\"";
  }
}

echo "<br><p>(Optional) If you would like results emailed to you, please supply a valid email address<br>\n";
echo "<input type=\"text\" size=50 name=\"email\" {$presupplied_email_address} ><br><br>\n";
?>

<input type="submit" value="Choose course">
</form>

<?php
echo get_web_page_footer();
?>
