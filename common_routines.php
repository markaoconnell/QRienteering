<?php
// Return a string with the elapsed time in seconds pretty printed
function formatted_time($time_in_seconds) {
  $hours = floor($time_in_seconds / 3600);
  $mins = floor(($time_in_seconds / 60) % 60);
//  $hours = 0;
//  $mins = floor($time_in_seconds / 60);
  $secs = ($time_in_seconds % 60);

  if ($hours > 0) {
    return sprintf("%2dh:%02dm:%02ds", $hours, $mins, $secs);
  }
  else if ($mins > 0) {
    return sprintf("   %2dm:%02ds", $mins, $secs);
  }
  else {
    return sprintf("       %2ds", $secs);
  }
}

// Return a string with the elapsed time in seconds formatted for easy parsing
function csv_formatted_time($time_in_seconds) {
  $mins = floor($time_in_seconds / 60);
  $secs = ($time_in_seconds % 60);

  return sprintf("%3d:%02d", $mins, $secs);
}


// Am I running on a mobile device?
function is_mobile () {
  return is_numeric(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "mobile"));
}

// get table style elements
function get_table_style_header() {
  if (is_mobile()) {
    return "<style>\n td {\nfont-size: 200%;\n}\n th {\n font-size: 220%;\n}\ntable, th, td {\n border-collapse: collapse;\n border: 1px solid lightgray;\n }\n</style>\n";
  }
  else {
    return "<style>\n td {\nfont-size: 100%;\n}\n th {\n font-size: 120%;\n}\ntable, th, td {\n border-collapse: collapse;\n border: 1px solid lightgray;\n }\n</style>\n";
  }
}

// get paragraph style elements
function get_paragraph_style_header() {
  if (is_mobile()) {
    return "<style>\n p {\nfont-size: 300%;\n}\n .title\n {\nfont-size: 350%;\n}\n </style>\n";
  }
  else {
    return "<style>\n p {\nfont-size: 100%;\n}\n .title\n {\nfont-size: 125%;\n}\n </style>\n";
  }
}

// get input form style elements
function get_input_form_style_header() {
  if (is_mobile()) {
    return "<style>input {\nfont-size: 110%;s\n}\ninput[type=submit] {\nheight : 85%;\n}\ninput[type=radio]\n{\ntransform:scale(2)\n}\n</style>\n";
  }
  else {
    return "";
  }
}

// Show the results for a course
function show_results($event, $course) {
  $result_string = "";
  $result_string .= "<p>Results on " . ltrim($course, "0..9-") . "\n";

  if (!is_dir("./${event}/Results/${course}")) {
    $result_string .= "<p>No Results yet<p><p><p>\n";
    return($result_string);
  }
  $results_list = scandir("./${event}/Results/${course}");
  $results_list = array_diff($results_list, array(".", ".."));

  $result_string .= "<table border=1><tr><th>Name</th><th>Time</th></tr>\n";
  $dnfs = "";
  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_name = file_get_contents("./${event}/Competitors/" . $result_pieces[1] . "/name");
    if (!file_exists("./${event}/Competitors/" . $result_pieces[1] . "/dnf")) {
      $result_string .= "<tr><td><a href=\"./show_splits?course=${course}&event=${event}&entry=${this_result}\">${competitor_name}</a></td><td>" . formatted_time($result_pieces[0]) . "</td></tr>\n";
    }
    else {
      $dnfs .= "<tr><td><a href=\"./show_splits?course=${course}&event=${event}&entry=${this_result}\">${competitor_name}</a></td><td>DNF</td></tr>\n";
    }
  }
  $result_string .= "${dnfs}</table>\n<p><p><p>";
  return($result_string);
}

// Show the results for a course as a csv
function get_csv_results($event, $course) {
  $result_string = "";
  $readable_course_name = ltrim($course, "0..9-");

  // No results yet - .csv is empty
  if (!is_dir("./${event}/Results/${course}")) {
    return("");
  }
  $results_list = scandir("./${event}/Results/${course}");
  $results_list = array_diff($results_list, array(".", ".."));

  $dnfs = "";
  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_name = file_get_contents("./${event}/Competitors/" . $result_pieces[1] . "/name");
    if (!file_exists("./${event}/Competitors/" . $result_pieces[1] . "/dnf")) {
      $result_string .= "${readable_course_name};${competitor_name};" . csv_formatted_time($result_pieces[0]) . ";\n";
    }
    else {
      $result_string .= "${readable_course_name};${competitor_name};DNF;\n";
    }
  }
  return($result_string);
}

// Explode a comma separated string into an array
function explode_lines(&$item1, $key)
{
   $item1 = explode(",", $item1);
   array_walk($item1, 'html_decoder');
}

// Implode an array into a comma separated string
function implode_line(&$item1, $key)
{
   array_walk($item1, 'html_encoder');
   $item1 = implode(",", $item1);
}

// Decode an entry from html
// First translate from > to , to restore commas in the individual entries
// Then decode the other special characters
// This works because a natural > is one of the special characters to be decoded
// - it had been replaced by a &gt;!
function html_decoder(&$item1, $key)
{
   $item1 = str_replace(">", ",", $item1);
   $item1 = html_entity_decode($item1);
}

// Encode an entry to html
// First encode it, then convert , to > so that a true , can be used as
// an entry separator in the .csv file.  This is safe since any original
// > would be encoded by the htmlentities encoding!
function html_encoder(&$item1, $key)
{
   $item1 = htmlentities($item1);
   $item1 = str_replace(",", ">", $item1);
}


// Take file contents as an array of arrays, where the sub-arrays will become comma separated
// lines and the main arrays will each be a separate line in the output file.
// All will be html encoded, and ',' will be encoded as '>' in the file.
// NOTE: This function rather implicitly assumes that the filename is in the current directory and
//       ends with .csv!!!
function write_file($file_array, $filename)
{
  array_walk($file_array, 'implode_line');
  $file_array = implode("\n", $file_array);

  $file_base_name = basename($filename, ".csv");

// Make a backup copy, one per day
  rename($filename, $file_base_name . unixtojd(). ".csv");

// Then write the new file
  $handle = fopen($filename, "w");
  fwrite($handle, $file_array);
  fclose($handle);
}


// Read the file $filename into the array.  The file is assumed to be a .csv file.
// Each line of the file will be an entry in the array $file_array.  Each line will furthermore
// be split up into a subarray, with each element of the subarray one of the elements from the line,
// all assuming that the entries were comma separated to begin with.
function read_file(&$file_array, $filename)
{
$file_array = file($filename);
foreach ($file_array as $key => $value)
  {
  $file_array[$key] = rtrim($value);
  }

array_walk($file_array, 'explode_lines');
}
?>
