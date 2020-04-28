#!/usr/bin/perl

use strict;

require "testHelpers.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_Success_Testing";

initialize_event();
set_test_info(\%GET, \%COOKIE, \%TEST_INFO, $0);

###########
sub reach_control {
  my($competitor_id, $control) = @_;

  %GET = ();
  $GET{"control"} = $control;
  hashes_to_artificial_file();
  $cmd = "php ../reach_control.php";
  $output = qx($cmd);
  
  if ($output !~ /Correct!  Reached $control, control #\d on White/) {
    error_and_exit("Web page output wrong, correct control string not found.\n$output");
  }
  
  #print $output;
  
  $path = "./UnitTestingEvent/Competitors/$competitor_id";
  if (! -f "$path/0") {
    error_and_exit("$path/0 (found first control) does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course start));
  if (grep(/NOTFOUND:finish/, @directory_contents) ||
      grep(/NOTFOUND:extra/, @directory_contents) || grep(/NOTFOUND:dnf/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
  }
  
  @file_contents_array = file_get_contents("$path/0");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $path/0: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
  }
}


###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname TestSuccessRegistration);
%GET = qw(event UnitTestingEvent course 01-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
%COOKIE = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../register_competitor.php";
$output = qx($cmd);

if ($output !~ /Registration complete: $COMPETITOR_NAME on White/) {
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

if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne $COMPETITOR_NAME) || ($course_file[0] ne "01-White")) {
  error_and_exit("File contents wrong, name_file: " . join(",", @name_file) . "\n\tcourse_file: " . join("," , @course_file));
}

success();




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestSuccessStart);
$TEST_INFO{"filename"} = $0;
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /White course started for $COMPETITOR_NAME./) {
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

success();


###########
# Test 3 - find all correct controls
# Validate that the correct entry is created
%TEST_INFO = qw(Testname FindAllValidControls);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;

reach_control($competitor_id, "201");
reach_control($competitor_id, "202");
reach_control($competitor_id, "203");
reach_control($competitor_id, "204");
reach_control($competitor_id, "205");

success();


###########
# Test 4 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishSuccessWhite);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = (); # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if (($output =~ /DNF/) || ($output !~ /Course complete, time taken/) || ($output !~ /Results on White/) ||
    ($output !~ /$COMPETITOR_NAME/)) {
  error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/finish") {
  error_and_exit("$path/finish does not exist.");
}

@directory_contents = check_directory_contents($path, qw(name course start 0 1 2 3 4 finish));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}


@file_contents_array = file_get_contents("$path/finish");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, $path/finish: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
}

my(@start_time_array) = file_get_contents("$path/start");
my($results_file) = sprintf("%06d,%s", (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);


my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/01-White", $results_file);
if (grep(/NOTFOUND:$results_file/, @results_array)) {
  error_and_exit("No results file ($results_file) found, contents are: " . join(",", @results_array));
}

success();



############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
