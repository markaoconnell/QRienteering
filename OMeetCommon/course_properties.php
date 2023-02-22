<?php
// Information for parsing the course name
// and the properties.txt file for a course
// Combo course - something like a Motala, where there are multiple
// different course options but the results should be shown in one screen
// e.g. competitors run three loops in any order, but the final results are from all three loops
$LINEAR_COURSE = 1;
$SCORE_O_COURSE = 2;
$COMBO_COURSE = 3;

$NAME_FIELD = "name";
$TYPE_FIELD = "type";
$PENALTY_FIELD = "penalty";
$LIMIT_FIELD = "limit";
$MAX_SCORE_FIELD = "max";
$CONTROLS = "controls";
$ERROR_FIELD = "error";

$COMBO_COURSE_LIST = "course_list";

$LINEAR_COURSE_ID = "l";
$SCORE_COURSE_ID = "s";
$COMBO_COURSE_ID = "c";


function get_course_properties($course_path) {
  if (file_exists("{$course_path}/properties.txt")) {
    return(get_properties("{$course_path}/properties.txt", false));
  }

  return(array());
}

function get_email_properties($base_path) {
  if (file_exists("{$base_path}/email_properties.txt")) {
    return(get_properties("{$base_path}/email_properties.txt", true));
  }

  return(array());
}

function get_member_properties($base_path) {
  if (file_exists("{$base_path}/member_properties.txt")) {
    return(get_properties("{$base_path}/member_properties.txt", true));
  }

  return(array());
}

function get_email_extra_info_file($base_path) {
  return("{$base_path}/email_extra_info.txt");
}

function get_properties($properties_path, $filter_for_comments) {
  $props_as_hash = array();
  $properties_contents = file($properties_path);
  if ($filter_for_comments) {
    $properties_contents = array_filter($properties_contents, function ($line) { return (ltrim($line)[0] != "#"); });
  }
  array_map(function ($string) use (&$props_as_hash) { $first_colon = strpos($string, ":");
                                                       $props_as_hash[trim(substr($string, 0, $first_colon))] = trim(substr($string, $first_colon + 1)); },
              $properties_contents);

  return($props_as_hash);
}
?>
