<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetMgmt/qrcode.php';

ck_testing();


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
