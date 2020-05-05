#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($cmd, $output, $competitor_id);
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
# Test 3 - start with the right event but bad course
# Should return an error message
%TEST_INFO = qw(Testname TestStartGoodEventBadCourse);
%COOKIE = qw(event UnitTestingEvent course 03-Orange);
$COOKIE{"competitor_id"} = "moc";
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /ERROR: Bad registration for event "UnitTestingEvent"/) {
  error_and_exit("Web page output wrong, bad registration error not found.\n$output");
}

#print $output;

success();



###########
# Test 4 - start multiple times
# First register, then start
%TEST_INFO = qw(Testname MultipleStart);
%GET = qw(event UnitTestingEvent course 01-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};


# Now start the course
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);


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

my($path) = "./UnitTestingEvent/Competitors/$competitor_id";
@directory_contents = check_directory_contents($path, qw(name course controls_found));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}

@directory_contents = check_directory_contents("${path}/controls_found", qw(start));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in ${path}/controls_found than expected: " . join(",", @directory_contents));
}


success();




############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
