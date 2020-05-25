<?php

require '../run_competition/name_matcher.php';

function error_and_exit($error_string) {
  echo "ERROR: {$error_string}\n";
  file_put_contents("./failure", "{$error_string}\n");
  exit(1);
}

function check_name_match($matching_info, $first, $last, $expected_matches) {
  $candidate_member_ids = find_best_name_match ($matching_info, $first, $last);

  $intersection = array_intersect($candidate_member_ids, $expected_matches);
  if ((count($intersection) != count($candidate_member_ids)) ||
      (count($intersection) != count($expected_matches))) {
    $members_hash = $matching_info["members_hash"];
    $found_members = array_map(function ($elt) use ($members_hash) { return ("{$members_hash[$elt]["full_name"]}-({$elt})"); }, $candidate_member_ids);
    $expected_members = array_map(function ($elt) use ($members_hash) { return ("{$members_hash[$elt]["full name"]}-({$elt})"); }, $expected_matches);
    error_and_exit("Name matching failed!  Got " . implode(",", $found_members) . " while expected " . implode(",", $expected_members) . "\n");
  }
  else {
    echo "Match on {$first} {$last} succeeded!\n";
  }
}

$fake_members_string = <<<END_OF_MEMBERS_FILE
591;Aaron;Aaker;
1431;Andrew;Anselmo;
1501;Jim;Arsenault;
1203;Susan;Axe-Bronk;
1774;Caroline;Baldwin;
426;Caroline;Baldwin;
422;Edward;Baldwin;
1771;Edward;Baldwin;
1773;Elizabeth;Baldwin;
421;Julie;Baldwin;
1772;Julie;Baldwin;
424;Katherine;Baldwin;
425;Lizzy;Baldwin;
423;Margaret;Baldwin;
1261;Tom;Baldwin;
41;Larry;Berrill;
323;Anna;Campbell;
322;Jonathan;Campbell;
324;Peter;Campbell;
321;Victoria;Campbell;
262;Xavier;Fradera;
33;Lydia;OConnell;
32;Mark;OConnell;2108369;
232;Mary;OConnell;141421;
31;Karen;Yeowell;3959473
END_OF_MEMBERS_FILE;

$fake_nicknames_string = <<<END_OF_NICKNAMES_FILE
Victoria;Tori;
Michael;Mike;
Donald;Don;
James;Jim;Jimmy;
Patrice;Patricia;Patty;Patti;
Jose Luis;Jose;
Andrew;Andy;
Susan;Sue;Suzanne;
Elizabeth;Beth;Liz;Lizzy;
Thomas;Tom;
Catherine;Cathy;
Theodore;Ted;Ed;Edward;
Lawrence;Larry;
Rebecca;Becky;
Phillip;Phil;
Jonathan;Jon;
Robert;Bob;Bobby;Rob;
Nathaniel;Nat;Natasha;
Alexander;Alexandra;Alex;
Matthew;Matt;
Jennifer;Jenny;
Xavier;Xevi;
Samuel;Samantha;Sam;
Stephen;Steven;Steve;
Christopher;Chris;Christine;
Richard;Dick;
Jeffrey;Jeff;
Judith;Judy;
Timothy;Tim;
END_OF_NICKNAMES_FILE;

file_put_contents("./members.csv", $fake_members_string);
file_put_contents("./nicknames.csv", $fake_nicknames_string);

$matching_info = read_names_info("./members.csv", "./nicknames.csv");

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


check_name_match ($matching_info, "Mark", "OConnell", array(32));
check_name_match ($matching_info, "Mark", "O'Connell", array(32));
check_name_match ($matching_info, "Marc", "OConnell", array(232,32));
check_name_match ($matching_info, "Mary", "OConnell", array(232));
check_name_match ($matching_info, "Martha", "OConnell", array());
check_name_match ($matching_info, "Mart", "OConnell", array(32, 232));
check_name_match ($matching_info, "Robert", "OConnell", array());
check_name_match ($matching_info, "Lawrence", "Berrill", array(41));
check_name_match ($matching_info, "Larry", "Berrill", array(41));
check_name_match ($matching_info, "Caitlin", "Marks", array());
check_name_match ($matching_info, "Stephen", "Berrill", array());
check_name_match ($matching_info, "Ed", "Baldwin", array(422));
check_name_match ($matching_info, "Xevi", "Fradera", array(262));
check_name_match ($matching_info, "Thomas", "Baldwin", array(1261));

echo "Success!\n";

?>
