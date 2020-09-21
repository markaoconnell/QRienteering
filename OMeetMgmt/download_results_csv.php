<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require '../OMeetCommon/generate_splits_output.php';

ck_testing();

// Get the submitted info
// echo "<p>\n";
$event = $_GET["event"];
$key = $_GET["key"];
$download_csv = true;

if ($event == "") {
  error_and_exit("<p>ERROR: Event not specified, no results can be shown.\n");
}

$courses_path = get_courses_path($event, $key, "..");
if (!file_exists($courses_path)) {
  error_and_exit("<p>ERROR: No such event found {$event} (or bad location key {$key}).\n");
}

echo get_web_page_header(true, true, false);

if ($download_csv) {
  echo "<pre>\n";
}

// Do the header line
// Oddity #1: WinSplits doesn't seem happy if this line is not there, I don't know why
echo "Stno;SI card;Database Id;Surname;First name;YB;S;Block;nc;Start;Finish;Time;Classifier;Club no.;Cl.name;City;Nat;Cl. no.;Short;Long;Num1;Num2;Num3;Text1;Text2;Text3;Adr. name;Street;Line2;Zip;City;Phone;Fax;EMail;Id/Club;Rented;Start fee;Paid;Course no.;Course;km;m;Course controls;Pl;Start punch;Finish punch;Control1;Punch1;Control2;Punch2;Control3;Punch3;Control4;Punch4;Control5;Punch5;Control6;Punch6;Control7;Punch7;Control8;Punch8;Control9;Punch9;Control10;Punch10;(may be more) ...\n";

$course_list = scandir($courses_path);
$course_list = array_diff($course_list, array(".", ".."));

$start_number = 1;
$course_number = 1;
foreach ($course_list as $one_course) {
  $readable_course_name = ltrim($one_course, "0..9-");
  $course_properties = get_course_properties("{$courses_path}/{$one_course}");
  $controls_on_course = read_controls("{$courses_path}/{$one_course}/controls.txt");
  $number_controls = count($controls_on_course);
  $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));
  $max_score = 0;
  if ($score_course) {
    $max_score = $course_properties[$MAX_SCORE_FIELD];
  }
  
  $results_array = get_results_as_array($event, $key, $one_course, $score_course, $max_score, "..");
  $place = 1;
  foreach ($results_array as $this_result) {
    $splits_array = get_splits_as_array($this_result["competitor_id"], $event, $key);
    $first_space_pos = strpos($this_result["competitor_name"], " ");
    if ($first_space_pos !== false) {
      $first_name = substr($this_result["competitor_name"], 0, $first_space_pos);
      $last_name = substr($this_result["competitor_name"], $first_space_pos + 1);
    }
    else {
      $first_name = "no-first-name";
      $last_name = $this_result["competitor_name"];
    }

    $si_stick = "124816";  // Improve this
    $csv_array = array();
    $csv_array[] = $start_number;
    $csv_array[] = $si_stick + $start_number;
    $csv_array[] = ""; // Database ID
    $csv_array[] = "\"{$last_name}\"";  // Surname
    $csv_array[] = "\"{$first_name}\"";  // First name
    $csv_array[] = "\"\""; // Year of Birth
    $csv_array[] = " "; // Gender
    $csv_array[] = ""; // Block
    $csv_array[] = "0"; // NC
    $csv_array[] = strftime("%T", $splits_array["start"]);  // Should be HH:MM:SS
    $csv_array[] = strftime("%T", $splits_array["finish"]); // Should be HH:MM:SS
    $csv_array[] = trim($this_result["time"]);
    $csv_array[] = $this_result["dnf"] ? "2" : "0"; // 2 = DNF, 0 = good - Classifier
    $csv_array[] = "1"; // Club number
    $csv_array[] = "\"\""; // Club name
    $csv_array[] = "\"NEOC\"";  // City
    $csv_array[] = "\"\""; // Nationality
    $csv_array[] = "\"\""; // Class number
    $csv_array[] = $readable_course_name; // Short course name
    $csv_array[] = $readable_course_name; // Long course name
    $csv_array[] = "";  // NuC1
    $csv_array[] = "";  // NuC2
    $csv_array[] = "";  // NuC3
    $csv_array[] = "\"\"";  // Text1
    $csv_array[] = "\"\"";  // Text2
    $csv_array[] = "\"\"";  // Text3
    $csv_array[] = "\"\"";  // Address name
    $csv_array[] = "\"\"";  // Street
    $csv_array[] = "\"\"";  // Line 2
    $csv_array[] = "\"\"";  // Zip
    $csv_array[] = "\"\"";  // City
    $csv_array[] = "\"\"";  // Phone
    $csv_array[] = "\"\"";  // Fax
    $csv_array[] = "\"\"";  // Email
    $csv_array[] = "";  // Id/Club
    $csv_array[] = "0";  // Rented
    $csv_array[] = "0";  // start fee
    $csv_array[] = "0";  // paid
    $csv_array[] = "$course_number";  // course no
    $csv_array[] = $readable_course_name;  // course
    $csv_array[] = "0"; // course km
    $csv_array[] = ""; // course m
    $csv_array[] = $number_controls; // course controls
    $csv_array[] = $place; // place
    $csv_array[] = strftime("%T", $splits_array["start"]);  // Should be HH:MM:SS
    $csv_array[] = strftime("%T", $splits_array["finish"]); // Should be HH:MM:SS
    $winsplits_csv_line = implode(";", $csv_array);
    $winsplits_csv_line .= ";" . implode(";", array_map(function($elt) { return ($elt["control_id"] . ";" . trim(csv_formatted_time($elt["cumulative_time"]))); },
                                                        $splits_array["controls"]));
    // Need to add in controls which were not visited
    // Should really factor in the controls from the extra list - for later
    if ($number_controls > count($splits_array["controls"])) {
      $unvisited_controls = array_slice($controls_on_course, count($splits_array["controls"]));
      if (count($splits_array["controls"]) > 0) {
        // If no controls were found, then there is already an appropriate semicolon from the last implode (for the found controls)
        $winsplits_csv_line .= ";";
      }
      $winsplits_csv_line .= implode(";", array_map(function($elt) { return ($elt[0] . ";-----"); },
                                                        $unvisited_controls));
    }

    // Oddity #2: The line MUST end in a semicolon!!!
    echo "{$winsplits_csv_line};\n";
    $start_number++;
    $place++;
  }
  $course_number++;
}

if ($download_csv) {
  echo "</pre>\n";
}


echo get_web_page_footer();
?>
