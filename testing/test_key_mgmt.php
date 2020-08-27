<?php

require '../OMeetCommon/common_routines.php';

$failure = false;

// Clean up after prior invocations of the test
if (file_exists("../keys")) {
  unlink("../keys");
}

// Test the legacy cases
// no keys file present
if (key_is_valid("")) {
  echo "ERROR: Empty key, with no key file, returned valid.\n";
  $failure = true;
}
else {
  echo "Success: empty key with no key file\n";
}

if (key_is_valid("valid_key")) {
  echo "ERROR: \"valid_key\", with no key file, returned valid.\n";
  $failure = true;
}
else {
  echo "Success: valid_key is bad\n";
}

if (key_password_ok("", "")) {
  echo "ERROR: Empty key and password, with no key file, returned ok.\n";
  $failure = true;
}
else {
  echo "Success: empty key and password ok.\n";
}

if (key_password_ok("", "unused")) {
  echo "ERROR: Empty key, with no key file and \"unused\" as password, returned ok.\n";
  $failure = true;
}
else {
  echo "Success: empty key and \"unused\" password ok.\n";
}

if (key_password_ok("NEOC_BYOM", "unused")) {
  echo "ERROR: NEOC_BYOM key, with no key file and \"unused\" as password, returned good password.\n";
  $failure = true;
}
else {
  echo "Success: key of NEOC_BYOM and \"unused\" password failed.\n";
}

// Create the key file
file_put_contents("../keys", "valid_key,NEOC_BYOM,123456789\nkey2,NEOC_Meets,55555\nToriMeets,Tori/Acton,kdkdkd\n");

if (key_is_valid("valid_key")) {
  echo "ERROR: \"valid_key\", with key file but no reset, returned valid.\n";
  $failure = true;
}
else {
  echo "Success: key of valid_key failed.\n";
}

key_reset();

if (!key_is_valid("valid_key")) {
  echo "ERROR: \"valid_key\", with key file after reset, returned invalid.\n";
  $failure = true;
}
else {
  echo "Success: key of valid_key now valid.\n";
}

if (!key_is_valid("key2")) {
  echo "ERROR: \"key2\", with key file after reset, returned invalid.\n";
  $failure = true;
}
else {
  echo "Success: key of key2 now valid.\n";
}

if (!key_is_valid("ToriMeets")) {
  echo "ERROR: \"ToriMeets\", with key file after reset, returned invalid.\n";
  $failure = true;
}
else {
  echo "Success: key of ToriMeets now valid.\n";
}

if (key_is_valid("NotYetThere")) {
  echo "ERROR: \"NotYetThere\", with key file after reset, returned valid.\n";
  $failure = true;
}
else {
  echo "Success: key of NotYetThere invalid.\n";
}

if (!key_password_ok("valid_key", "123456789")) {
  echo "ERROR: \"valid_key\", with good password \"123456789\", did not pass validity check.\n";
  $failure = true;
}
else {
  echo "Success: key of valid_key password ok.\n";
}

if (key_password_ok("valid_key", "kdkd")) {
  echo "ERROR: \"valid_key\", with bad password \"kdkd\", did pass validity check.\n";
  $failure = true;
}
else {
  echo "Success: key of valid_key password bad.\n";
}

if (!key_password_ok("ToriMeets", "kdkdkd")) {
  echo "ERROR: \"ToriMeets\", with good password \"kdkdkd\", did not pass validity check.\n";
  $failure = true;
}
else {
  echo "Success: key of ToriMeets password ok.\n";
}

$testing_values  = array (
                     array("Hale2020",
                           "key2",
                           "../OMeetData/NEOC_Meets/Hale2020/Courses",
                           "../OMeetData/NEOC_Meets/Hale2020/Competitors",
                           "../OMeetData/NEOC_Meets/Hale2020/Results"),
                     array("NobscotMay2020",
                           "key2",
                           "../OMeetData/NEOC_Meets/NobscotMay2020/Courses",
                           "../OMeetData/NEOC_Meets/NobscotMay2020/Competitors",
                           "../OMeetData/NEOC_Meets/NobscotMay2020/Results"),
                     array("LegacyEvent",
                           "",
                           "../OMeetData//LegacyEvent/Courses",
                           "../OMeetData//LegacyEvent/Competitors",
                           "../OMeetData//LegacyEvent/Results"),
                     array("NobscotMay2020",
                           "valid_key",
                           "../OMeetData/NEOC_BYOM/NobscotMay2020/Courses",
                           "../OMeetData/NEOC_BYOM/NobscotMay2020/Competitors",
                           "../OMeetData/NEOC_BYOM/NobscotMay2020/Results"),
                     array("ActonBoxborough",
                           "ToriMeets",
                           "../OMeetData/Tori/Acton/ActonBoxborough/Courses",
                           "../OMeetData/Tori/Acton/ActonBoxborough/Competitors",
                           "../OMeetData/Tori/Acton/ActonBoxborough/Results"));

foreach ($testing_values as $test_array) {
  if (get_courses_path($test_array[0], $test_array[1], "..") != $test_array[2]) {
    echo "ERORR: get courses for {$test_array[0]} and {$test_array[1]} did not return {$test_array[2]}.\n";
    $failure = true;
  }
  else {
    echo "Success: get_courses with {$test_array[0]} and {$test_array[1]}.\n";
  }

  if (get_competitor_directory($test_array[0], $test_array[1], "..") != $test_array[3]) {
    echo "ERORR: get competitors directory for {$test_array[0]} and {$test_array[1]} did not return {$test_array[3]}.\n";
    $failure = true;
  }
  else {
    echo "Success: get_competitor_directory with {$test_array[0]} and {$test_array[1]}.\n";
  }

  if (get_competitor_path($test_array[0] . $test_array[1], $test_array[0], $test_array[1], "..") != ($test_array[3] . "/" . $test_array[0] . $test_array[1])) {
    echo "ERORR: get competitor for {$test_array[0]} and {$test_array[1]} did not return {$test_array[3]}/{$test_array[0]}{$test_array[1]}.\n";
    $failure = true;
  }
  else {
    echo "Success: get_competitor_path with {$test_array[0]} and {$test_array[1]}.\n";
  }

  if (get_results_path($test_array[0], $test_array[1], "..") != $test_array[4]) {
    echo "ERORR: get results path for {$test_array[0]} and {$test_array[1]} did not return {$test_array[4]}.\n";
    $failure = true;
  }
  else {
    echo "Success: get_results with {$test_array[0]} and {$test_array[1]}.\n";
  }
}


if ($failure) {
  mkdir("../OMeetData/TestingDirectory");
  mkdir("../OMeetData/TestingDirectory/UnitTestingEvent");
}
else {
  unlink("../keys");
  echo "Test key management passed successfully\n";
}

?>
