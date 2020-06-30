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
$success_string .= "<p>Welcome {$printable_name}.\n";
$success_string .= <<<END_OF_FORM
<form action="./finalize_member_registration.php">
<input type=hidden name="member_id" value="{$member_id}"/>
<input type=hidden name="key" value="{$key}"/>
<p> Is your name correct, and are you using your SI stick {$si_stick} today?<br>
<p> Yes <input type=radio name="using_stick" value="yes" checked /> <input type=text name="si_stick_number" value="{$si_stick}" readonly/>
<p> No <input type=radio name="using_stick" value="no" />
<p><input type="submit" value="Register"/>
<p>To change the SI stick number you will use, go back and register by name rather than by SI stick.
</form>
END_OF_FORM;


echo get_web_page_header(true, false, true);

echo $success_string;

echo get_web_page_footer();
?>
