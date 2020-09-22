<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetMgmt/qrcode.php';

ck_testing();



//$key = $_POST["key"];
//if (!key_is_valid($key)) {
//  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
//}
//
//if (!is_dir(get_base_path($key, ".."))) {
//  error_and_exit("No event directory found, please contact administrator to create \"{$base_path}\"");
//}
//
//$event = $_POST["event"];
//
//$existing_event_path = get_event_path($event, $key, "..");
//if (!is_dir($existing_event_path)) {
//  error_and_exit("Event not found, is \"{$key}\" and \"{$event}\" a valid pair?\n");
//}
//
//$existing_event_name = file_get_contents("{$existing_event_path}/description");

$qr_code_files = array();
foreach (array_keys($_POST) as $posted_item) {
  if (substr($posted_item, 0, 3) == "qr-") {
    $qr_code_name = base64_decode(substr($posted_item, 3));
    $qr_code_value = $_POST[$posted_item];

    $qr = QRCode::getMinimumQRCode($qr_code_value, QR_ERROR_CORRECT_LEVEL_L);
    
    $size_of_image = 10;
    $im = $qr->createImage($size_of_image, 4);
    
    //header("Content-type: image/gif");
    $temp_image_file = tempnam(sys_get_temp_dir(), "qrcode-");
    imagegif($im, $temp_image_file);
    imagedestroy($im);
    $qr_code_files[$qr_code_name] = $temp_image_file;
    }
}

if ($_POST["style"] == "html") {
  $html_page_break = "<p style=\"page-break-after: always;\">&nbsp;</p>\n" .
                     "<p style=\"page-break-before: always;\">&nbsp;</p>";
  echo get_web_page_header(true, false, false);
  echo "<table>\n";
  foreach (array_keys($qr_code_files) as $qr_code_name) {
    $temp_image_file = $qr_code_files[$qr_code_name];
    $base64_image_data = base64_encode(file_get_contents($temp_image_file));
    unlink($temp_image_file);
    
    echo "<tr><td>\n<p style=\"font-size: 500%;\">$qr_code_name<br>\n<img src=\"data:image/png;base64,\n$base64_image_data\">\n{$html_page_break}\n</td></tr>\n";
  }
  echo "</table>\n";
  echo get_web_page_footer();
}
else if ($_POST["style"] == "zipfile") {
  $zipfile = new ZipArchive();
  $zipfilename = tempnam(sys_get_temp_dir(), "zip-");
  if ($zipfile->open($zipfilename, ZipArchive::CREATE) !== true) {
    error_and_exit("Opening of zipfile {$zipfilename} failed.\n");
  }

  foreach (array_keys($qr_code_files) as $qr_code_name) {
    $temp_image_file = $qr_code_files[$qr_code_name];
    $zipfile->addFile($temp_image_file, "./{$qr_code_name}.gif");
  }
  $zipfile->close();
  header('Content-disposition: attachment; filename=qrcodes.zip');
  header('Content-type: application/zip');
  readfile($zipfilename);

  // If the cleanup of the temporary files is done earlier, then the closing
  // of the zip file fails - it must only read the files when closing.
  unlink($zipfilename);
  foreach (array_keys($qr_code_files) as $qr_code_name) {
    $temp_image_file = $qr_code_files[$qr_code_name];
    unlink($temp_image_file);
  }
}
else {
  error_and_exit("Unknown download option {$_POST["style"]}");
}
?>
