#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output, $competitor_id);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_Bad_Start";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


###########
# Test 1 - start the course without registering
# Should return an error message
%TEST_INFO = qw(Testname TestStartNoRegistration);
%COOKIE = ();
%GET = qw(key UnitTestPlayground);  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/start_course.php";
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
%COOKIE = qw(key UnitTestPlayground event OldEvent course 00-White);
$COOKIE{"competitor_id"} = "moc";
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/start_course.php";
$output = qx($cmd);

if ($output !~ /ERROR: Bad event "OldEvent", was this created properly/) {
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();


###########
# Test 3 - start with the right event but bad course
# Should return an error message
%TEST_INFO = qw(Testname TestStartGoodEventBadCourse);
%COOKIE = qw(key UnitTestPlayground course 03-Orange);
$COOKIE{"competitor_id"} = "moc";
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/start_course.php";
$output = qx($cmd);

if ($output !~ /ERROR: Bad registration for event "UnitTesting" and competitor "moc", please reregister and try again/) {
  error_and_exit("Web page output wrong, bad registration error not found.\n$output");
}

#print $output;

success();



###########
# Test 4 - start multiple times
# First register, then start
%TEST_INFO = qw(Testname MultipleStart);
%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};


# Now start the course
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

sleep 5;  # Add a delay to make sure we get a new start time

# Now start the course again, it should succeed (no controls found yet)
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);


# Now find a control and start the course again - this is the real part of the test
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = qw(control 201);  # control we reached

reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/start_course.php";
$output = qx($cmd);

if ($output !~ /already started for $COMPETITOR_NAME/) {
  error_and_exit("Web page output wrong, course start string not found.\n$output");
}

#print $output;

my($path) = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"} . "/Competitors/$competitor_id";
@directory_contents = check_directory_contents($path, qw(name course controls_found));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

# Should be one entry for the control that was found
@directory_contents = check_directory_contents("${path}/controls_found", qw(start));
if ($#directory_contents != 0) {
  error_and_exit("More files exist in ${path}/controls_found than expected: " . join("--", @directory_contents));
}


success();




############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
