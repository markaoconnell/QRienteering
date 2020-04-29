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
  
  if ($output !~ /Correct!  Reached $control, control #\d/) {
    error_and_exit("Web page output wrong, correct control string not found.\n$output");
  }
  
  #print $output;
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  if (! -f "$path/${control_num_on_course}") {
    error_and_exit("$path/${control_num_on_course} (found first control) does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course start));
  if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) ||
      grep(/extra/, @directory_contents) || grep(/dnf/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
  }
  
  @file_contents_array = file_get_contents("$path/${control_num_on_course}");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $path/${control_num_on_course}: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
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
  
  @directory_contents = check_directory_contents($path, qw(name course));
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
  }
  
  my(@name_file) = file_get_contents("$path/name");
  my(@course_file) = file_get_contents("$path/course");
  
  if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne $competitor_name) || ($course_file[0] ne $course)) {
    error_and_exit("File contents wrong, name_file: " . join(",", @name_file) . "\n\tcourse_file: " . join("," , @course_file));
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
  if (! -f "$path/start") {
    error_and_exit("$path/start does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course start));
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
  }
  
  
  @file_contents_array = file_get_contents("$path/start");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, start_time_file: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
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

  if (($output =~ /DNF/) || ($output !~ /Course complete, time taken/) || ($output !~ /Results on ${readable_course_name}/)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }
  
  #print $output;
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  if (! -f "$path/finish") {
    error_and_exit("$path/finish does not exist.");
  }
  
  # The only other files i the directory should be the numeric files for the controls found
  @directory_contents = check_directory_contents($path, qw(name course start finish));
  if (grep(/^[^0-9]/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
  }
  
  
  @file_contents_array = file_get_contents("$path/finish");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $path/finish: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
  }
  
  my(@start_time_array) = file_get_contents("$path/start");
  my($results_file) = sprintf("%06d,%s", (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/${course}", $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join(",", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});
}



###########
# Finish the course successfully
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
  if (! -f "$path/finish") {
    error_and_exit("$path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course start finish dnf));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
  }
  
  
  @file_contents_array = file_get_contents("$path/finish");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $path/finish: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
  }
  
  my(@start_time_array) = file_get_contents("$path/start");
  my($results_file) = sprintf("%06d,%s", (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/${course}", $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join(",", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});
}

1;
