<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetMgmt/qrcode.php';

ck_testing();



$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key, ".."))) {
  error_and_exit("No event directory found, please contact administrator to create \"{$base_path}\"");
}

$event = $_GET["event"];

$existing_event_path = get_event_path($event, $key, "..");
if (!is_dir($existing_event_path)) {
  error_and_exit("Event not found, is \"{$key}\" and \"{$event}\" a valid pair?\n");
}

$existing_event_name = file_get_contents("{$existing_event_path}/description");

$qr_code_value = $_GET["qr_code"];
if ($qr_code_value != "") {
  $qr = QRCode::getMinimumQRCode($qr_code_value, QR_ERROR_CORRECT_LEVEL_L);
      
  $size_of_image = 10;
  $im = $qr->createImage($size_of_image, 4);
      
  $temp_image_file = tempnam(sys_get_temp_dir(), "qrcode-");
  imagegif($im, $temp_image_file);
  imagedestroy($im);
  
  // We have the image, now return it as an image
  header("Content-type: image/gif");
  readfile($temp_image_file);
  
  unlink($temp_image_file);
}
else {
  error_and_exit("No qr code image specified");
}
?>
