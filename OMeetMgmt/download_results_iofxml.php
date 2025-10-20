<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/time_routines.php';
require '../OMeetCommon/nre_routines.php';
require '../OMeetCommon/results_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

function add_xml_attribute($xw, $attr_name, $attr_value) {
  xmlwriter_start_attribute($xw, $attr_name);
  xmlwriter_text($xw, $attr_value);
  xmlwriter_end_attribute($xw);
}

function add_text_element($xw, $attr_name, $attr_value) {
  xmlwriter_start_element($xw, $attr_name);
  xmlwriter_text($xw, $attr_value);
  xmlwriter_end_element($xw);
}

function iso8601($input_time) {
    $date = date('Y-m-d\TH:i:sO', $input_time);
    return (substr($date, 0, strlen($date)-2).':'.substr($date, -2));
}

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$download_csv = !isset($_GET["show_as_html"]);

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

set_timezone($key);

$xw = xmlwriter_open_memory();
xmlwriter_set_indent($xw, 1);
$res = xmlwriter_set_indent_string($xw, ' ');

xmlwriter_start_document($xw, '1.0', 'UTF-8');

xmlwriter_start_element($xw, 'ResultList');
add_xml_attribute($xw, "xmlns", "http://www.orienteering.org/datastandard/3.0");
add_xml_attribute($xw, "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
add_xml_attribute($xw, "iofVersion", "3.0");
add_xml_attribute($xw, "creator", "QRienteering from Mark OConnell");
add_xml_attribute($xw, "status", "Complete");
add_xml_attribute($xw, "createTime", iso8601(time()));

xmlwriter_start_element($xw, "Event");
$event_name = file_get_contents(get_event_path($event, $key) . "/description");
add_text_element($xw, "Name", $event_name);
xmlwriter_end_element($xw);  // Event


$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));

$start_number = 1;
$course_number = 1;
foreach ($course_list as $one_course) {
  $readable_course_name = ltrim($one_course, "0..9-");
  $course_properties = get_course_properties("{$courses_path}/{$one_course}");
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  if ($score_course) {
    $max_score = $course_properties[$MAX_SCORE_FIELD];
  }

  xmlwriter_start_element($xw, "ClassResult");
  xmlwriter_start_element($xw, "Class");
  add_text_element($xw, "Id", "{$course_number}");
  add_text_element($xw, "Name", "{$readable_course_name}");
  xmlwriter_end_element($xw); // Class of ClassResult
  
  $results_array = get_course_results_as_array($event, $key, $one_course, $score_course, $max_score, "..");
  $place = 1;
  foreach ($results_array as $this_result) {
    // If the splits array is empty, there is an error - most likely a self reported result with
    // no splits available, so just skip it.
    $splits_array = get_splits_for_download($this_result["competitor_id"], $event, $key);
    if (count($splits_array) == 0) {
      continue;
    }

    $first_space_pos = strpos($this_result["competitor_name"], " ");
    if ($first_space_pos !== false) {
      $first_name = substr($this_result["competitor_name"], 0, $first_space_pos);
      $last_name = substr($this_result["competitor_name"], $first_space_pos + 1);
    }
    else {
      $first_name = "no-first-name";
      $last_name = $this_result["competitor_name"];
    }

    if ($place == 1) {
      $winning_time = $this_result["raw_time"];
    }

    xmlwriter_start_element($xw, "PersonResult");
    xmlwriter_start_element($xw, "Person");
    add_text_element($xw, "Id", "{$start_number}");
    xmlwriter_start_element($xw, "Name");
    add_text_element($xw, "Family", "{$last_name}");
    add_text_element($xw, "Given", "{$first_name}");
    xmlwriter_end_element($xw); // Name of Person of PersonResult
    xmlwriter_end_element($xw); // Person of PersonResult

    xmlwriter_start_element($xw, "Result");
    add_text_element($xw, "StartTime",  iso8601($splits_array["start"]));
    add_text_element($xw, "FinishTime",  iso8601($splits_array["finish"] - $splits_array["forgiven_time"]));
    add_text_element($xw, "Time", ltrim($this_result["raw_time"], "0"));
    add_text_element($xw, "Position", "{$place}");
    add_text_element($xw, "Status", $this_result["dnf"] ? "MissingPunch" : "OK");
    $time_behind = $this_result["raw_time"] - $winning_time;
//    Livelox at least doesn't seem to like this for reasons I don't understand, even though it is in the spec
//    add_text_element($xw, "TimeBehind", "{$time_behind}");


    foreach ($splits_array["controls"] as $this_split) {
      xmlwriter_start_element($xw, "SplitTime");
      if (isset($this_split["missed"])) {
	add_xml_attribute($xw, "status", "Missing");
        add_text_element($xw, "ControlCode", $this_split["control_id"]);
      }
      else {
        // For some reason, it looks like the ControlCode field is supposed to come before
        // the Time field, even though I didn't think the order should matter in an xml
        // doc.  At least LiveLox works this way, even though WinSplits doesn't.
        add_text_element($xw, "ControlCode", $this_split["control_id"]);
        add_text_element($xw, "Time", $this_split["cumulative_time"]);
      } 
      xmlwriter_end_element($xw); // SplitTime
    }

    xmlwriter_end_element($xw); // Result of PersonResult
    xmlwriter_end_element($xw); // PersonResult
    $start_number++;
    $place++;
  }
  xmlwriter_end_element($xw); // ClassResult
  $course_number++;
}

xmlwriter_end_element($xw);  // end the ResultsList
xmlwriter_end_document($xw);

if ($download_csv) {
  header('Content-disposition: attachment; filename=results.xml');
  header('Content-type: application/octet-stream');
}
else {
  echo get_web_page_header(true, true, false);
  echo "<pre>\n";
}

echo xmlwriter_output_memory($xw);

if (!$download_csv) {
  echo "</pre>\n";
  echo get_web_page_footer();
}

?>
