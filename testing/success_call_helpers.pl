#!/usr/bin/perl

use strict;

require "testHelpers.pl";

my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);


###########
sub reach_control_successfully {
  my($control_num_on_course, $get_ref, $cookie_ref, $test_info_ref) = @_;

  $competitor_id = $cookie_ref->{"competitor_id"};
  $test_info_ref->{"subroutine"} = "reach_control";
  hashes_to_artificial_file();
  $cmd = "php ../reach_control.php";
  $output = qx($cmd);

  my($control) = $get_ref->{"control"};
  if ($control eq "") {
    # When testing with passing the control via mumble, also pass the control
    # via the test info rather than base64 decoding the mumble and getting it
    # from there.
    $control = $test_info_ref->{"control"};
  }
  my($control_num_for_print_string) = $control_num_on_course + 1;
  
  if ($output !~ /Correct!  Reached $control, control #$control_num_for_print_string/) {
    error_and_exit("Web page output wrong, correct control string not found.\n$output");
  }
  
  #print $output;
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  my(@controls_found) = check_directory_contents("$path/controls_found", qw(start));

  if (grep (/NOTFOUND/, @controls_found) || grep (!/^[0-9]+,[0-9a-f]+$/)) {
    error_and_exit("$path/controls_found holds incorrect items, " .
                   "\n\tFound: " . join("--", @controls_found));
  }

  if ($#controls_found != $control_num_on_course) {
    error_and_exit("$path/controls_found hold wrong number of controls, found $#controls_found, expected $control_num_on_course, " .
                   "\n\tFound: " . join("--", @controls_found));
  }

  my(@sorted_controls_found) = sort { $a cmp $b } @controls_found;
  my($time_at_control);
  if ($sorted_controls_found[$#sorted_controls_found] !~ m#^([0-9]+),$control$#) {
    error_and_exit("Last control found: " . $sorted_controls_found[$#sorted_controls_found] . " does not match expected control: $control.\n");
  }
  $time_at_control = $1;
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) ||
      grep(/extra/, @directory_contents) || grep(/dnf/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  $time_now = time();
  if (($time_now - $time_at_control) > 5) {
    error_and_exit("Control file wrong, " . $sorted_controls_found[$#sorted_controls_found] . " has time $time_at_control vs time_now of $time_now.");
  }

  delete($test_info_ref->{"subroutine"});
}


###########
# Successfully register a new competitor
sub register_successfully {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "register_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../register_competitor.php";
  $output = qx($cmd);

  my($course) = $get_ref->{"course"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;
  my($competitor_name) = $get_ref->{"competitor_name"};

  if ($output !~ /Registration complete: $competitor_name on ${readable_course_name}/) {
    error_and_exit("Web page output wrong, registration complete string not found.\n$output");
  }
  
  #print $output;
  
  my($competitor_id);
  $competitor_id = qx(ls -1t ./UnitTestingEvent/Competitors | head -n 1);
  chomp($competitor_id);
  print "My competitor_id is $competitor_id\n";
  if (! -d "./UnitTestingEvent/Competitors/$competitor_id") {
    error_and_exit("Directory ./UnitTestingEvent/Competitors/$competitor_id not found.");
  }
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  if ((! -f "$path/name") || (! -f "$path/course")) {
    error_and_exit("One of $path/name or $path/course does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("$path/controls_found", qw());
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path/controls_found than expected: " . join("--", @directory_contents));
  }
  
  my(@name_file) = file_get_contents("$path/name");
  my(@course_file) = file_get_contents("$path/course");
  
  if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne $competitor_name) || ($course_file[0] ne $course)) {
    error_and_exit("File contents wrong, name_file: " . join("--", @name_file) . "\n\tcourse_file: " . join("--" , @course_file));
  }

  delete($test_info_ref->{"subroutine"});
  $test_info_ref->{"competitor_id"} = $competitor_id;
}




###########
# Success start the course
sub start_successfully {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "start_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../start_course.php";
  $output = qx($cmd);
  
  my($competitor_name);
  my($course) = $cookie_ref->{"course"};
  my($competitor_id) = $cookie_ref->{"competitor_id"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;
  
  if ($output !~ /${readable_course_name} course started for/) {
    error_and_exit("Web page output wrong, course start string not found.\n$output");
  }
  
  #print $output;
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  if (! -f "$path/controls_found/start") {
    error_and_exit("$path/controls_found/start does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("$path/controls_found", qw(start));
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path/controls_found than expected: " . join("--", @directory_contents));
  }
  
  
  @file_contents_array = file_get_contents("$path/controls_found/start");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, start_time_file: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  }

  delete($test_info_ref->{"subroutine"});
}




###########
# Finish the course successfully
sub finish_successfully {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../finish_course.php";
  $output = qx($cmd);
  
  my($course) = $cookie_ref->{"course"};
  my($competitor_id) = $cookie_ref->{"competitor_id"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output =~ /ERROR: DNF status/) || ($output !~ /Course complete, time taken/) || ($output !~ /Results on ${readable_course_name}/)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }
  
  #print $output;
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  my($controls_found_path) = "$path/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  # The only other files in the directory should be the numeric files for the controls found
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  # The points for the course based should be equal to the number of controls for a linear course
  my($number_controls_on_course) = scalar(@directory_contents);
  
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $controls_found_path/finish: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  }
  
  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", $number_controls_on_course, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/${course}", $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});
}



###########
# Finish the course with a DNF
sub finish_with_dnf {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_with_dnf";
  hashes_to_artificial_file();
  $cmd = "php ../finish_course.php";
  $output = qx($cmd);
  
  my($course) = $cookie_ref->{"course"};
  my($competitor_id) = $cookie_ref->{"competitor_id"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output !~ /DNF/) || ($output !~ /Course complete, time taken/) || ($output !~ /Results on ${readable_course_name}/)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }
  
  #print $output;
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  my($controls_found_path) = "${path}/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found dnf));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  my($number_controls_found_on_course) = scalar(@directory_contents);
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $controls_found_path/finish: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  }
  
  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", $number_controls_found_on_course, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/${course}", $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});

  return ($output);
}

###########
# Use the web interface to create an event
sub create_event_successfully {
  my($get_ref, $cookie_ref, $post_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "create_event_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../create_event.php";
  $output = qx($cmd);
  
  if ($output !~ /Created event successfully/) {
    error_and_exit("Web page output wrong, no message about successful event creation.\n$output");
  }

  my($event) = "./" . $post_ref->{"event_name"} . "Event";

  # Validate proper directories exist
  if (! -d $event) {
    error_and_exit("Proper directory for $event not found.\n");
  }

  my($number_courses);
  $number_courses = () = $post_ref->{"course_description"} =~ m/--newline--/g;
  $number_courses++;   # There is normally one fewer newline than the number of courses
  
  @directory_contents = check_directory_contents($event, qw(Competitors Results Courses));
  if (scalar(@directory_contents) != 0) {
    error_and_exit("More files exist in $event than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("${event}/Competitors", qw());
  if (scalar(@directory_contents) != 0) {
    error_and_exit("More files exist in ${event}/Competitors than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("${event}/Results", qw());
  if (scalar(@directory_contents) != $number_courses) {
    error_and_exit("Different number of files exist in ${event}/Results than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("${event}/Courses", qw());
  if (scalar(@directory_contents) != $number_courses) {
    error_and_exit("Different number of files exist in ${event}/Courses than expected: " . join("--", @directory_contents));
  }
  
  
  delete($test_info_ref->{"subroutine"});

  return ($output);
}

1;
