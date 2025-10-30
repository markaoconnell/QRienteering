<?php

require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/time_routines.php';

$test_cases = array(
  "3600" => 3600,
  "3600s" => 3600,
  "60m" => 3600,
  "60m30s" => 3630,
  "1h" => 3600,
  "1h0m0s" => 3600,
  "1h30m" => 5400,
  "90m" => 5400,
  "2h3600s" => 10800,
  "120m" => 7200,
  "24h12m30s" => 87150,
  "24h12h" => -1,
  "1h12m5m" => -1,
  "1s12s5m" => -1,
  "60s1m1h" => -1,
  "Totally wrong" => -1,
  "1h 30m" => 5400,
  "24h 12m 30s  " => 87150,
  "1h 10s and no more" => -1,
  "1h1rm" => -1,
  "five hours" => -1,
  "1d" => 86400,
  "3d" => 259200,
  "1M" => 2592000,
  "2M" => 5184000,
  "1M14d" => 3801600,
  "1d12h" => 129600,
  "45:30" => 2730,
  "90:22" => 5422,
  "2:01:01" => 7261,
  "2:1:1"=> 7261,
  "36h" => 129600
);

$failure = false;

foreach (array_keys($test_cases) as $test_key) {
  $result = time_limit_to_seconds($test_key);
  if ($result != $test_cases[$test_key]) {
    echo "ERROR: Wrong results for {$test_key}, did not produce {$test_cases[$test_key]}, got $result instead.\n";
    $failure = true;
  }
}

if ($failure) {
  mkdir("../OMeetData/TestingDirectory");
}
else {
  echo "Test time parsing passed successfully\n";
}

?>
