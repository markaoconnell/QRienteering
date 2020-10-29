<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_POST["verbose"]);
$cloning_event = false;

require '../OMeetCommon/course_properties.php';
require '../OMeetMgmt/event_mgmt_common.php';

$event_created = false;
$found_error = false;

if (isset($_POST["submit"])) {
  echo "Name of event: " . $_POST["event_name"] . "\n<p>";
  $event_fullname = $_POST["event_name"];
  $key = $_POST["key"];

//  if (!ck_valid_chars($event_name) || (substr($event_name, -5) == ".done")) {
//    echo "<p>ERROR: Event \"{$event_name}\" can only contain letters and numbers and cannot end in \".done\".\n";
//    $found_error = true;
//  }

  if (!key_is_valid($key)) {
    error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
  }

//  $event_name .= "Event";
//  $event_path = get_event_path($event_name, $key, "..");
//  if (file_exists($event_path)) {
//    echo "<p>ERROR: Event \"{$event_name}\" exists - is this a duplicate submission?\n";
//    $found_error = true;
//  }

  $course_array = array();
  //echo "<p>Here is the FILES array.\n";
  //print_r($_FILES);
  //echo "Here are the elements: " . $_FILES["upload_file"] . "\n";
  //echo "<p>and here's files of upload_file.\n<p>";
  //print_r($_FILES["upload_file"]);
  //echo "<p>Here is the course description textbox.\n";
  //echo "<p>" . $_POST["course_description"];
  //echo "<p>And that's all she wrote\n";

  if ($_FILES["upload_file"]["size"] > 0) {
    $file_contents = file_get_contents($_FILES["upload_file"]["tmp_name"]);
  }
  else {
    $file_contents = $_POST["course_description"];
  }

  $course_list = explode("\n", $file_contents);
  $num_courses=count($course_list);
  if ($verbose) {
    echo "<p>There are ${num_courses} courses found.\n";
  }

  if ($num_courses < $MAX_COURSES) {
    foreach ($course_list as $this_course) {
      // There is sometimes a blank line at the end - just ignore those
      if (trim($this_course) == "") {
        continue;
      }

      if (strpos($this_course, "--") === 0) {
        continue;  // Lines beginning with -- are ignored as comments
      }

      $validation_results = validate_and_parse_course($this_course);
      $course_info = $validation_results[0];
      $extra_info = $validation_results[1];
      $course_array[] = $course_info;
      $course_names_array[] = $course_info[$NAME_FIELD];

      //print_r($extra_info);

      if ($extra_info[$ERRORS] != "") {
        $found_error = true;
        echo "<p>{$extra_info[$ERRORS]}\n";
      }

      if ($verbose && ($extra_info[$VERBOSE_OUTPUT] != "")) {
        echo "<p>{$extra_info[$VERBOSE_OUTPUT]}\n";
      }
 
      if ($extra_info[$OUTPUT] != "") {
        echo "<p>{$extra_info[$OUTPUT]}\n";
      }

      //print_r($course_info);
    }
    
    if (!$found_error) {
      # Create the event itself
      $result = create_event($key, $event_fullname);

      if (substr($result, 0, 5) == "ERROR") {
        error_and_exit($result);
      }

      if (substr($result, 0, 6) != "event-") {
        error_and_exit("Unexpected format for event name, {$result}.\n");
      }

      $event = $result;

      foreach ($course_array as $this_course_info) {
        //print_r($this_course_info);
        create_course_in_event($this_course_info, $key, $event);
      }

      echo "<p>Created event successfully {$event_fullname} with " . count($course_array) . " courses:<ul><li>" . implode("<li>", $course_names_array) . "</ul>\n";
//      if (isset($_SERVER["HTTPS"])) {
//        $proto = "https://";
//      }
//      else {
//        $proto = "http://";
//      }
//
//      $url_prefix = $proto . $_SERVER["SERVER_NAME"] . dirname(dirname($_SERVER["REQUEST_URI"]));
//      while (substr($url_prefix, -1) == "/") {
//        $url_prefix = substr($url_prefix, 0, -1);
//      }

      //echo "<p>Server URI is: " . $_SERVER["REQUEST_URI"] . "\n";
      //echo "<p>Server URI dirname is: " . dirname($_SERVER["REQUEST_URI"]) . "\n";
      //echo "<p>Server URI dirname and rel path is: " . dirname($_SERVER["REQUEST_URI"]) . "/../OMeetRegistration/register.php" . "\n";
      //echo "<p>Service URI after realpath is " . dirname(dirname($_SERVER["REQUEST_URI"])) . "/OMeetRegistration/register.php" . "\n";
      //echo "<p>Server name is " . $_SERVER["SERVER_NAME"] . "\n";
      echo "<p>Return to the <a href=\"./manage_events.php?key={$key}\">event management page</a> to print the QR codes, get registration links, etc.\n";
      $event_created = true;
    }
  }
  else {
    echo "<p>ERROR: {$num_courses} courses is too many, poorly formatted input file?\n";
    $found_error = true;
  }

  if ($found_error) {
    echo "<p>Errors found, course not created.\n";
  }
}
else {
  $key = $_GET["key"];
  if (!key_is_valid($key)) {
    error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
  }

  if (!is_dir(get_base_path($key, ".."))) {
    error_and_exit("No event directory found, please contact administrator to create \"{$base_path}\"");
  }
  
  if (isset($_GET["clone_event"])) {
    $cloning_event = true;
    $clone_event = $_GET["clone_event"];

    $existing_event_path = get_event_path($clone_event, $key, "..");
    if (!is_dir($existing_event_path)) {
      error_and_exit("Event not found, is \"{$key}\" and \"{$clone_event}\" a valid pair?\n");
    }

    $existing_event_name = file_get_contents("{$existing_event_path}/description");
    $path_to_courses = get_courses_path($clone_event, $key, "..");
    $current_courses = scandir($path_to_courses);
    $current_courses = array_diff($current_courses, array(".", ".."));

    $existing_event_description_string = "";
    foreach ($current_courses as $this_course) {
      $control_list = read_controls("{$path_to_courses}/{$this_course}/controls.txt");

      $course_properties = get_course_properties("{$path_to_courses}/{$this_course}");
      $score_course = (isset($course_properties[$TYPE_FIELD]) && ($course_properties[$TYPE_FIELD] == $SCORE_O_COURSE));

      if ($score_course) {
        $existing_event_description_string .=
                                     "s:" . ltrim($this_course, "0..9-") . ":" . $course_properties[$LIMIT_FIELD] . "s:" . $course_properties[$PENALTY_FIELD] . "," .
                                     implode(",", array_map(function ($elt) { return ($elt[0] . ":" . $elt[1]); }, $control_list)) . "\n";
      }
      else {
        $existing_event_description_string .=
                                      "l:" . ltrim($this_course, "0..9-") . "," .
                                      implode(",", array_map(function ($elt) { return ($elt[0]); }, $control_list)) . "\n";
      }
    }
  }
}

if (!$event_created && !$found_error) {
?>
<br>
<form action=./create_event.php method=post enctype="multipart/form-data" >
<p class="title">What is the name of the event?<br>
<p>
<input name=event_name type=text size=80 <?php if ($cloning_event) { echo "value=\"Copy of {$existing_event_name}\""; } ?> >
<br><br><br><p><p>
<input type="hidden" name="MAX_FILE_SIZE" value="4096" />
<input type="hidden" name="key" value="<?php echo $key ?>" />
<p class="title">Enter course/control details for the event.
<p>Format of the information: One course per line, comma separated.
<ul>
<li>Normal Course: NameOfCourse,control,control,control,...
  <ul>
      <li>May precede the name with "l:" (lowercase letter L) to unambiguously indicate a normal (linear) course
      <li>Example:   <strong>White,102,105,106</strong>
      <li>Example 2: <strong>l:Yellow,108,109</strong>
  </ul>
<li>ScoreO Course: s:NameOfCourse:time limit:penalty per minute,control:points,control:points,control:points,...
  <ul><li>Example: <strong>s:ScoutScoreO:2h:2,102:10,110:20,203:30,101:10,109:15</strong>
      <li>Time limit format is XXhYYmZZs for XX hours, XX minutes, XX seconds, note no spaces
      <li>Use a time limit of 0 to indicate unlimited time</ul>
</ul>
<p>Note: It is normally a good idea to type this information into a Word Doc, or a Google doc, or
someone else, then copy-and-paste it in below - makes it much easier if you have to re-enter due to a 
mistake if you can just copy-and-paste it in again!
<?php
if (0) {
?>
<input name=upload_file type=file>
<p>
<br>
<p class="title">Alternatively, enter the information directly here.
<?php } ?>
<p>
<textarea name=course_description rows=10 cols=60>
--Replace this with your course description--
<?php
  if ($cloning_event) {
    echo $existing_event_description_string;
  }
?>
</textarea>
<p><p>
<input type=checkbox name="verbose" value="true">Show verbose output (useful only if course creation is failing)
<p><p>
<input name="submit" type="submit">
</form>

<?php
}
echo get_web_page_footer();
?>
