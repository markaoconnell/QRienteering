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
  $props_as_hash = array();
  if (file_exists("${course_path}/properties.txt")) {
    $properties_contents = file("{$course_path}/properties.txt");
    array_map(function ($string) use (&$props_as_hash) { $pieces = explode(":", trim($string)); $props_as_hash[$pieces[0]] = $pieces[1]; },
              $properties_contents);
  }

  return($props_as_hash);
}
?>
