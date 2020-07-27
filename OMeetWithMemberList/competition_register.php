<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, true);

$member_cookie_found = false;
if (isset($_COOKIE["member_first_name"]) && isset($_COOKIE["member_last_name"])) {
  $member_cookie_found = true;
}

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

?>
<p class="title"><u>NEOC club member registration:</u>
<form action="./name_lookup.php">
<p>Lookup by member name:
<p>First name 
<input type="text" name="competitor_first_name" <?php if ($member_cookie_found) { echo "value=\"{$_COOKIE["member_first_name"]}\""; } ?> ><br>
<p>Last name 
<input type="text" name="competitor_last_name" <?php if ($member_cookie_found) { echo "value=\"{$_COOKIE["member_last_name"]}\""; } ?> ><br>
<input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
<input type="submit" value="Member name lookup">
</form>

<form action="./stick_lookup.php">
<p>Lookup by Si Stick:
<input type="text" name="si_stick"><br>
<input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
<input type="submit" value="SI stick lookup">
</form>


<br><br><br><p class="title"><u>Non-NEOC club member registration:</u>
<form action="./non_member.php">
<p>What is your name?<br>
<p>First name 
<input type="text" name="competitor_first_name"><br>
<p>Last name 
<input type="text" name="competitor_last_name"><br>
<br><p>What is your orienteering club affiliation (if any)?<br>
<input type="text" name="club_name"><br>
<br><p>What is your SI stick number (if any)?<br>
<input type="text" name="si_stick"><br>
<br><p>What is your email (to send results link)?<br>
<input type="text" name="email"><br>
<br><p>What is your cell phone number (in case we need to contact you)?<br>
<input type="text" name="cell_number"><br>
<br><p>What car make/model did you come in (so we can see if you've left)?<br>
<input type="text" name="car_info"><br>
<input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
<?php
$base_path = get_base_path($key, "..");
if (file_exists("{$base_path}/waiver_link")) {
  $waiver_link = file_get_contents("{$base_path}/waiver_link");
  echo "<p><input type=checkbox name=\"waiver_signed\" value=\"signed\">  I have read and agreed to <a href=\"{$waiver_link}\">the waiver</a><br>\n";
}
else {
  echo "<p><input type=checkbox name=\"waiver_signed\" value=\"signed\">  I am participating of my own accord and hold the organizers harmless for any injury sustained.<br>\n";
}
?>
<br><br>
<input type="submit" value="Choose course">
</form>

<?php
echo get_web_page_footer();
?>
