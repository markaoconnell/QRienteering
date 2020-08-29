<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$expecting_cookie = isset($_GET["expecting_cookie"]);
$cookie_found = isset($_COOKIE["QRienteering_test_cookie"]);

// Set the cookie for testing purposes
$current_time = time();
$timeout_value = $current_time + 3600;  // 1 hour timeout, should be fine for testing
$cookie_path = dirname(dirname($_SERVER["REQUEST_URI"]));
setcookie("QRienteering_test_cookie", "Testing cookie for QRienteering", $timeout_value, $cookie_path);


$body_string = "";

if ($cookie_found) {
  if ($expecting_cookie) {
    $body_string = "<p>Cookie test succeeded!  All seems good.\n";
  }
  else {
    $body_string = "<p>Cookie test succeeded unexpectedly, is this a second try for the test?\n" .
                   "<p>All seems good.\n";
  }

  set_success_background();
}
else if ($expecting_cookie && !$cookie_found) {
  $body_string = "<p>Cookie not found, please see troubleshooting guide for suggestions.\n";
  set_error_background();
}
else {
  $body_string = "<p>Hit the \"Test Cookie\" button to validate that your phone/browser has the necessary " .
                 "cookie support for the QR code orienteering.\n";
  $body_string .= "<p><p><form action=\"./cookie_test.php\">\n" .
                  "<input type=\"hidden\" name=\"expecting_cookie\" value=\"1\">\n" .
                  "<input type=\"submit\" value=\"Test Cookie\">\n</form>\n";
}

echo get_web_page_header(true, false, true);

echo $body_string;

echo get_web_page_footer();
?>
