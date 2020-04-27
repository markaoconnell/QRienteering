#!/usr/bin/perl

use strict;

require "test_helper_functions.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);

initialize_event();

###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname TestSuccessRegistration);
%GET = qw(competitor_name Mark_OConnell_Testing event UnitTestingEvent course 01-White);
%COOKIE = ();  # empty hash
hashes_to_artificial_file(\%GET, \%COOKIE);
$cmd = "php ../register_competitor.php";
$output = qx($cmd);

if ($output !~ /Registration complete: Mark_OConnell_Testing on White/) {
  error_and_exit("Web page output wrong, registration complete string not found.\n$output", \%TEST_INFO);
}

#print $output;

my($competitor_id);
$competitor_id = qx(ls -1t ./UnitTestingEvent/Competitors | head -n 1);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";
if (! -d "./UnitTestingEvent/Competitors/$competitor_id") {
  error_and_exit("Directory ./UnitTestingEvent/Competitors/$competitor_id not found.", \%TEST_INFO);
}

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if ((! -f "$path/name") || (! -f "$path/course")) {
  error_and_exit("One of $path/name or $path/course does not exist.", \%TEST_INFO);
}

@directory_contents = check_directory_contents($path, qw(name course));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents), \%TEST_INFO);
}

my(@name_file) = file_get_contents("$path/name");
my(@course_file) = file_get_contents("$path/course");

if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne "Mark_OConnell_Testing") || ($course_file[0] ne "01-White")) {
  error_and_exit("File contents wrong, name_file: " . join(",", @name_file) . "\n\tcourse_file: " . join("," , @course_file), \%TEST_INFO);
}

success(\%TEST_INFO);




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestSuccessStart);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash
hashes_to_artificial_file(\%GET, \%COOKIE);
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /White course started for Mark_OConnell_Testing./) {
  error_and_exit("Web page output wrong, course start string not found.\n$output", \%TEST_INFO);
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/start") {
  error_and_exit("$path/start does not exist.", \%TEST_INFO);
}

@directory_contents = check_directory_contents($path, qw(name course start));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents), \%TEST_INFO);
}


@file_contents_array = file_get_contents("$path/start");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, start_time_file: " . join(",", @file_contents_array) . " vs time_now of $time_now.", \%TEST_INFO);
}

success(\%TEST_INFO);


###########
# Test 3 - find a control
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFind201);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = qw(control 201);
hashes_to_artificial_file(\%GET, \%COOKIE);
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output !~ /Correct!  Reached 201, control #1 on White/) {
  error_and_exit("Web page output wrong, course start string not found.\n$output", \%TEST_INFO);
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/0") {
  error_and_exit("$path/0 (found first control) does not exist.", \%TEST_INFO);
}

@directory_contents = check_directory_contents($path, qw(name course start 0));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents), \%TEST_INFO);
}


@file_contents_array = file_get_contents("$path/0");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, $path/0: " . join(",", @file_contents_array) . " vs time_now of $time_now.", \%TEST_INFO);
}

success(\%TEST_INFO);


###########
# Test 4 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishEarlyDNF);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = (); # empty hash
hashes_to_artificial_file(\%GET, \%COOKIE);
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if ($output !~ /Not all controls found/) {
  error_and_exit("Web page output wrong, not all controls entry not found.\n$output", \%TEST_INFO);
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/finish") {
  error_and_exit("$path/finish does not exist.", \%TEST_INFO);
}

@directory_contents = check_directory_contents($path, qw(name course start 0 finish dnf));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents), \%TEST_INFO);
}


@file_contents_array = file_get_contents("$path/finish");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, $path/finish: " . join(",", @file_contents_array) . " vs time_now of $time_now.", \%TEST_INFO);
}

if (! -f "$path/dnf") {
  error_and_exit("Early finish but the DNF file does not exist.", \%TEST_INFO);
}

success(\%TEST_INFO);



############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
