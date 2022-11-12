<?php
// Should alredy be included via other includes
// require '../OMeetCommon/common_routines.php';

function get_current_year() {
  $time_elements = localtime();
  $year = 1900 + $time_elements[5];
  return($year);
}

function get_age($birth_year) {
  if ($birth_year <= 0) {
    return (-1);
  }

  $current_year = get_current_year();
  $two_digit_current_year = $current_year % 100;

  // The current code should validate the birth year at the UI
  // level to be a four digit year, however, for safety, let's
  // try and handle two digit birth years sanely
  if ($birth_year < 100) {
    if ($birth_year > $two_digit_current_year) {
      $birth_year += 1900;
    }
    else {
      $birth_year += 2000;
    }
  }

  return($current_year - $birth_year);
}

function get_nre_class($event, $key, $gender, $birth_year, $course, $using_si_timing) {
  if (event_is_using_nre_classes($event, $key)) {
    $classification_info = get_nre_classes_info($event, $key);
    $age_for_classification = get_age($birth_year);
    if ($age_for_classification > 0) {
      return(find_best_class($classification_info, $gender, $age_for_classification, $course, $using_si_timing));
    }
  }
  return("");
}

function find_best_class($classification_info, $gender, $age_for_classification, $course, $using_si_timing) {
  // Just in case, make these lower case and strip off the prefix from the course
  $lcase_gender = strtolower($gender);
  $lcase_course = strtolower(ltrim($course, "[0..9]-"));

  foreach ($classification_info as $class_info) {
    if ((strtolower($class_info[0]) == $lcase_course) && (strtolower($class_info[1]) == $lcase_gender) &&
        ($age_for_classification >= $class_info[2]) && ($age_for_classification <= $class_info[3]) &&
	($using_si_timing || ($class_info[4] == "y"))) {  // class_info[4] says if QR timing is acceptable
      return($class_info[5]);  // Return the matched class
    }
  }

  return("");  // No match, should rarely happen, should be weeded out earlier
}

?>
