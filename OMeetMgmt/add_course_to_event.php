<?php
require '../OMeetCommon/common_routines.php';

ck_testing();

echo get_web_page_header(true, false, false);

$verbose = isset($_GET["verbose"]);

require '../OMeetCommon/course_properties.php';
require '../OMeetMgmt/event_mgmt_common.php';

$event_created = false;
$found_error = false;

$key = $_GET["key"];
if (!key_is_valid($key)) {
  error_and_exit("No such access key \"$key\", are you using an authorized link?\n");
}

if (!is_dir(get_base_path($key, ".."))) {
  error_and_exit("No directory found for events, is your key \"{$key}\" valid?\n");
}

$event = $_GET["event"];
$event_path = get_event_path($event, $key, "..");
if (!is_dir($event_path)) {
  error_and_exit("No event directory found, is \"{$event}\" from a valid link?\n");
}

if (isset($_GET["submit"])) {
  $course_description = $_GET["course_description"];

  $event_path = get_event_path($event, $key, "..");

  $validation_results = validate_and_parse_course($course_description);
  $course_info = $validation_results[0];
  $extra_info = $validation_results[1];
  $course_name = $course_info[$NAME_FIELD];
  $current_event_name = file_get_contents("{$event_path}/description");

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
    
  if (!$found_error) {
    create_course_in_event($course_info, $key, $event);
    echo "<p>Course $course_name successfully added to {$current_event_name}\n";
  }

  if ($found_error) {
    echo "<p>Errors found, course not added.\n";
  }
}

$current_courses = scandir("{$event_path}/Courses");
$current_courses = array_diff($current_courses, array(".", ".."));
$current_courses = array_map(function ($elt) { return ("<li>" . ltrim($elt, "0..9-")); }, $current_courses);

$current_event_name = file_get_contents("{$event_path}/description");


if (!$event_created && !$found_error) {
?>
<br>
<form action=./add_course_to_event.php >
<p class="title">Add a course to <?php echo $current_event_name; ?>:<br>
<p>
<p> Current courses are:
<ul>
<?php echo implode("\n", $current_courses); ?>
</ul>
<br><br><br><p><p>
<input type="hidden" name="key" value="<?php echo $key ?>" />
<input type="hidden" name="event" value="<?php echo $event ?>" />
<p class="title">Enter details for the additional course:
<p>Format of the information: 
<ul>
<li>Normal Course: NameOfCourse,control,control,control,...
  <ul><li>Example: White,102,105,106</ul>
<li>ScoreO Course: s:NameOfCourse:time limit:penalty per minute,control:points,control:points,control:points,...
  <ul><li>Example: s:ScoutScoreO:2h:2,102:10,110:20,203:30,101:10,109:15
      <li>Time limit format is XXhYYmZZs for XX hours, XX minutes, XX seconds, note no spaces
      <li>Use a time limit of 0 to indicate unlimited time</ul>
</ul>
<p>Note: It is normally a good idea to type this information into a Word Doc, or a Google doc, or
someone else, then copy-and-paste it in below - makes it much easier if you have to re-enter due to a 
mistake if you can just copy-and-paste it in again!
<p>
<input name=course_description type=text size=80>
<p><p>
<input type=checkbox name="verbose" value="true">Show verbose output (useful only if course creation is failing)
<p><p>
<input name="submit" type="submit">
</form>

<?php
}
echo get_web_page_footer();
?>
