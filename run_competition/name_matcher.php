<?php

$MAX_CHECK_DISTANCE = 5;

// member_id;first;last;si_stick
// Read in the members together with si_stick, if they have one
// Results
// Hash from si_stick -> member
// Hash from member-> hash(first -> , last ->, full_name ->, si_stick->)
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
      $member_hash[$pieces[0]] = array("first" => $pieces[1],
                                       "last" => $pieces[2],
                                       "full_name" => "{$pieces[1]} {$pieces[2]}",
                                       "si_stick"=> $pieces[3]);
      $last_name_hash[$pieces[2]][] = $pieces[0]; 
      $full_name_hash["{$pieces[1]} {$pieces[2]}"] = $pieces[0];
    }
  }

  if (file_exists($nicknames_file)) {
    $nickname_list = file($nicknames_file, FILE_IGNORE_NEW_LINES);
    foreach ($nickname_list as $equivalent_names_csv) {
      $pieces = explode(";", $equivalent_names_csv);
      foreach ($pieces as $name_in_list) {
        $nicknames_hash[$name_in_list] = $pieces;
      }
    }
  }

  return (array("members_hash" => $member_hash,
               "last_name_hash" => $last_name_hash,
               "full_name_hash" => $full_name_hash,
               "si_hash" => $si_hash,
               "nicknames_hash" => $nicknames_hash));
}



function find_best_match_by_distance($name_to_check, $list_of_names) {

  global $MAX_CHECK_DISTANCE;

  $match_distances = array();
  foreach ($list_of_names as $member_name) {
    $dist = levenshtein($name_to_check, $member_name);
    //echo "Distance from {$name_to_check} to {$member_name} is {$dist}\n";
    if ($dist < $MAX_CHECK_DISTANCE) {
      $match_distances[$dist][] = $member_name;
    }
  }

  // return the best match - the list with the shortest distance
  foreach ($match_distances as $name_list) {
    //echo "Returning " . implode(",", $name_list) . " as best matches for {$name_to_check}\n";
    return($name_list);
  }

  return(array());
}

function find_best_name_match ($matching_info, $first_name, $last_name) {
  $member_hash = $matching_info["members_hash"];
  $last_name_hash = $matching_info["last_name_hash"];
  $full_name_hash = $matching_info["full_name_hash"];
  $nicknames_hash = $matching_info["nicknames_hash"];

  // Try the easy case - the name simply exists in the list
  $full_name = "{$first_name} {$last_name}";
  if (isset($full_name_hash[$full_name])) {
    return($full_name_hash[$full_name]);
  }


  // Look for a match on the last name
  $last_name_matches = array();
  if (isset($last_name_hash[$last_name])) {
    $last_name_matches = array($last_name);
  }
  else {
    $last_name_matches = find_best_match_by_distance($last_name, array_keys($last_name_hash));
    //echo "No exact match for $last_name, possibilities are: " . implode(",", $last_name_matches) . "\n";
  }

  // No member with anything that looks like this last name, return no match
  if (count($last_name_matches) == 0) {
    return -1;
  }

  if (count($last_name_matches) == 1) {
    // Is there an exact match on the full name?  If so, we're good
    // This catches a slight misspelling in the last name
    if (isset($full_name_matches["{$first_name} {$last_name_matches[0]}"])) {
      return ($full_name_matches["{$first_name} {$last_name_matches[0]}"]);
    }
  }

  # Do the full blown nickname check
  # For each possible member, get the list of nicknames associated
  //echo "Checking " . implode (",", $last_name_matches) . " for nicknames\n";
  foreach ($last_name_matches as $possible_last_name) {
    //echo "Looking at members " . implode (",", $last_name_hash[$possible_last_name]) . " for nicknames\n";
    foreach ($last_name_hash[$possible_last_name] as $possible_member_id) {
      $possible_member_first_name = $member_hash[$possible_member_id]["first"];
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

  if (count($best_name_matches) == 0) {
    return -1;  // no match found
  }

  // Only one match
  if (count($best_name_matches) == 1) {
    if (count($possible_nicknames[$best_name_matches[0]]) == 1) {
      // Only one member matches - return the member id
      return ($possible_nicknames[$best_name_matches[0]][0]);
    }
  }

  
  // Multiple matches - but they may all be for the same member id, which is ok
  $candidate_member_id = $possible_nicknames[$best_name_matches[0]][0];
  foreach ($best_name_matches as $possible_name) {
    foreach ($possible_nicknames[$possible_name] as $possible_member_id) {
      if ($candidate_member_id != $possible_member_id) {
        // Hmmm, multiple members have too similar a name and we can't disambiguate
        // just give up
        return(-1);
      }
    }
  }

  return($candidate_member_id);
}

?>