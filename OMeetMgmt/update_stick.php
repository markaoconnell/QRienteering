<?php
require '../OMeetCommon/common_routines.php';

ck_testing();


$event = $_GET["event"];
$key = $_GET["key"];

if (($event == "") || (!key_is_valid($key))) {
  error_and_exit("Empty event \"{$event}\" or bad location key \"{$key}\", is this an unauthorized link?\n");
}

if (!file_exists(get_event_path($event, $key, ".."))) {
  error_and_exit("No such event \"{$event}\", is this an authorized link?\n");
}

$competitor_id = $_GET["competitor"];
if (!isset($_GET["competitor"]) || ($competitor_id == "")) {
  error_and_exit("No such competitor \"{$competitor_id}\", is this an authorized link?\n");
}

$new_stick = $_GET["new_stick"];
$new_stick_provided = (isset($_GET["new_stick"]) && ($new_stick != ""));

$competitor_path = get_competitor_path($competitor_id, $event, $key, "..");
$competitor_name = file_get_contents("{$competitor_path}/name");
if (file_exists("{$competitor_path}/si_stick")) {
  $competitor_stick = file_get_contents("{$competitor_path}/si_stick");
}
else {
  $competitor_stick = "none";
}


$output_string = "";
if (!$new_stick_provided) {
  $output_string = "<p>Update Si unit for {$competitor_name}<p>Enter \"none\" to remove the current si unit.\n";
  $output_string .= "<form action=./update_stick.php method=put>\n";
  $output_string .= "<input type=text name=new_stick value=\"{$competitor_stick}\">\n";
  $output_string .= "<input type=hidden name=key value=\"{$key}\">\n";
  $output_string .= "<input type=hidden name=event value=\"{$event}\">\n";
  $output_string .= "<input type=hidden name=competitor value=\"{$competitor_id}\">\n";
  $output_string .= "<input type=submit name=submit value=\"Update si unit number\">\n</form>\n";
}
else {
  $output_string = "<p>Updating Si unit for {$competitor_name}\n";
  if ($new_stick != "none") {
    if (!file_exists("{$competitor_path}/si_stick")) {
      // User was a QR runner - remove the start time if it is there, error if there are any punches
      $punches = scandir("{$competitor_path}/controls_found");
      $punches = array_diff($punches, array(".", "..", "start"));
      if (count($punches) != 0) {
        error_and_exit("User is a QR code user with active punches, cannot add a SI stick now.");
      }
      else {
        if (file_exists("{$competitor_path}/controls_found/start")) {
	   unlink("{$competitor_path}/controls_found/start");
        }
      }
    }
    else {
      clear_stick_xlation($event, $key, file_get_contents("{$competitor_path}/si_stick"));
    }

    file_put_contents("{$competitor_path}/si_stick", $new_stick);
    put_stick_xlation($event, $key, $competitor_id, $new_stick);
  }
  else {
    if (file_exists("{$competitor_path}/si_stick")) {
      clear_stick_xlation($event, $key, file_get_contents("{$competitor_path}/si_stick"));
      unlink("{$competitor_path}/si_stick");
    }
  }

  $output_string .= "<p>Updated SI unit from \"{$competitor_stick}\" to \"{$new_stick}\"\n";
}



echo get_web_page_header(true, false, true);

echo $output_string;

echo get_web_page_footer();
?>
