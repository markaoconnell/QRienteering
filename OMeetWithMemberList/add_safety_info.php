<?php
require '../OMeetCommon/common_routines.php';

ck_testing();


$first_name = "";
$last_name = "";
$club_name = "";
$si_stick = "";
$is_member = isset($_GET["member_id"]);
$member_id = $_GET["member_id"];
$key = $_GET["key"];

if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

if ($is_member) {
  if (!isset($_GET["using_stick"])) {
    error_and_exit("No value found for SI unit usage - error in scripting?  Please restart registration.\n");
  }
  
  $using_stick_value = $_GET["using_stick"];
  if (($using_stick_value != "yes") && ($using_stick_value != "no")) {
    error_and_exit("Invalid value \"{$using_stick_value}\" for SI unit usage.  Please restart registration.\n");
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
if ($is_member) {
  echo "<input type=hidden name=\"member_id\" value=\"{$member_id}\">\n";
}
else {
  echo "<input type=hidden name=\"competitor_first_name\" value=\"{$first_name}\">\n";
  echo "<input type=hidden name=\"competitor_last_name\" value=\"{$last_name}\">\n";
  echo "<input type=hidden name=\"club_name\" value=\"{$club_name}\">\n";
}
echo "<input type=hidden name=\"si_stick\" value=\"{$si_stick}\">\n";
echo "<input type=hidden name=\"key\" value=\"{$key}\">\n";


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
?>

<p>It is important that you scan finish at the end of your course so that we know you are safely off the course.
<p>In case there is any question if you have (or have not) safely returned, we need a way to contact you to verify your safety.
<p>This information is maintained while you are on the course and destroyed when you finish the course.

<br><p>(Best option) Your cell phone number, or a parent/guardian's, spouse's, etc.<br>
<input type="text" size=50 name="cell_number"><br>
<br><p>What car (make/model/plate) did you come in (we can check the lot to see if you've left)?<br>
<input type="text" size=50 name="car_info"><br>

<p>
<?php
$presupplied_value = "";
if ($is_member) {
  $member_email = $_GET["member_email"];
  if ($member_email != "") {
    $presupplied_value = "value=\"{$member_email}\"";
  }
}

echo "<br><p>(Optional) If you would like results emailed to you, please supply a valid email address<br>\n";
echo "<input type=\"text\" size=50 name=\"email\" {$presupplied_value} ><br><br>\n";
?>

<input type="submit" value="Choose course">
</form>

<?php
echo get_web_page_footer();
?>
