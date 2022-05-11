<?php

$MAX_CHECK_DISTANCE = 3;

// member_id;first;last;si_stick;email;cell_phone;
// Read in the members together with si_stick, if they have one
// Results
// Hash from si_stick -> member
// Hash from member-> hash(first -> , last ->, full_name ->, si_stick->, email->, cell_phone->, birth_year->, gender->)
// Hash from last -> member list
// Hash from full_name -> member_id (assumes unique names amongst the membership)
// Hash of equivalent nicknames
function read_names_info($member_file, $nicknames_file) {

  $member_hash = array();
  $last_name_hash = array();
  $full_name_hash = array();
  $si_hash = array();
  $nicknames_hash = array();

  if (file_exists($member_file)) {
    $member_list = file($member_file, FILE_IGNORE_NEW_LINES);
    foreach ($member_list as $elt) {
      $pieces = explode(";", $elt);
      if ($pieces[3] != "") {
        //echo "Found {$pieces[3]} for entry $elt.\n";
        $si_hash[$pieces[3]] = $pieces[0];
      }

      // Weed out duplicate names - assume someone accidentally joined twice
      $lower_case_full_name = strtolower("{$pieces[1]} {$pieces[2]}");
      $si_stick = isset($pieces[3]) ? $pieces[3] : "";
      $email_address = isset($pieces[4]) ? $pieces[4] : "";
      $cell_phone = isset($pieces[5]) ? $pieces[5] : "";
      $birth_year = isset($pieces[6]) ? $pieces[6] : "";
      $gender = isset($pieces[7]) ? $pieces[7] : "";
      if (!isset($full_name_hash[$lower_case_full_name])) {
        $member_hash[$pieces[0]] = array("first" => $pieces[1],
                                         "last" => $pieces[2],
                                         "full_name" => "{$pieces[1]} {$pieces[2]}",
                                         "si_stick"=> $si_stick,
					 "email" => $email_address,
					 "cell_phone" => $cell_phone,
	                                 "birth_year" => $birth_year,
	                                 "gender" => $gender);
        $last_name_hash[strtolower($pieces[2])][] = $pieces[0]; 
        $full_name_hash[$lower_case_full_name] = $pieces[0];
      }
    }
  }

  $nicknames_hash = read_nicknames_info($nicknames_file);

  return (array("members_hash" => $member_hash,
               "last_name_hash" => $last_name_hash,
               "full_name_hash" => $full_name_hash,
               "si_hash" => $si_hash,
               "nicknames_hash" => $nicknames_hash));
}

function read_nicknames_info($nicknames_file) {
  $nicknames_hash = array();
  if (file_exists($nicknames_file)) {
    $nickname_list = file($nicknames_file, FILE_IGNORE_NEW_LINES);
    foreach ($nickname_list as $equivalent_names_csv) {
      $pieces = explode(";", strtolower($equivalent_names_csv));
      $pieces = array_filter($pieces, function ($elt) { return (trim($elt) != ""); } );
      foreach ($pieces as $name_in_list) {
        $nicknames_hash[$name_in_list] = $pieces;
      }
    }
  }

  return($nicknames_hash);
}

function get_full_name($member_id, $matching_info) {
  return($matching_info["members_hash"][$member_id]["full_name"]);
}

function get_member_name_info($member_id, $matching_info) {
  return(array($matching_info["members_hash"][$member_id]["first"], $matching_info["members_hash"][$member_id]["last"]));
}

function get_member_email($member_id, $matching_info) {
  return($matching_info["members_hash"][$member_id]["email"]);
}

function get_member_cell_phone($member_id, $matching_info) {
  return($matching_info["members_hash"][$member_id]["cell_phone"]);
}

function get_member_birth_year($member_id, $matching_info) {
  if (isset($matching_info["members_hash"][$member_id]["birth_year"]) && ($matching_info["members_hash"][$member_id]["birth_year"] != "")) {
    return($matching_info["members_hash"][$member_id]["birth_year"]);
  }
  # No birth year found, assume age 21 (most competitive, will generally put them in the Open category)
  $current_year = localtime()[5];
  $birth_year = $current_year + 1900 - 21;
  return($birth_year);
}

function get_member_gender($member_id, $matching_info) {
  return($matching_info["members_hash"][$member_id]["gender"]);
}

function get_si_stick($member_id, $matching_info) {
  return($matching_info["members_hash"][$member_id]["si_stick"]);
}

function get_by_si_stick($si_stick, $matching_info) {
  return(isset($matching_info["si_hash"][$si_stick]) ? $matching_info["si_hash"][$si_stick] : "");
}


function find_best_match_by_distance($name_to_check, $list_of_names) {

  global $MAX_CHECK_DISTANCE;

  // Always initialize this as full of empty arrays, makes finding
  // the first one with a non-empty value easier
  $match_distances = array();
  for ($i = 0; $i < $MAX_CHECK_DISTANCE; $i++) {
    $match_distances[$i] = array();
  }

  foreach ($list_of_names as $member_name) {
    $dist = levenshtein($name_to_check, $member_name);
    //echo "Distance from {$name_to_check} to {$member_name} is {$dist}\n";
    if ($dist < $MAX_CHECK_DISTANCE) {
      $match_distances[$dist][] = $member_name;
    }
  }

  // return the best match - the list with the shortest distance
  //echo "Found {$number_matches} matches for {$name_to_check}.\n";
  for ($i = 0; $i < $MAX_CHECK_DISTANCE; $i++) {
    if (isset($match_distances[$i]) && (count($match_distances[$i]) > 0)) {
      //echo "Returning " . implode(",", $match_distances[$i]) . " as best matches for {$name_to_check}\n";
      return($match_distances[$i]);
    }
  }

  return(array());
}

function find_all_close_matches($name_to_check, $list_of_names) {

  global $MAX_CHECK_DISTANCE;

  $combined_matches = array();
  foreach ($list_of_names as $member_name) {
    $dist = levenshtein($name_to_check, $member_name);
    //echo "Distance from {$name_to_check} to {$member_name} is {$dist}\n";
    if ($dist < $MAX_CHECK_DISTANCE) {
      $combined_matches[] = $member_name;
    }
  }

  return($combined_matches);
}

function find_best_name_match ($matching_info, $first_name, $last_name) {
  $member_hash = $matching_info["members_hash"];
  $last_name_hash = $matching_info["last_name_hash"];
  $full_name_hash = $matching_info["full_name_hash"];
  $nicknames_hash = $matching_info["nicknames_hash"];

  $first_name = strtolower($first_name);
  $last_name = strtolower($last_name);

  // Try the easy case - the name simply exists in the list
  $full_name = "{$first_name} {$last_name}";
  if (isset($full_name_hash[$full_name])) {
    return(array($full_name_hash[$full_name]));
  }


  // Look for a match on the last name
  $last_name_matches = array();
  if (isset($last_name_hash[$last_name])) {
    $last_name_matches = array($last_name);
    //echo "Found match for $last_name, hash returned: " . implode(",", $last_name_matches) . "\n";
  }
  else {
    $last_name_matches = find_best_match_by_distance($last_name, array_keys($last_name_hash));
    //echo "No exact match for $last_name, possibilities are: " . implode(",", $last_name_matches) . "\n";
  }

  // No member with anything that looks like this last name, return no match
  if (count($last_name_matches) == 0) {
    return array();
  }

  if (count($last_name_matches) == 1) {
    // Is there an exact match on the full name?  If so, we're good
    // This catches a slight misspelling in the last name
    if (isset($full_name_hash["{$first_name} {$last_name_matches[0]}"])) {
      return (array($full_name_hash["{$first_name} {$last_name_matches[0]}"]));
    }
    //echo "No match for {$first_name} {$last_name_matches[0]} -> {$full_name_hash["{$first_name} {$last_name_matches[0]}"]}\n";
  }

  // There may be two members with very similar last names (OConnell vs O'Connell)
  // And if Mark OConnell and Someone O'Connell are in the member list, and Mark O'Connell
  // searches, then it will find only Someone O'Connell as the possible match and will fail.
  // So if we are doing the full blown (slow) check, expand the search to all possible
  // last names, even if there was an exact match.
  $last_name_matches = find_all_close_matches($last_name, array_keys($last_name_hash));
  //echo "No exact match for $last_name, possibilities are: " . implode(",", $last_name_matches) . "\n";

  # Do the full blown nickname check
  # For each possible member, get the list of nicknames associated
  //echo "Checking " . implode (",", $last_name_matches) . " for nicknames\n";
  foreach ($last_name_matches as $possible_last_name) {
    //echo "Looking at members " . implode (",", $last_name_hash[$possible_last_name]) . " for nicknames\n";
    foreach ($last_name_hash[$possible_last_name] as $possible_member_id) {
      $possible_member_first_name = strtolower($member_hash[$possible_member_id]["first"]);
      if (isset($nicknames_hash[$possible_member_first_name])) {
        // Multiple nicknames are possible, remember them all
        $nicknames_to_check = $nicknames_hash[$possible_member_first_name];
      }
      else {
        // No known nicknames, just use the member's name
        $nicknames_to_check = array($possible_member_first_name);
      }

      foreach ($nicknames_to_check as $nickname) {
        $possible_nicknames["{$nickname} {$possible_last_name}"][] = $possible_member_id;
      }
    }
  }


  // find the closest match amongst all the possible nicknames etc
  $best_name_matches = find_best_match_by_distance("{$first_name} {$last_name}", array_keys($possible_nicknames));
  //echo "Best name matches for {$first_name} {$last_name} is " . implode(",", $best_name_matches) . "\n";
  //echo "Check nicknames for {$first_name} {$last_name}: " . implode(",", array_keys($possible_nicknames)) . "\n";

  if (count($best_name_matches) == 0) {
    return array();  // no match found
  }

  // Only one match
  if (count($best_name_matches) == 1) {
    if (count($possible_nicknames[$best_name_matches[0]]) == 1) {
      // Only one member matches - return the member id
      return (array($possible_nicknames[$best_name_matches[0]][0]));
    }
  }

  
  // Multiple matches - but they may all be for the same member id, which is ok
  $candidate_member_ids = array();
  foreach ($best_name_matches as $possible_name) {
    foreach ($possible_nicknames[$possible_name] as $possible_member_id) {
      if (!in_array($possible_member_id, $candidate_member_ids)) {
        // Return all matching ids and let the user choose, hopefully it is just one
        $candidate_member_ids[] = $possible_member_id;
      }
    }
  }

  return($candidate_member_ids);
}

?>
