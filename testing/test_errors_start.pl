#!/usr/bin/perl

use strict;

require "testHelpers.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_Bad_Start";

initialize_event();
set_test_info(\%GET, \%COOKIE, \%TEST_INFO, $0);


###########
# Test 1 - start the course without registering
# Should return an error message
%TEST_INFO = qw(Testname TestStartNoRegistration);
%COOKIE = ();
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /probably not registered for a course/) {
  error_and_exit("Web page output wrong, should receive not registered output.\n$output");
}

#print $output;

success();



###########
# Test 2 - start with an unknown event
# Should return an error message
%TEST_INFO = qw(Testname TestStartOldEvent);
%COOKIE = qw(event OldEvent course 01-White);
$COOKIE{"competitor_id"} = "moc";
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /ERROR: Bad registration for event "OldEvent"/) {
  error_and_exit("Web page output wrong, bad registration error not found.\n$output");
}

#print $output;

success();



###########
# Test 3 - start multiple times
# First register, then start
%TEST_INFO = qw(Testname MultipleStart);
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

# Now start the course
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

# Now start the course again - this is the real part of the test
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /already started for $COMPETITOR_NAME/) {
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




############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
