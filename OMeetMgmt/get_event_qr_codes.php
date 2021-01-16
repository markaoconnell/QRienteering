<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_POST["verbose"]);
$cloning_event = false;

require '../OMeetCommon/course_properties.php';
require '../OMeetMgmt/event_mgmt_common.php';

$event_created = false;
$found_error = false;

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key, ".."))) {
  error_and_exit("No event directory found, please contact administrator to create \"{$base_path}\"");
}

$event = $_GET["event"];

$existing_event_path = get_event_path($event, $key, "..");
if (!is_dir($existing_event_path)) {
  error_and_exit("Event not found, is \"{$key}\" and \"{$event}\" a valid pair?\n");
}

$checked_by_default = "";
if (isset($_GET["select-all"])) {
  $checked_by_default = "checked";
  $select_button_label = "Deselect all";
  $select_button_name = "deselect-all";
}
else {
  $checked_by_default = "";
  $select_button_label = "Select all";
  $select_button_name = "select-all";
}

$existing_event_name = file_get_contents("{$existing_event_path}/description");
$path_to_courses = get_courses_path($event, $key, "..");
$current_courses = scandir($path_to_courses);
$current_courses = array_diff($current_courses, array(".", ".."));

$existing_event_description_string = "";
$all_controls = array();
foreach ($current_courses as $this_course) {
  $control_list = read_controls("{$path_to_courses}/{$this_course}/controls.txt");
  array_map(function ($elt) use (&$all_controls) { $all_controls[$elt[0]] = 1; }, $control_list);
  }

if (isset($_SERVER["HTTPS"])) {
  $proto = "https://";
}
else {
  $proto = "http://";
}
$url_prefix = $proto . $_SERVER["SERVER_NAME"] . dirname(dirname($_SERVER["REQUEST_URI"]));
while (substr($url_prefix, -1) == "/") {
  $url_prefix = substr($url_prefix, 0, -1);
}

$add_key_string = "key={$key}";
$add_event_string = "event={$event}";
//echo "<p>Server URI is: " . $_SERVER["REQUEST_URI"] . "\n";
//echo "<p>Server URI dirname is: " . dirname($_SERVER["REQUEST_URI"]) . "\n";
//echo "<p>Server URI dirname and rel path is: " . dirname($_SERVER["REQUEST_URI"]) . "/../OMeetRegistration/register.php" . "\n";
//echo "<p>Service URI after realpath is " . dirname(dirname($_SERVER["REQUEST_URI"])) . "/OMeetRegistration/register.php" . "\n";
//echo "<p>Server name is " . $_SERVER["SERVER_NAME"] . "\n";

?>
<br>
<p class="title">Generate QR codes for <?php  echo $existing_event_name; ?>.<br>
<form action="./get_event_qr_codes.php" method=get>
<input type=hidden name=key value="<?php echo $key; ?>">
<input type=hidden name=event value="<?php echo $event; ?>">
<input type=submit name="<?php echo $select_button_name; ?>" value="<?php echo $select_button_label; ?>">
</form>

<form action="./create_qr_codes.php" method=post enctype="multipart/form-data" >
<input type=hidden name=key value="<?php echo $key; ?>">
<input type=hidden name=event value="<?php echo $event; ?>">
<p><p>
<?php
echo "<ul>\n";
echo "<li><strong>Non-reusable registration</strong> (for non-organized meet events (Bring Your Own Map))\n";
echo "<ul>" .
        "<li><input type=checkbox checked name=\"qr-" . base64_encode("BYOM registration") .
                          "\" value=\"{$url_prefix}/OMeetRegistration/register.php?{$add_key_string}&{$add_event_string}\">BYOM registration" .
     "</ul>\n";
echo "<li><strong>Registration QR codes</strong> (for ogranized meets, reusable at different venues)\n";
echo "<ul>\n";
echo "<li><input type=checkbox {$checked_by_default} name=\"qr-" . base64_encode("Non-Member registration") .
                          "\" value=\"{$url_prefix}/OMeetWithMemberList/competition_register.php?{$add_key_string}\">Non member registration\n";
echo "<li><input type=checkbox {$checked_by_default} name=\"qr-" . base64_encode("Member registration") .
                          "\" value=\"{$url_prefix}/OMeetWithMemberList/competition_register.php?member=1&{$add_key_string}\">Member registration\n";
echo "</ul>\n";
echo "<li><strong>On-course QR codes</strong> (resuable across events/courses)\n";
echo "<ul>\n";
echo "<li><input type=checkbox {$checked_by_default} name=\"qr-" . base64_encode("Start") . "\" value=\"{$url_prefix}/OMeet/start_course.php\">Start\n";
echo "<li><input type=checkbox {$checked_by_default} name=\"qr-" . base64_encode("Finish") . "\" value=\"{$url_prefix}/OMeet/finish_course.php\">Finish\n";
$sorted_control_list = array_keys($all_controls);
sort($sorted_control_list);
foreach ($sorted_control_list as $one_control) {
  echo "<li><input type=checkbox {$checked_by_default} name=\"qr-" . base64_encode("Control {$one_control}") .
                        "\" value=\"{$url_prefix}/OMeet/reach_control.php?control={$one_control}\">Control {$one_control}\n";
}
echo "</ul>\n";
echo "<li><strong>Result QR codes</strong> (non-resuable across events)\n";
echo "<ul>\n";
echo "<li><input type=checkbox {$checked_by_default} name=\"qr-" . base64_encode("View results") .
                          "\" value=\"{$url_prefix}/OMeet/view_results.php?{$add_key_string}&{$add_event_string}\">View results of the event\n";
echo "<li><input type=checkbox {$checked_by_default} name=\"qr-" . base64_encode("Competitors still running") .
                          "\" value=\"{$url_prefix}/OMeet/on_course.php?{$add_key_string}&{$add_event_string}\">View competitors still on the course\n";
echo "</ul>\n";
echo "</ul>\n";
?>
<p>
<input type=radio name="style" value="zipfile">Zipfile
<input type=radio name="style" value="html" checked>Web page
<p><p>
<p><p>
<input name="submit" type="submit">
</form>

<?php
echo get_web_page_footer();
?>
