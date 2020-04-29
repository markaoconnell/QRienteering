#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

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
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if (($output !~ /Cannot find event/) || ($output !~ /please re-register and retry/)) {
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

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};


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

##################
# Test: Reach a control before starting the course
# 
%TEST_INFO = qw(Testname TestReachControlNoStart);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = qw(control 201);

hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output !~ /not started for $COMPETITOR_NAME, please return and scan Start QR code/) {
  error_and_exit("Web page output wrong, no failed to start message.\n$output");
}

success();




##################
# Test: Reach the same control twice, after starting
# Should be fine
%TEST_INFO = qw(Testname TestReachControlTwice);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();
start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

%GET = qw(control 201);
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

# The second one should appear like the first
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

success();


################
# Test: Reach the wrong control
%TEST_INFO = qw(Testname TestReachWrongControl);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = qw(control 345);

hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output !~ /Found wrong control: 345/) {
  error_and_exit("Web page output wrong, correct control string not found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (-f "$path/1") {
  error_and_exit("$path/1 exists, should be not as control was wrong.");
}

@directory_contents = check_directory_contents($path, qw(name course start 0 extra));
if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) || grep(/dnf/, @directory_contents)) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}

success();




#####################
# Cleanup
qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
