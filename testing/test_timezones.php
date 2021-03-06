<?php

require '../OMeetCommon/common_routines.php';

$failure = false;

// Clean up after prior invocations of the test
if (file_exists("../keys")) {
  unlink("../keys");
}


// Create the key file and a timezone file
file_put_contents("../keys", "valid_key,NEOC_BYOM,123456789\nkey2,NEOC_Meets,55555\nToriMeets,Tori/Acton,kdkdkd\n");
mkdir("../OMeetData/NEOC_BYOM", 0755, true);
mkdir("../OMeetData/NEOC_Meets", 0755, true);
file_put_contents("../OMeetData/NEOC_BYOM/timezone.txt", "America/Vancouver");  // key is valid_key
file_put_contents("../timezone.txt", "America/Denver");                         // default
file_put_contents("../OMeetData/NEOC_Meets/timezone.txt", "malformatted/timezone");          // key is key2

set_timezone("valid_key");
$current_tz = date_default_timezone_get();
if ($current_tz != "America/Vancouver") {
  echo "ERROR: got {$current_tz} instead of \"America/Vancouver\" for key \"valid_key\".\n";
  $failure = true;
}


set_timezone("key2");
$current_tz = date_default_timezone_get();
if ($current_tz != "America/New_York") {
  echo "ERROR: got {$current_tz} instead of \"America/New_York\" for key \"key2\" (has invalid timezone spec).\n";
  $failure = true;
}


set_timezone("no_such_key");
$current_tz = date_default_timezone_get();
if ($current_tz != "America/Denver") {
  echo "ERROR: got {$current_tz} instead of \"America/Denver\" for key \"no_such_key\".\n";
  $failure = true;
}


set_timezone("ToriMeets");
$current_tz = date_default_timezone_get();
if ($current_tz != "America/Denver") {
  echo "ERROR: got {$current_tz} instead of \"America/Denver\" for key \"ToriMeets\" (no timezone file present).\n";
  $failure = true;
}

unlink("../timezone.txt");

set_timezone("ToriMeets");
$current_tz = date_default_timezone_get();
if ($current_tz != "America/New_York") {
  echo "ERROR: got {$current_tz} instead of \"America/New_York\" for key \"ToriMeets\" (no timezone file present and no default for the site).\n";
  $failure = true;
}




if ($failure) {
  mkdir("../OMeetData/TestingDirectory");
  mkdir("../OMeetData/TestingDirectory/UnitTestingEvent");
}
else {
  unlink("../keys");
  unlink("../OMeetData/NEOC_BYOM/timezone.txt");
  // unlink("../timezone.txt");   // Already unlinked above
  unlink("../OMeetData/NEOC_Meets/timezone.txt");

  rmdir("../OMeetData/NEOC_BYOM");
  rmdir("../OMeetData/NEOC_Meets");

  echo "Test timezone management passed successfully\n";
}

?>
