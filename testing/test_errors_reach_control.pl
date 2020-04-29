#!/usr/bin/perl

use strict;

require "testHelpers.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_ReachControl_Bad";

initialize_event();
set_test_info(\%GET, \%COOKIE, \%TEST_INFO, $0);


###########
# Test 1 - reach a control without registering
# Should return an error message
%TEST_INFO = qw(Testname TestReachControlNotRegistered);
%COOKIE = ();
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output !~ /probably not registered for a course/) {
  error_and_exit("Web page output wrong, should receive not registered output.\n$output");
}

#print $output;

success();



###########
# Test 2 - reach a control with a bad event
# Should return an error message
%TEST_INFO = qw(Testname TestReachControlBadEvent);
%COOKIE = qw(event OldEvent course 01-White);
$COOKIE{"competitor_id"} = "moc";
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);


if ($output =~ m#\./reach_control\.php\?mumble=([a-zA-Z0-9+/=]+)#) {
  print "Processing redirect during " . $TEST_INFO{"Testname"} . ".\n";
  %GET = ();
  $GET{"mumble"} = $1;
  hashes_to_artificial_file();
  $cmd = "php ../reach_control.php";
  $output = qx($cmd);
}

if (($output !~ /Cannot find event/) || ($output !~ /please re-register and retry/)) {
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();


###########
# Test 3 - reach a control with a bad course
# Should return an error message
%TEST_INFO = qw(Testname TestReachControlBadCourse);
%COOKIE = qw(event UnitTestingEvent course 03-Orange);
$COOKIE{"competitor_id"} = "moc";
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if ($output !~ /appears to be no longer appears valid/){
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();



###########
# Test 4 - After registering, try bad reach_control calls again
# First register, then call reach_control incorrectly a few different ways
%TEST_INFO = qw(Testname TestReachControlAfterRegisteringBadCourse);
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

# Now reach a control but with a bad course
%COOKIE = qw(event UnitTestingEvent course 03-Orange);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output =~ m#\./reach_control\.php\?mumble=([a-zA-Z0-9+/=]+)#) {
  print "Processing redirect during " . $TEST_INFO{"Testname"} . ".\n";
  %GET = ();
  $GET{"mumble"} = $1;
  hashes_to_artificial_file();
  $cmd = "php ../reach_control.php";
  $output = qx($cmd);
}


if (($output !~ /Cannot find event/) || ($output !~ /please re-register and retry/)) {
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();


##################
# Test: Corrupt competitor id on the course
# Now reach a control but with a bad competitor id
%TEST_INFO = qw(Testname TestBadCompetitorOnCourse);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = "moc-who-is-not-there";
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output =~ m#\./reach_control\.php\?mumble=([a-zA-Z0-9+/=]+)#) {
  print "Processing redirect during " . $TEST_INFO{"Testname"} . ".\n";
  %GET = ();
  $GET{"mumble"} = $1;
  hashes_to_artificial_file();
  $cmd = "php ../reach_control.php";
  $output = qx($cmd);
}


if (($output !~ /Cannot find event/) || ($output !~ /please re-register and retry/)) {
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();






#####################
# Cleanup
qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
