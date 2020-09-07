<?php
require '../OMeetCommon/common_routines.php';

ck_testing();


$first_name = $_GET["competitor_first_name"];
$last_name = $_GET["competitor_last_name"];
$club_name = $_GET["club_name"];
$si_stick = $_GET["si_stick"];
$key = $_GET["key"];

if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

// Let's do some validations
if ($first_name == "") {
  error_and_exit("Invalid (empty) first name, please go back and enter a valid first name.\n");
}

if ($last_name == "") {
  error_and_exit("Invalid (empty) last name, please go back and enter a valid last name.\n");
}

if ($si_stick != "") {
  if (!preg_match("/^[0-9]+$/", $si_stick)) {
    error_and_exit("Invalid si_stick \"{$si_stick}\", only numbers allowed.  Please go back and re-enter.\n");
  }
}

echo get_web_page_header(true, false, true);

echo "<p class=title><u>Safety information</u>\n";
echo "<form action=\"./non_member_with_safety_info.php\">\n";
echo "<input type=hidden name=\"competitor_first_name\" value=\"{$first_name}\">\n";
echo "<input type=hidden name=\"competitor_last_name\" value=\"{$last_name}\">\n";
echo "<input type=hidden name=\"club_name\" value=\"{$club_name}\">\n";
echo "<input type=hidden name=\"si_stick\" value=\"{$si_stick}\">\n";
echo "<input type=hidden name=\"key\" value=\"{$key}\">\n";


$base_path = get_base_path($key, "..");
if (file_exists("{$base_path}/waiver_link")) {
  $waiver_link = file_get_contents("{$base_path}/waiver_link");
  echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) I have read and agreed to <a href=\"{$waiver_link}\">the waiver</a></strong><br>\n";
}
else {
  echo "<p><strong><input type=checkbox name=\"waiver_signed\" value=\"signed\">  (Required) I am participating of my own accord and hold the organizers harmless for any injury sustained.</strong><br>\n";
}
?>

<p>It is important that you scan finish at the end of your course so that we know you are safely off the course.
<p>In case there is any question if you have (or have not) safely returned, we need a way to contact you to verify your safety.
<p>This information is maintained while you are on the course and destroyed when you finish the course.

<br><p>(Best option) Your cell phone number, or a parent's, spouse's, etc.<br>
<input type="text" name="cell_number"><br>
<br><p>What car (make/model/plate) did you come in (we can check the lot to see if you've left)?<br>
<input type="text" name="car_info"><br>

<p>
<br><p>(Optional) If you would like results emailed to you, please supply a valid email<br>
<input type="text" name="email"><br><br>

<input type="submit" value="Choose course">
</form>

<?php
echo get_web_page_footer();
?>
