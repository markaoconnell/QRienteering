<?php
function enable_event_nre_classes($event, $key) {
  if (!file_exists(get_event_path($event, $key) . "/using_nre_classes")) {
    file_put_contents(get_event_path($event, $key) . "/using_nre_classes", "");
  }

  if (file_exists("../OMeetData/" . key_to_path($key) . "/default_classes.csv")) {
    copy("../OMeetData/" . key_to_path($key) . "/default_classes.csv", get_event_path($event, $key) . "/default_classes.csv");
  }
  else {
    echo "No default classes found!\n";
  }

  if (file_exists("../OMeetData/" . key_to_path($key) . "/nre_class_display_order.txt")) {
    copy("../OMeetData/" . key_to_path($key) . "/nre_class_display_order.txt", get_event_path($event, $key) . "/nre_class_display_order.txt");
  }
}

function event_is_using_nre_classes($event, $key) {
  return(file_exists(get_event_path($event, $key) . "/using_nre_classes"));
}

function set_class_for_competitor($competitor_path, $class) {
  file_put_contents("{$competitor_path}/competition_class", $class);
}

function remove_class_for_competitor($competitor_path) {
  if (file_exists("{$competitor_path}/competition_class")) {
    unlink("{$competitor_path}/competition_class");
  }
}

function competitor_has_class($competitor_path) {
  return(file_exists("{$competitor_path}/competition_class"));
}

function get_class_for_competitor($competitor_path) {
  if (competitor_has_class($competitor_path)) {
    return(file_get_contents("{$competitor_path}/competition_class"));
  }
  else {
    return("");
  }
}

// I add the GenderId: to the gender to make the base64 encoding of the gender a little less
// obvious.  I don't know if this is really worthwhile and maybe I should get rid of this, but I'll keep it
// for now.
function encode_entrant_classification_info($birth_year, $gender, $presupplied_class) {
  if ($presupplied_class != "") {
    $class_info = "CLASS:" . base64_encode($presupplied_class);
  }
  else {
    $class_info = "CLASS:";
  }
  return("BY:" . base64_encode($birth_year) . ",G:" . base64_encode("GenderId:" . $gender) . ",{$class_info}");
}

function decode_entrant_classification_info($classification_info) {
  $pieces = explode(",", $classification_info);
  $pre_hash = array_map(function ($entry) { return (explode(":", $entry)); }, $pieces);
  $return_hash = array();
  array_map(function ($entry) use (&$return_hash) { $return_hash[$entry[0]] = base64_decode($entry[1]); }, $pre_hash);
  if (isset($return_hash["G"])) {
    $gender_pieces = explode(":", $return_hash["G"]);
    $return_hash["G"] = $gender_pieces[1];
    // Comment this out after done with testing
    if ($gender_pieces[0] != "GenderId") {
      echo "WARNING: classification info has wrong gender field {$gender_pieces[0]}:{$gender_pieces[1]}\n";
    }
  }
  return($return_hash);
}

function get_default_nre_classification_file($key) {
  return("../OMeetData/" . key_to_path($key) . "/default_classes.csv");
}

function get_nre_classification_file($event, $key) {
  $event_specific_classes = get_event_path($event, $key) . "/default_classes.csv";
  if (file_exists($event_specific_classes)) {
    return($event_specific_classes);
  }
  else {
    return(get_default_nre_classification_file($key));
  }
}

function get_default_nre_class_display_order($key) {
  $display_order_filename = "../OMeetData/" . key_to_path($key) . "/nre_class_display_order.txt";
  if (file_exists($display_order_filename)) {
    return(file($display_order_filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
  }
  else {
    return (array());
  }
}

function get_nre_class_display_order($event, $key) {
  $display_order_filename = get_event_path($event, $key) . "/nre_class_display_order.txt";
  if (file_exists($display_order_filename)) {
    return(file($display_order_filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
  }
  else {
    return (get_default_nre_class_display_order($key));
  }
}


// Get the nre classification info
// The returned information is ordered so that the first match should be the best
// The information comes back in an array, with each entry
// [0] -> Course being run
// [1] -> gender
// [2] -> min age
// [3] -> max age
// [4] -> QR codes allowed
// [5] -> classification
function get_nre_classes_info($event, $key) {
  $nre_classification_file = get_nre_classification_file($event, $key);
  return(read_and_parse_classification_file($nre_classification_file));
}

// This is mostly separate from get_nre_classes_info for easier testing in unit tests
function read_and_parse_classification_file($nre_classification_file) {
  if (file_exists($nre_classification_file)) {
    $nre_classification_data = file($nre_classification_file, FILE_IGNORE_NEW_LINES);
    $filtered_data = array_filter($nre_classification_data, function ($line) { $trimmed = ltrim($line); return (($trimmed != "") && ($trimmed[0] != "#")); });
    $parsed_classes = array_map(function ($line) { return explode(",", $line); }, $filtered_data);
    return($parsed_classes);
  }

  // Return a sensible default - no entries
  return (array());
}

?>
