<?php

require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetRegistration/nre_class_handling.php';

$failure = false;
$show_all_classification_tests = false;

$correct_current_year = localtime()[5] + 1900;  // Should calculate the current year
if (get_current_year() != $correct_current_year) {
  echo "Get current year did not return {$correct_current_year}, returned: " . get_current_year() . "\n";
  $failure = true;
}
else {
  echo "Get current year returned correct year ({$correct_current_year}).\n";
}

// Format is the entered birth year (2 or 4 digit) and how it should be interpreted
// Note: this doesn't work for 00, as 0 as a birth year is taken as unspecified
// May need to rethink this, but for the moment the UI should always guarantee a 4 digit year
// ************************
// Note - this assumes that "24" will be interpreted as 1924 until 2024, in which case it will be interpreted as 2024.
// Will need to update this test yearly.
$birth_year_to_age = array(array(1967, 1967), array(2001, 2001), array(1920, 1920), array(67, 1967), array(01, 2001),
                           array(20, 2020), array(23, 2023), array(24, 1924), array(40, 1940), array(99, 1999));

foreach ($birth_year_to_age as $test_birth_year_entry) {
  if (get_age($test_birth_year_entry[0]) != ($correct_current_year - $test_birth_year_entry[1])) {
    echo "Did not correctly calculate age, entry is {$test_birth_year_entry[0]} -> {$test_birth_year_entry[1]}, returned: " . get_age($test_birth_year_entry[0]) . "\n";
    $failure = true;
  }
  else {
    echo "Get age returned correct age for ({$test_birth_year_entry[0]}).\n";
  }
}

if (get_age(0) != -1) {
  echo "Did not correctly ignore birth year of 0, returned: " . get_age(0) . "\n";
  $failure = true;
}
else {
  echo "Get age returned correct age for 0 (-1, did not treat as year 2000)\n";
}

# Start checking the actual classification tables
$class_info = read_and_parse_classification_file("./default_classes.csv");


// Format is gender, age, course being run, true if using si timing, expected classification
$classification_test_data = array(
	array("f", 5, "White", False, "F-10"),
	array("f", 10, "White", False, "F-10"),
	array("f", 12, "White", False, "F-12"),
	array("f", 13, "Yellow", False, "F-14"),
	array("f", 14, "Yellow", False, "F-14"),
	array("f", 14, "White", False, "MF White"),
	array("f", 14, "Orange", False, "F-16"),
	array("f", 16, "Orange", False, "F-16"),
	array("f", 16, "Brown", False, ""),
	array("f", 16, "Brown", True, "F-18"),
	array("f", 16, "Yellow", False, "F Yellow"),
	array("f", 18, "YELlow", False, "F Yellow"),
	array("f", 5, "White", True, "F-10"),
	array("f", 10, "White", True, "F-10"),
	array("f", 12, "White", True, "F-12"),
	array("f", 13, "Yellow", True, "F-14"),
	array("f", 14, "Yellow", True, "F-14"),
	array("f", 14, "White", True, "MF White"),
	array("f", 14, "Orange", True, "F-16"),
	array("f", 16, "Orange", True, "F-16"),
	array("f", 16, "Brown", False, ""),
	array("f", 16, "Brown", True, "F-18"),
	array("f", 16, "Yellow", False, "F Yellow"),
	array("f", 18, "YELlow", False, "F Yellow"),
	array("f", 18, "Brown", False, ""),
	array("f", 18, "Brown", True, "F-18"),
	array("f", 18, "GreEN", True, "F-20"),
	array("f", 18, "Red", True, "F-21+"),
	array("f", 18, "Blue", True, "M-21+"),
	array("f", 20, "Brown", False, ""),
	array("f", 20, "Brown", True, "F Brown"),
	array("f", 20, "Green", True, "F-20"),
	array("f", 20, "Red", True, "F-21+"),
	array("f", 20, "Blue", True, "M-21+"),
	array("f", 25, "GREEn", False, ""),
	array("f", 25, "GREEn", True, "F Green"),
	array("f", 25, "Brown", True, "F Brown"),
	array("f", 25, "Red", True, "F-21+"),
	array("f", 25, "Blue", True, "M-21+"),
	array("f", 35, "Blue", True, "M-21+"),
	array("f", 35, "Red", True, "F-21+"),
	array("f", 35, "Green", True, "F35+"),
	array("f", 35, "Brown", True, "F Brown"),
	array("f", 35, "Orange", True, "F Orange"),
	array("f", 45, "Blue", True, "M-21+"),
	array("f", 45, "Red", True, "F-21+"),
	array("f", 45, "Green", True, "F45+"),
	array("f", 45, "Brown", False, ""),
	array("f", 45, "Orange", False, "F Orange"),
	array("f", 54, "Blue", True, "M-21+"),
	array("f", 54, "Red", True, "F-21+"),
	array("f", 54, "Green", True, "F50+"),
	array("f", 54, "Brown", True, "F Brown"),
	array("f", 54, "Orange", True, "F Orange"),
	array("f", 55, "Blue", True, "M-21+"),
	array("f", 55, "Red", True, "F-21+"),
	array("f", 55, "Green", True, "F50+"),
	array("f", 55, "Brown", True, "F55+"),
	array("f", 55, "Orange", True, "F Orange"),
	array("f", 68, "Blue", True, "M-21+"),
	array("f", 68, "Red", True, "F-21+"),
	array("f", 68, "Green", True, "F50+"),
	array("f", 68, "Brown", True, "F65+"),
	array("f", 68, "Orange", True, "F Orange"),
	array("f", 68, "Yellow", False, "F Yellow"),
	array("m", 5, "White", False, "M-10"),
	array("m", 10, "White", False, "M-10"),
	array("m", 12, "White", False, "M-12"),
	array("m", 13, "Yellow", False, "M-14"),
	array("m", 14, "Yellow", False, "M-14"),
	array("m", 14, "White", False, "MF White"),
	array("m", 14, "Orange", False, "M-16"),
	array("m", 16, "Orange", False, "M-16"),
	array("m", 16, "Brown", False, ""),
	array("m", 16, "Brown", True, "M Brown"),
	array("m", 16, "Green", True, "M-18"),
	array("m", 16, "Red", True, "M-20"),
	array("m", 16, "Yellow", False, "M Yellow"),
	array("m", 18, "YELlow", False, "M Yellow"),
	array("m", 5, "White", True, "M-10"),
	array("m", 10, "White", True, "M-10"),
	array("m", 12, "White", True, "M-12"),
	array("m", 13, "Yellow", True, "M-14"),
	array("m", 14, "Yellow", True, "M-14"),
	array("m", 14, "White", True, "MF White"),
	array("m", 14, "Orange", True, "M-16"),
	array("m", 16, "Orange", True, "M-16"),
	array("m", 16, "Brown", False, ""),
	array("m", 18, "Brown", False, ""),
	array("m", 18, "Brown", True, "M Brown"),
	array("m", 18, "GreEN", True, "M-18"),
	array("m", 18, "Red", True, "M-20"),
	array("m", 18, "Blue", True, "M-21+"),
	array("m", 20, "Brown", False, ""),
	array("m", 20, "Brown", True, "M Brown"),
	array("m", 20, "Green", True, "M Green"),
	array("m", 20, "Red", True, "M-20"),
	array("m", 20, "Blue", True, "M-21+"),
	array("m", 25, "GREEn", False, ""),
	array("m", 25, "GREEn", True, "M Green"),
	array("m", 25, "Brown", True, "M Brown"),
	array("m", 25, "Red", True, "M Red"),
	array("m", 25, "Blue", True, "M-21+"),
	array("m", 35, "Blue", True, "M-21+"),
	array("m", 35, "Red", True, "M35+"),
	array("m", 35, "Green", True, "M Green"),
	array("m", 35, "Brown", True, "M Brown"),
	array("m", 35, "Orange", True, "M Orange"),
	array("m", 45, "Blue", True, "M-21+"),
	array("m", 45, "Red", True, "M45+"),
	array("m", 45, "Green", True, "M Green"),
	array("m", 45, "Brown", False, ""),
	array("m", 45, "Orange", False, "M Orange"),
	array("m", 54, "Blue", True, "M-21+"),
	array("m", 54, "Red", True, "M45+"),
	array("m", 54, "Green", True, "M50+"),
	array("m", 54, "Brown", True, "M Brown"),
	array("m", 54, "Orange", True, "M Orange"),
	array("m", 55, "Blue", True, "M-21+"),
	array("m", 55, "Red", True, "M45+"),
	array("m", 55, "Green", True, "M55+"),
	array("m", 55, "Brown", True, "M Brown"),
	array("m", 55, "Orange", True, "M Orange"),
	array("m", 68, "Blue", True, "M-21+"),
	array("m", 68, "Red", True, "M45+"),
	array("m", 68, "Green", True, "M60+"),
	array("m", 68, "Brown", True, "M65+"),
	array("m", 68, "Orange", True, "M Orange"),
	array("m", 68, "Yellow", False, "M Yellow"),
	array("o", 68, "Yellow", False, ""),
	array("o", 15, "Red", True, ""),
	array("m", 55, "LongWhite", False, ""),
	array("m", 48, "Find40", True, ""),
	array("f", 18, "ScoreO_Find20", False, ""),
	array("f", 35, "Tan", True, "")
);
                           

$correct_classification_tests = 0;
foreach ($classification_test_data as $classification_test_entry) {
  $class_identified = find_best_class($class_info, $classification_test_entry[0], $classification_test_entry[1],
		                                   $classification_test_entry[2], $classification_test_entry[3]);
  $formatted_info = "{$classification_test_entry[0]}, {$classification_test_entry[1]} on {$classification_test_entry[2]} : ";
  $formatted_info .= ($classification_test_entry[3] ? "SI" : "QR");
  $formatted_info .= " -> {$classification_test_entry[4]}";
  if ($class_identified != $classification_test_entry[4]) {
    echo "Did not correctly identify class, entry is {$formatted_info}, returned: {$class_identified}\n";
    $failure = true;
  }
  else {
    if ($show_all_classification_tests) {
      echo "Got correct classification for ({$formatted_info})\n";
    }
    else {
      $correct_classification_tests++;
    }
  }
}

if (!$show_all_classification_tests) {
  echo "Got {$correct_classification_tests} correct classifications, all passed.\n";
}

function validate_encode_decode_classification($birth_year, $gender, $class) {
  $return_code = true;
  $encoded_info = encode_entrant_classification_info($birth_year, $gender, $class);
  $decoded_info = decode_entrant_classification_info($encoded_info);

  if ($decoded_info["BY"] != $birth_year) {
    echo "Encode / decode of birth year failed: {$birth_year} supplied, {$decoded_info["BY"]} returned.\n";
    $return_code = false;
  }

  if ($decoded_info["G"] != $gender) {
    echo "Encode / decode of gender failed: {$gender} supplied, {$decoded_info["G"]} returned.\n";
    $return_code = false;
  }

  if ($decoded_info["CLASS"] != $class) {
    echo "Encode / decode of clas failed: {$class} supplied, {$decoded_info["CLASS"]} returned.\n";
    $return_code = false;
  }

  return($return_code);
}

if (!validate_encode_decode_classification("1967", "m", "")) {
  $failure = true;
}

if (!validate_encode_decode_classification("1968", "f", "")) {
  $failure = true;
}

if (!validate_encode_decode_classification("2001", "f", "F-21")) {
  $failure = true;
}

if (!validate_encode_decode_classification("2005", "o", "MF White")) {
  $failure = true;
}

if ($failure) {
  mkdir("../OMeetData/TestingDirectory");
  mkdir("../OMeetData/TestingDirectory/UnitTestingEvent");
}
else {
  echo "NRE class handling passed successfully\n";
}

?>
