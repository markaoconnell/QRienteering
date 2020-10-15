<?php
require '../OMeetCommon/common_routines.php';
require 'name_matcher.php';

ck_testing();

echo get_web_page_header(true, false, true);

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$member_cookie_found = false;
if (isset($_COOKIE["{$key}-member_ids"])) {
  // Format will be member_id:timestamp_of_last_registration,member_id:timestamp_of_last_registration,...
  $member_cookie_found = true;
}

$current_time = time();
$is_member_registration = isset($_GET["member"]);

if ($is_member_registration) {
  $radio_button_string = "";
  if ($member_cookie_found) {
    $matching_info = read_names_info(get_members_path($key, ".."), get_nicknames_path($key, ".."));
    $time_cutoff = $current_time - (86400 * 90);  // 3 month window
    $member_ids = array_map(function ($elt) { return (explode(":", $elt)); }, explode(",", $_COOKIE["{$key}-member_ids"]));
    $member_ids = array_filter($member_ids, function ($member_entry) use ($time_cutoff) { return ($member_entry[1] > $time_cutoff); });

    
    if (count($member_ids) > 0) {
      $radio_button_string .= "<p>Register known member on this device:<br>\n";
      foreach ($member_ids as $member_entry) {
        $radio_button_string .= "<input type=\"radio\" name=\"member_id\" value=\"{$member_entry[0]}\" id=\"radio-{$member_entry[0]}\">\n";
        $radio_button_string .= "<label for=\"radio-{$member_entry[0]}\">" . get_full_name($member_entry[0], $matching_info) . "</label><br>\n";
      }

      $radio_button_string .= "<input type=\"submit\" value=\"Register known member\"><br><br>\n";
    }
  }
?>
  <p class="title"><u>NEOC club member registration:</u>
  <form action="./name_lookup.php">
  <?php if ($radio_button_string != "") { echo "{$radio_button_string}<p><p>\n"; } ?>
  <p>Lookup by member name:
  <p>First name 
  <input type="text" name="competitor_first_name"><br>
  <p>Last name 
  <input type="text" name="competitor_last_name"><br>
  <input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
  <input type="submit" value="Member name lookup">
  </form>
  
  <form action="./stick_lookup.php">
  <br><p><p>Lookup by Si Stick:
  <input type="text" name="si_stick"><br>
  <input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
  <input type="submit" value="SI stick lookup">
  </form>
<?php
}
else {
?>
  <p class="title"><u>Non-NEOC club member registration:</u>
  <form action="./add_safety_info.php">
  <p>What is your name?<br>
  <p>First name 
  <input type="text" name="competitor_first_name"><br>
  <p>Last name 
  <input type="text" name="competitor_last_name"><br>
  <br><p>What is your orienteering club affiliation (if any)?<br>
  <input type="text" name="club_name"><br>
  <br><p>If you are using a SI stick today, please enter the number here<br>
  <input type="text" name="si_stick"><br>
  <input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
  <br><br>
  <input type="submit" value="Fill in safety information">
  </form>
<?php
}
?>

<?php
echo get_web_page_footer();
?>
