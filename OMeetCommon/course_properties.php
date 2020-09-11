<?php
// Information for parsing the course name
// and the properties.txt file for a course
$LINEAR_COURSE = 1;
$SCORE_O_COURSE = 2;

$NAME_FIELD = "name";
$TYPE_FIELD = "type";
$PENALTY_FIELD = "penalty";
$LIMIT_FIELD = "limit";
$MAX_SCORE_FIELD = "max";
$ERROR_FIELD = "error";

$LINEAR_COURSE_ID = "l";
$SCORE_COURSE_ID = "s";


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
