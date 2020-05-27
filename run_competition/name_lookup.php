<?php
require '../common_routines.php';
require './name_matcher.php';

ck_testing();

$matching_info = read_names_info("./members.csv", "./nicknames.csv");

if (!isset($_GET["member_id"])) {
  if (!isset($_GET["competitor_first_name"]) || ($_GET["competitor_first_name"] == "")) {
    error_and_exit("Unspecified competitor first name, please retry.\n");
  }
  
  if (!isset($_GET["competitor_last_name"]) || ($_GET["competitor_last_name"] == "")) {
    error_and_exit("Unspecified competitor last name, please retry.\n");
  }
  
  $first_name_to_lookup = $_GET["competitor_first_name"];
  $last_name_to_lookup = $_GET["competitor_last_name"];
  
  $possible_member_ids = find_best_name_match($matching_info, $first_name_to_lookup, $last_name_to_lookup);
}
else {
  $possible_member_ids = array($_GET["member_id"]);
}

$error_string = "";
$success_string = "";
if (count($possible_member_ids) == 0) {
  error_and_exit("No such member {$first_name_to_lookup} {$last_name_to_lookup} found, please retry or ask for assistance.\n");
}
else if (count($possible_member_ids) == 1) {
  $printable_name = get_full_name($possible_member_ids[0], $matching_info);
  $si_stick = get_si_stick($possible_member_ids[0], $matching_info);
  $success_string .= "<p>Welcome {$printable_name}.\n";
  if ($si_stick != "") {
    $success_string .= "<p>Are you using a SI stick ({$si_stick}) today?";
    $yes_checked_by_default = "checked";
    $no_checked_by_default = "";
  }
  else {
    $success_string .= "<p> Are you using a SI Stick today?";
    $yes_checked_by_default = "";
    $no_checked_by_default = "checked";
  }
  $success_string .= <<<END_OF_FORM
<form action="./finalize_member_registration.php">
<input type=hidden name="member_id" value="{$possible_member_ids[0]}"/>
<p> Yes <input type=radio name="using_stick" value="yes" {$yes_checked_by_default} /> <input type=text name="si_stick_number" value="{$si_stick}" />
<p> No <input type=radio name="using_stick" value="no" {$no_checked_by_default}/>
<p><input type="submit" value="Register"/>
</form>
END_OF_FORM;
}
else {
  $success_string .= "<p>Ambiguous member name, please choose:\n";
  $success_string .= "<form action=\"name_lookup.php\">\n";
  foreach ($possible_member_ids as $possible_member) {
    $success_string .= "<p><input type=radio name=\"member_id\" value=\"{$possible_member}\"> " . get_full_name($possible_member, $matching_info) . "\n";
  }
  $success_string .= "<p><input type=submit name=\"Choose member\"/>\n";
  $success_string .= "</form>\n";
}


echo get_web_page_header(true, false, true);

echo $success_string;

echo get_web_page_footer();
?>
