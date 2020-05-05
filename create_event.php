<?php
require 'common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$MAX_COURSE_NAME_LEN = 40;
$MAX_CONTROL_CODE_LEN = 40;
$MAX_COURSES = 20;
$MAX_CONTROLS = 50;

function ck_valid_chars($string_to_check) {
  return (preg_match("/^[a-zA-Z0-9_-]+$/", $string_to_check));
}

function ck_control_name_alnum($string_to_check) {
  return(ctype_alnum($string_to_check) ? 1 : 0);
}

function ck_control_str_length($string_to_check) {
  global $MAX_CONTROL_CODE_LEN;
  return((strlen($string_to_check) < $MAX_CONTROL_CODE_LEN) ? 1 : 0);
}

if (isset($_POST["submit"])) {
  $found_error = false;
  echo "Name of event: " . $_POST["event_name"] . "\n<p>";
  $event_name = $_POST["event_name"];
  if (!ck_valid_chars($event_name) || (substr($event_name, -5) == ".done")) {
    echo "<p>Event \"{$event_name}\" can only contain letters and numbers and cannot end in \".done\".\n";
    $found_error = true;
  }
  $event_name .= "Event";
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
  echo "<p>There are ${num_courses} courses found.\n";

  if ($num_courses < $MAX_COURSES) {
    foreach ($course_list as $this_course) {
      // There is sometimes a blank line at the end - just ignore those
      if (trim($this_course) == "") {
        continue;
      }

      // Course name must begin with a letter and may only contain [a-zA-Z0-9-_]
      // controls may only contain [a-zA-Z0-9]
      $course_name_and_controls = explode(",", $this_course);
      $course_name = trim($course_name_and_controls[0]);
      if ((ctype_alpha(substr($course_name, 0, 1))) && (ck_valid_chars($course_name)) && 
            (strlen($course_name) < $MAX_COURSE_NAME_LEN)) {
        echo "<p>Course name {$course_name} passes the checks.\n";
      }
      else {
        echo "<p>Course name \"{$course_name}\" fails the checks, only letters, numbers, and - allowed.\n";
        $found_error = true;
      }

      $control_list = array_map(trim, array_slice($course_name_and_controls, 1));

      $check_controls = array_map(ck_control_name_alnum, $control_list);
      $check_controls_length = array_map(ck_control_str_length, $control_list);
      if ((array_search(0, $check_controls) === false) && (array_search(0, $check_controls_length) === false)) {
        echo "<p>Control list all seems to be alphanumeric and not too long.\n";
      }
      else {
        echo "<p>Control list contains either non-alphanumeric characters or too long.\n";
        echo "<p>Alphanumeric check: " . join(",", $check_controls) . "\n";
        echo "<p>Length check: " . join(",", $check_controls_length) . "\n";
        $found_error = true;
      }

      if (count($control_list) > $MAX_CONTROLS) {
        echo "<p>ERROR: Too many controls found - " . count($control_list) . "\n";
        $found_error = true;
      }

      if (count($course_name_and_controls) > 1) {
        // For a linear course, the controls are all worth one point. TBD if this is the right
        // place to do this.
        $control_list = array_map(function ($control) { return("{$control},1"); }, $control_list);
        echo "<p>Found controls for course {$course_name}: " . implode("--", $control_list) . "\n";
        $course_array[] = array($course_name, $control_list);
      }
      else {
        echo "<p>ERROR: No controls for course {$course_name}.\n";
        $found_error = true;
      }
    }
    
    if (!$found_error) {
      # Create the event itself
      mkdir("./{$event_name}");
      mkdir("./{$event_name}/Competitors");
      mkdir("./{$event_name}/Courses");
      mkdir("./{$event_name}/Results");
  
      for ($i = 0; $i < count($course_array); $i++) {
        $prefix = sprintf("%02d", $i);
        mkdir("./{$event_name}/Courses/{$prefix}-{$course_array[$i][0]}");
        mkdir("./{$event_name}/Results/{$prefix}-{$course_array[$i][0]}");
        // Linear course is 1 point per control (unlike a scoreO)
        file_put_contents("./${event_name}/Courses/{$prefix}-{$course_array[$i][0]}/controls.txt", implode("\n", $course_array[$i][1]));
      }
      echo "<p>Created event successfully {$event_name}\n";
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

?>
<br>
<form action=./create_event.php method=post enctype="multipart/form-data" >
<p class="title">What is the name of the event?<br>
<p>Note that "Event" will be automatically appended to the entered event name.
<p>
<input name=event_name type=text>
<br><br><br><p><p>
<input type="hidden" name="MAX_FILE_SIZE" value="4096" />
<p class="title">Enter a filename with the course/control details for the event:
<input name=upload_file type=file>
<p><p>
<br>
<p class="title">Alternatively, enter the file contents here.
<p>One course per line, comma separated.
<p>NameOfCourse,control,control,control,...<p>
<textarea name=course_description rows=10 cols=60>
--Replace this with your course description--
</textarea>
<p><p>
<input name="submit" type="submit">
</form>

<?php
echo get_web_page_footer();
?>
