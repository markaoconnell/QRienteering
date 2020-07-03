<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$MAX_COURSE_NAME_LEN = 40;
$MAX_CONTROL_CODE_LEN = 40;
$MAX_CONTROL_VALUE = 100;
$MAX_COURSES = 20;
$MAX_CONTROLS = 50;

$verbose = isset($_POST["verbose"]);

require '../OMeetCommon/course_properties.php';

// Utility functions
function ck_valid_chars($string_to_check) {
  return (preg_match("/^[a-zA-Z0-9_-]+$/", $string_to_check));
}

function ck_linear_control_entry($string_to_check) {
  global $MAX_CONTROL_CODE_LEN;
  return((ctype_alnum($string_to_check) && (strlen($string_to_check) < $MAX_CONTROL_CODE_LEN)) ? 1 : 0);
}

function ck_score_control_entry($string_to_check) {
  global $MAX_CONTROL_CODE_LEN, $MAX_CONTROL_VALUE;
  if (!preg_match("/^[a-zA-Z0-9]+:[0-9]+$/", $string_to_check)) {
    return(0);
  }

  $pieces = explode(":", $string_to_check);
  if ((strlen($pieces[0]) > $MAX_CONTROL_CODE_LEN) || ($pieces[1] > $MAX_CONTROL_VALUE)) {
    return(0);
  }

  return(1);
}


function parse_course_name($course_name) {
  global $NAME_FIELD, $TYPE_FIELD, $LIMIT_FIELD, $PENALTY_FIELD, $SCORE_O_COURSE, $LINEAR_COURSE, $ERROR_FIELD;
  global $SCORE_COURSE_ID, $LINEAR_COURSE_ID;

  $info = explode(":", $course_name);
  $return_info = array();
  if (strlen($info[0]) == 1) {
    if ($info[0] == $SCORE_COURSE_ID) {
      $return_info[$NAME_FIELD] = $info[1];
      $return_info[$TYPE_FIELD] = $SCORE_O_COURSE;
      $return_info[$LIMIT_FIELD] = time_limit_to_seconds($info[2]);
      $return_info[$PENALTY_FIELD] = $info[3];
      $expected_fields = 4; 

      if ($return_info[$LIMIT_FIELD] == -1) {
        $return_info[$ERROR_FIELD] = "Time limit field not understandable, {$info[2]} not in format XXhYYmZZs.\n";
      }
    }
    else if ($info[0] == $LINEAR_COURSE_ID) {
      $return_info[$NAME_FIELD] = $info[1];
      $return_info[$TYPE_FIELD] = $LINEAR_COURSE;
      $expected_fields = 2;
    }
    else {
      // This case really shouldn't happen, but to be safe
      $return_info[$NAME_FIELD] = $info[0];
      $return_info[$TYPE_FIELD] = $LINEAR_COURSE;
      $expected_fields = 1;
    }
  }
  else {
    // This case really shouldn't happen, but to be safe
    $return_info[$NAME_FIELD] = $info[0];
    $return_info[$TYPE_FIELD] = $LINEAR_COURSE;
    $expected_fields = 1;
  }

  if (count($info) != $expected_fields) {
    $return_info[$ERROR_FIELD] = "Unexpected number entries: {$course_name}, {$expected_fields} expected, found " . count($info) . "\n";
  }

  return($return_info);
}

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

      // Course name must begin with a letter and may only contain [a-zA-Z0-9-_]
      // controls may only contain [a-zA-Z0-9]
      // The Course name may have extra, : separated information, especially for a scoreO
      $course_name_and_controls = explode(",", $this_course);
      $course_name_entry = trim($course_name_and_controls[0]);
      $course_info = parse_course_name($course_name_entry);
      $course_name = $course_info[$NAME_FIELD];

      if ($course_info[$ERROR_FIELD] != "") {
        echo "<p>ERROR: Course entry {$this_course} looks wrong: {$course_info[$ERROR_FIELD]}\n";
        $found_error = true;
      }

      if ((ctype_alpha(substr($course_name, 0, 1))) && (ck_valid_chars($course_name)) && 
            (strlen($course_name) < $MAX_COURSE_NAME_LEN)) {
        if ($verbose) {
          echo "<p>Course name {$course_name} passes the checks.\n";
        }
      }
      else {
        echo "<p>ERROR: Course name \"{$course_name}\" fails the checks, only letters, numbers, and - allowed.\n";
        $found_error = true;
      }

      $control_list = array_map(trim, array_slice($course_name_and_controls, 1));

      if ($course_info[$TYPE_FIELD] == $LINEAR_COURSE) {
        $check_controls = array_map(ck_linear_control_entry, $control_list);
      }
      else if ($course_info[$TYPE_FIELD] == $SCORE_O_COURSE) {
        $check_controls = array_map(ck_score_control_entry, $control_list);
      }
      else {
        echo "<p>ERROR: Unknown course type {$course_info[$TYPE_FIELD]}.\n";
        $found_error = true;
        $check_controls = array();
      }

      if (array_search(0, $check_controls) === false) {
        if ($verbose) {
          echo "<p>Control list all seems to be correctly formatted and not too long.\n";
        }
      }
      else {
        echo "<p>ERROR: Control list contains either non-alphanumeric characters or too long.\n";
        echo "<p>Checking results: " . join(",", $check_controls) . "\n";
        $found_error = true;
      }

      if (count($control_list) > $MAX_CONTROLS) {
        echo "<p>ERROR: Too many controls found - " . count($control_list) . "\n";
        $found_error = true;
      }

      // Validate that if there are duplicate entries, that at least the point values are the same
      if ($course_info[$TYPE_FIELD] == $SCORE_O_COURSE) {
        $control_dedup_hash = array();
        foreach ($control_list as $control_entry) {
          $pieces = explode(":", $control_entry);
          if (!isset($control_dedup_hash[$pieces[0]])) {
            $control_dedup_hash[$pieces[0]] = $pieces[1];
          }
          else if ($control_dedup_hash[$pieces[0]] != $pieces[1]) {
            $found_error = true;
            echo "<p>ERROR: Control {$pieces[0]} duplicated with different point values {$control_dedup_hash[$pieces[0]]} and {$pieces[1]}.\n";
          }
          else {
            echo "<p>INFO: Control {$pieces[0]} duplicated, ignoring second entry.\n";
          }
        }

        $control_list = array_map(function ($elt) use ($control_dedup_hash) { return (implode(":", array($elt, $control_dedup_hash[$elt]))); },
                                  array_keys($control_dedup_hash));
      }

      if (count($course_name_and_controls) > 1) {
        if ($course_info[$TYPE_FIELD] == $LINEAR_COURSE) {
           // For a linear course, the controls are all worth one point. TBD if this is the right
           // place to do this.
           $control_list = array_map(function ($control) { return("{$control}:1"); }, $control_list);
        }
        else if ($course_info[$TYPE_FIELD] == $SCORE_O_COURSE) {
          // Get the maximum score for the course
          $points_array = array_map(function ($control) { $pieces = explode(":", $control); return ($pieces[1]); }, $control_list);
          $max_score = array_sum($points_array);
          $course_info[$MAX_SCORE_FIELD] = $max_score;
        }

        if ($verbose) {
          echo "<p>Found controls for course {$course_name}: " . implode("--", $control_list) . "\n";
        }
        $course_array[] = array($course_name, $control_list, $course_info);
      }
      else {
        echo "<p>ERROR: No controls for course {$course_name}.\n";
        $found_error = true;
      }
    }
    
    if (!$found_error) {
      # Create the event itself
      if (!is_dir(get_base_path($key, ".."))) {
        mkdir(get_base_path($key, ".."), 0755, true);  // Create the intermediate directories as necessary
      }

      $event_name_attempts = 0;
      while ($event_name_attempts < 100) {
        $event_name = uniqid("event-");
        $event_path = get_event_path($event_name, $key, "..");

        if (!file_exists($event_path)) {
          break;
        }

        $event_name_attempts++;
      }
      if (($event_name_attempts >= 100)  || file_exists($event_path)) {
        error_and_exit("Internal error creating event, please wait and retry.");
      }

      mkdir($event_path);
      mkdir("{$event_path}/Competitors");
      mkdir("{$event_path}/Courses");
      mkdir("{$event_path}/Results");
      file_put_contents("{$event_path}/description", $event_fullname);
  
      $course_names_array = array();
      for ($i = 0; $i < count($course_array); $i++) {
        $prefix = sprintf("%02d", $i);
        mkdir("{$event_path}/Courses/{$prefix}-{$course_array[$i][0]}");
        mkdir("{$event_path}/Results/{$prefix}-{$course_array[$i][0]}");
        $course_names_array[] = $course_array[$i][0];
        file_put_contents("${event_path}/Courses/{$prefix}-{$course_array[$i][0]}/controls.txt", implode("\n", $course_array[$i][1]));

        if ($course_array[$i][2][$TYPE_FIELD] == $SCORE_O_COURSE) {
          $course_info = $course_array[$i][2];
          $properties_string = "";
          foreach ($course_info as $key => $value) {
            $properties_string .= $key . ":" . $value . "\n";
          }
          file_put_contents("{$event_path}/Courses/{$prefix}-{$course_array[$i][0]}/properties.txt", $properties_string);
        }
      }

      echo "<p>Created event successfully {$event_fullname} with " . count($course_array) . " courses:<ul><li>" . implode("<li>", $course_names_array) . "</ul>\n";
      if (isset($_SERVER["HTTPS"])) {
        $proto = "https://";
      }
      else {
        $proto = "http://";
      }
      $registration_link = $proto . $_SERVER["SERVER_NAME"] . dirname(dirname($_SERVER["REQUEST_URI"])) . "/OMeetRegistration/register.php" . "?key={$key}";
      //echo "<p>Server URI is: " . $_SERVER["REQUEST_URI"] . "\n";
      //echo "<p>Server URI dirname is: " . dirname($_SERVER["REQUEST_URI"]) . "\n";
      //echo "<p>Server URI dirname and rel path is: " . dirname($_SERVER["REQUEST_URI"]) . "/../OMeetRegistration/register.php" . "\n";
      //echo "<p>Service URI after realpath is " . dirname(dirname($_SERVER["REQUEST_URI"])) . "/OMeetRegistration/register.php" . "\n";
      //echo "<p>Server name is " . $_SERVER["SERVER_NAME"] . "\n";
      echo "<p>Paths to register are:<p><ul><li>Event specific registration: {$registration_link}&event=${event_name}</li>\n";
      echo "<li>General registration: {$registration_link}</li></ul><p>\n";
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
}

if (!$event_created && !$found_error) {
?>
<br>
<form action=./create_event.php method=post enctype="multipart/form-data" >
<p class="title">What is the name of the event?<br>
<p>
<input name=event_name type=text size=80>
<br><br><br><p><p>
<input type="hidden" name="MAX_FILE_SIZE" value="4096" />
<input type="hidden" name="key" value="<?php echo $key ?>" />
<p class="title">Enter a filename with the course/control details for the event:
<input name=upload_file type=file>
<p><p>
<br>
<p class="title">Alternatively, enter the file contents here.
<p>One course per line, comma separated.
<ul>
<li>Normal Course: NameOfCourse,control,control,control,...
  <ul><li>Example: White,102,105,106</ul>
<li>ScoreO Course: s:NameOfCourse:time limit:penalty per minute,control:points,control:points,control:points,...
  <ul><li>Example: s:ScoutScoreO:2h:2,102:10,110:20,203:30,101:10,109:15
      <li>Time limit format is XXhYYmZZs for XX hours, XX minutes, XX seconds, note no spaces</ul>
</ul>
<textarea name=course_description rows=10 cols=60>
--Replace this with your course description--
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
