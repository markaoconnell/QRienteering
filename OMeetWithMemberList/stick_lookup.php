<?php
require '../OMeetCommon/common_routines.php';
require 'name_matcher.php';

ck_testing();

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$matching_info = read_names_info(get_members_path($key, ".."), get_nicknames_path($key, ".."));

if (!isset($_GET["si_stick"])) {
  error_and_exit("Unspecified SI stick number, please hit back and retry.\n");
}

$si_stick = $_GET["si_stick"];

$member_id = get_by_si_stick($si_stick, $matching_info);
if ($member_id == "") {
  error_and_exit("No member with SI stick \"{$si_stick}\" found, please hit back and retry.\n");
}

$error_string = "";
$success_string = "";

$printable_name = get_full_name($member_id, $matching_info);
$email_address = get_member_email($member_id, $matching_info);
$success_string .= "<p>Welcome {$printable_name}.\n";
$success_string .= <<<END_OF_FORM
<form action="./add_safety_info.php">
<input type=hidden name="member_id" value="{$member_id}"/>
<input type=hidden name="member_email" value="{$email_address}"/>
<input type=hidden name="key" value="{$key}"/>
<p> How are you orienteering today? <br>
<p> Using Si Stick <input type=radio name="using_stick" value="yes" checked /> <input type=text name="si_stick_number" value="{$si_stick}" readonly/>
<p> Using QR codes <input type=radio name="using_stick" value="no" />
<p><input type="submit" value="Choose course"/>
<p>If you are using a different SI stick, go back and register by name rather than by SI stick.
<p>If your name is wrong, go back and re-register.
</form>
END_OF_FORM;


echo get_web_page_header(true, false, true);

echo $success_string;

echo get_web_page_footer();
?>
