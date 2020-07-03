#!/usr/bin/perl

use strict;

use MIME::Base64;
require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output, $output2, $competitor_id, $competitor_id2, $path, $time_now, $controls_found_path);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_ReachControl_Bad";
my($COMPETITOR_NAME_2) = "Mark_OConnell_ReachControl_Bad_On_ScoreO";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");



###########
# Test 1 - reach a control without registering
# Should return an error message
%TEST_INFO = qw(Testname TestReachControlNotRegistered);
%COOKIE = qw(key UnitTestPlayground);
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
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
%COOKIE = qw(key UnitTestPlayground event OldEvent course 00-White);
$COOKIE{"competitor_id"} = "moc";
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);


if ($output =~ m#\./reach_control\.php\?mumble=([a-zA-Z0-9+/=]+)#) {
  print "Processing redirect during " . $TEST_INFO{"Testname"} . ".\n";
  %GET = ();
  $GET{"mumble"} = $1;
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/reach_control.php";
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
%COOKIE = qw(key UnitTestPlayground course 03-Orange);
$COOKIE{"competitor_id"} = "moc";
$COOKIE{"event"} = $event_id;
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
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
%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"competitor_name"} = $COMPETITOR_NAME_2;
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id2 = $TEST_INFO{"competitor_id"};


# Now reach a control but with a bad course
%COOKIE = qw(key UnitTestPlayground course 03-Orange);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output =~ m#\./reach_control\.php\?mumble=([a-zA-Z0-9+/=]+)#) {
  print "Processing redirect during " . $TEST_INFO{"Testname"} . ".\n";
  %GET = ();
  $GET{"mumble"} = $1;
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/reach_control.php";
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
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = "moc-who-is-not-there";
$COOKIE{"event"} = $event_id;
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output =~ m#\./reach_control\.php\?mumble=([a-zA-Z0-9+/=]+)#) {
  print "Processing redirect during " . $TEST_INFO{"Testname"} . ".\n";
  %GET = ();
  $GET{"mumble"} = $1;
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/reach_control.php";
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
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = qw(control 201);

hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output !~ /not started for $COMPETITOR_NAME, please return and scan Start QR code/) {
  error_and_exit("Web page output wrong, no failed to start message.\n$output");
}

# Try for the ScoreO competitor too
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = qw(control 301);

hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output !~ /not started for $COMPETITOR_NAME_2, please return and scan Start QR code/) {
  error_and_exit("Web page output wrong, no failed to start message.\n$output");
}

success();




##################
# Test: Reach the same control twice, after starting
# Should be fine
%TEST_INFO = qw(Testname TestReachControlTwice);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();
start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

%GET = qw(control 201);
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

# The second one should appear like the first
reach_control_again(0, \%GET, \%COOKIE, \%TEST_INFO);

success();


##################
# Test: Reach the same control twice, after starting
# Should be fine
%TEST_INFO = qw(Testname TestReachControlTwiceScoreO);
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = ();
start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

%GET = qw(control 301);
reach_score_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

# The second one should appear like the first
reach_score_control_again(0, \%GET, \%COOKIE, \%TEST_INFO);

success();


##################
# Test: Reach the same control again, after starting
# this time with the encoded mumble
%TEST_INFO = qw(Testname TestReachControlAgainWithMumble);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();
$GET{"mumble"} = encode_base64("201,$competitor_id," . time());
$TEST_INFO{"control"} = "201";

reach_control_again(0, \%GET, \%COOKIE, \%TEST_INFO);

success();


##################
# Test: Reach the same control again, after starting
# this time with the encoded mumble on a ScoreO
%TEST_INFO = qw(Testname TestReachControlAgainWithMumbleScoreO);
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = ();
$GET{"mumble"} = encode_base64("301,$competitor_id2," . time());
$TEST_INFO{"control"} = "301";

reach_score_control_again(0, \%GET, \%COOKIE, \%TEST_INFO);

success();


##################
# Test: Reach the correct control with a mumble
# 
%TEST_INFO = qw(Testname TestReachControlCorrectlyWithMumble);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();
$GET{"mumble"} = encode_base64("202,$competitor_id," . time());
$TEST_INFO{"control"} = "202";

reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

success();


##################
# Test: Reach the correct control with a mumble
#       On a ScoreO this time
%TEST_INFO = qw(Testname TestReachControlCorrectlyWithMumbleScoreO);
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = ();
$GET{"mumble"} = encode_base64("302,$competitor_id2," . time());
$TEST_INFO{"control"} = "302";

reach_score_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

success();


################
# Test: Reach the wrong control
%TEST_INFO = qw(Testname TestReachWrongControl);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = qw(control 345);

hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output !~ /Found wrong control: 345/) {
  error_and_exit("Web page output wrong, correct control string not found.\n$output");
}

#print $output;

$controls_found_path = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"} . "/Competitors/${competitor_id}/controls_found";
@directory_contents = check_directory_contents($controls_found_path, qw(start));
if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) || grep(/[0-9]+,345$/, @directory_contents)) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

success();


################
# Test: Reach the wrong control on a ScoreO
%TEST_INFO = qw(Testname TestReachWrongControlScoreO);
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = qw(control 345);

hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output !~ /Found wrong control, control 345 not on course ScoreO/) {
  error_and_exit("Web page output wrong, correct control string not found.\n$output");
}

#print $output;


$controls_found_path = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"} . "/Competitors/${competitor_id}/controls_found";
@directory_contents = check_directory_contents($controls_found_path, qw(start));
if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) || grep(/[0-9]+,345$/, @directory_contents)) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

success();


##################
# Test: Reach the same control again, after starting
# this time with the encoded mumble
# but with an old time (replay of old result)
%TEST_INFO = qw(Testname TestReachControlAgainWithMumbleTooLate);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();
$GET{"mumble"} = encode_base64("202,$competitor_id," . (time() - 300));

hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output !~ /ERROR: Time lag of > 30 seconds since scan of control 202 - incorrect page reload/) {
  error_and_exit("Web page output wrong, time lag error string not found.\n$output");
}

#print $output;

$controls_found_path = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"} . "/Competitors/${competitor_id}/controls_found";
@directory_contents = check_directory_contents($controls_found_path, qw(start));
if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


success();


##################
# Test: Reach a new control with an old mumble
# (not sure how this would happen, but let's confirm that it doesn't work)
%TEST_INFO = qw(Testname TestReachCorrectControlWithMumbleTooLate);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();
$GET{"mumble"} = encode_base64("203,$competitor_id," . (time() - 300));

hashes_to_artificial_file();
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output !~ /ERROR: Time lag of > 30 seconds since scan of control 203 - incorrect page reload/) {
  error_and_exit("Web page output wrong, time lag error string not found.\n$output");
}

#print $output;

$controls_found_path = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"} . "/Competitors/${competitor_id}/controls_found";
@directory_contents = check_directory_contents($controls_found_path, qw(start));
if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents) || grep(/[0-9]+,203$/, @directory_contents)) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


success();




#####################
# Cleanup
my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
