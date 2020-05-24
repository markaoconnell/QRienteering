<?php

require '../run_competition/name_matcher.php';

function error_and_exit($error_string) {
  echo "ERROR: {$error_string}\n";
  file_put_contents("./failure", "{$error_string}\n");
  exit(1);
}

function check_name_match($matching_info, $first, $last) {
  $candidate_member_id = find_best_name_match ($matching_info, $first, $last);
  if ($candidate_member_id == -1) {
    echo "No member found for {$first} {$last}\n";
  }
  else {
    echo "Member found for {$first} {$last} -> ";
    print_r($matching_info["members_hash"][$candidate_member_id]);
    echo "\n";
  }
}


$matching_info = read_names_info("../run_competition/members.csv", "../run_competition/nicknames.csv");

// Check that things seem to have parsed correctly
if (!isset($matching_info["members_hash"])) {
  error_and_exit("No members hash field.");
}
if (!isset($matching_info["last_name_hash"])) {
  error_and_exit("No last name hash field.");
}
if (!isset($matching_info["full_name_hash"])) {
  error_and_exit("No full name hash field.");
}
if (!isset($matching_info["si_hash"])) {
  error_and_exit("No si hash field.");
}
if (!isset($matching_info["nicknames_hash"])) {
  error_and_exit("No nicknames hash field.");
}

$members_hash = $matching_info["members_hash"];
$last_name_hash = $matching_info["last_name_hash"];
$full_name_hash = $matching_info["full_name_hash"];
$si_hash = $matching_info["si_hash"];
$nicknames_hash = $matching_info["nicknames_hash"];

// Test a few known entries to make sure that things parsed correctly
if (!isset($full_name_hash["Lydia OConnell"])) {
  error_and_exit("Missing name Lydia OConnell");
}
if (!isset($full_name_hash["Mark OConnell"])) {
  error_and_exit("Missing name Mark OConnell");
}
if (!isset($last_name_hash["OConnell"])) {
  error_and_exit("Missing last name OConnell");
}
if (!isset($last_name_hash["Yeowell"])) {
  error_and_exit("Missing last name Yeowell");;
}
if (!isset($si_hash["2108369"])) {
  error_and_exit("Missing si stick 2108369");
}
if (!isset($si_hash["3959473"])) {
  error_and_exit("Missing si stick 3959473");;
}
if (!isset($nicknames_hash["Chris"])) {
  error_and_exit("Missing nickname Chris");;
}
if (!isset($members_hash["41"])) {
  error_and_exit("Missing member id 41");
}
if (isset($last_name_hash["no_one_has_this_last_name"])) {
  error_and_exit("Too many last names, no_one_has_this_last_name");
}
if (isset($last_name_hash["OConnelly"])) {
  error_and_exit("Too man last names, OConnelly");
}
if (isset($full_name_hash["Marcus OConnell"])) {
  error_and_exit("Too many names, Marcus OConnell");
}


check_name_match ($matching_info, "Mark", "OConnell");
check_name_match ($matching_info, "Mark", "O'Connell");
check_name_match ($matching_info, "Marc", "OConnell");
check_name_match ($matching_info, "Martha", "OConnell");
check_name_match ($matching_info, "Robert", "OConnell");
check_name_match ($matching_info, "Lawrence", "Berrill");
check_name_match ($matching_info, "Larry", "Berrill");
check_name_match ($matching_info, "Caitlin", "Marks");
check_name_match ($matching_info, "Stephen", "Berrill");

echo "Success!\n";

?>
