<?php

function get_preregistered_entrant($entrant, $event, $key) {
  if (preregistrations_allowed($event, $key)) {
    $entrant_path = "../OMeetData/" . key_to_path($key) . "/{$event}/Preregistrations/{$entrant}";
    return ($entrant_path);
  }

  return("");
}

function get_preregistrations($event, $key) {
  if (preregistrations_allowed($event, $key)) {
    $entrants = scandir("../OMeetData/" . key_to_path($key) . "/{$event}/Preregistrations");
    return(array_diff($entrants, array(".", "..")));
  }
  else {
    return(array());
  }
}

function preregistrations_allowed($event, $key) {
  return(file_exists("../OMeetData/" . key_to_path($key) . "/{$event}/Preregistrations"));
}

function preregistrations_allowed_by_event_path($base_event_pathname) {
  return(file_exists("{$base_event_pathname}/Preregistrations"));
}

function enable_preregistration($event, $key) {
  if (!file_exists("../OMeetData/" . key_to_path($key) . "/{$event}/Preregistrations")) {
    mkdir("../OMeetData/" . key_to_path($key) . "/{$event}/Preregistrations");
  }
}


function encode_preregistered_entrant($preregistered_entrant) {
  $entrant_pieces = array_map(function ($elt) use ($preregistered_entrant) { return("{$elt}," . base64_encode($preregistered_entrant[$elt])); },
                              array_keys($preregistered_entrant));
  return(implode(":", $entrant_pieces));
}


function decode_preregistered_entrant($preregistered_entrant_path) {
  $encoded_entrant_info = file_get_contents($preregistered_entrant_path);
  $encoded_entrant_pieces = explode(":", $encoded_entrant_info);

  $entrant_info = array();
  array_map(function ($elt) use (&$entrant_info) { $kv_pieces = explode(",", $elt); $entrant_info[$kv_pieces[0]] = base64_decode($kv_pieces[1]); },
            $encoded_entrant_pieces);

  return($entrant_info);
}


// Read in the preregistered people and return the list in a (hopefully) useful format
// Results
// Hash from si_stick -> preregistration_id
// Hash from preregistration_id -> hash(first -> , last ->, full_name ->, si_stick->, email->)
// Hash from last -> preregistration_id list
// Hash from full_name -> preregistration_id (assumes unique names amongst the pre-registrants)
function read_preregistrations($event, $key) {

  $member_hash = array();
  $last_name_hash = array();
  $full_name_hash = array();
  $si_hash = array();
  $nicknames_hash = array();

  $preregistration_ids = get_preregistrations($event, $key);
  foreach ($preregistration_ids as $preregistered_entrant) {
    $entrant_info_path = get_preregistered_entrant($preregistered_entrant, $event, $key);
    if ($entrant_info_path == "") {
      // This shouldn't happen, but just in case
      continue;
    }


    $entrant_info = decode_preregistered_entrant($entrant_info_path);

    $member_hash[$preregistered_entrant] = array("first" => $entrant_info["first_name"],
                                                 "last" => $entrant_info["last_name"],
                                                 "full_name" => "{$entrant_info["first_name"]} {$entrant_info["last_name"]}",
                                                 "si_stick"=> $entrant_info["stick"]);
    $lower_case_full_name = strtolower("{$entrant_info["first_name"]} {$entrant_info["last_name"]}");
    $last_name_hash[strtolower($entrant_info["last_name"])][] = $preregistered_entrant; 
    $full_name_hash[$lower_case_full_name] = $preregistered_entrant;
    $si_hash[$entrant_info["stick"]] = $preregistered_entrant;
  }

  return (array("members_hash" => $member_hash,
                "last_name_hash" => $last_name_hash,
                "full_name_hash" => $full_name_hash,
                "si_hash" => $si_hash,
                "nicknames_hash" => $nicknames_hash));
}

?>
