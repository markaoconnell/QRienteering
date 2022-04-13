<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require 'name_matcher.php';

ck_testing();

echo get_web_page_header(true, false, true);

$key = isset($_GET["key"]) ? $_GET["key"] : "";
$key = translate_key($key);
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$saved_registration_info = array();
if (isset($_COOKIE["{$key}-safety_info"])) {
  $saved_registration_info = parse_registration_info($_COOKIE["{$key}-safety_info"]);
}

$member_cookie_found = false;
if (isset($_COOKIE["{$key}-member_ids"])) {
  // Format will be member_id:timestamp_of_last_registration,member_id:timestamp_of_last_registration,...
  $member_cookie_found = true;
}

$current_time = time();
$is_generic_registration = isset($_GET["generic"]);
$is_member_registration = isset($_GET["member"]) && !$is_generic_registration;
$member_properties = get_member_properties(get_base_path($key));
$club_name = get_club_name($key, $member_properties);

if ($is_member_registration) {
  $radio_button_string = "";
  if ($member_cookie_found) {
    $matching_info = read_names_info(get_members_path($key, $member_properties), get_nicknames_path($key, $member_properties));
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
  <p class="title"><u><?php echo $club_name ?> club member registration:</u>
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
  <br><p><p>Lookup by Si Unit number:
  <input type="text" name="si_stick"><br>
  <input type="hidden" name="key" <?php echo "value=\"{$key}\""; ?> >
  <input type="submit" value="SI unit lookup">
  </form>
<?php
}
elseif ($is_generic_registration) {
?>
  <p class="title"><u>Meet registration, click on the appropriate link</u>
  <p><a href="./competition_register.php?member=1&key=<?php echo $key; ?>"><?php echo $club_name ?> club member</a>
  <p><a href="./competition_register.php?key=<?php echo $key; ?>">Non-member (or member of other club)</a>
<?php
}
else {
  if (isset($saved_registration_info["first_name"])) {
    $presupplied_first_name = "value=\"{$saved_registration_info["first_name"]}\"";
  }
  else {
    $presupplied_first_name = "";
  }

  if (isset($saved_registration_info["last_name"])) {
    $presupplied_last_name = "value=\"{$saved_registration_info["last_name"]}\"";
  }
  else {
    $presupplied_last_name = "";
  }

  if (isset($saved_registration_info["club_name"])) {
    $presupplied_club_name = "value=\"{$saved_registration_info["club_name"]}\"";
  }
  else {
    $presupplied_club_name = "";
  }

  if (isset($saved_registration_info["si_stick"])) {
    $presupplied_si_stick = "value=\"{$saved_registration_info["si_stick"]}\"";
  }
  else {
    $presupplied_si_stick = "";
  }

?>
  <p class="title"><u>Non-<?php echo $club_name; ?> club member registration:</u>
  <form action="./add_safety_info.php">
  <p>What is your name?<br>
  <p>First name 
  <input type="text" name="competitor_first_name" <?php echo $presupplied_first_name; ?>><br>
  <p>Last name 
  <input type="text" name="competitor_last_name" <?php echo $presupplied_last_name; ?>><br>
  <br><p>What is your orienteering club affiliation (if any)?<br>
  <input type="text" name="club_name" <?php echo $presupplied_club_name; ?>><br>
  <br><p>If you are using a SI unit today, please enter the number here<br>
  <input type="text" name="si_stick" <?php echo $presupplied_si_stick; ?>><br>
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
