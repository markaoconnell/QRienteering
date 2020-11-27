#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($COMPETITOR_NAME) = "Mark_OConnell_Success_Testing";
my($competitor_id);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname TestSuccessRegistration);
%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

success();




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestSuccessStart);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 3 - find two correct controls
# Arrive at control 5 (skipped two)
#
# Validate that the correct entry is created
%TEST_INFO = qw(Testname SkipTwoControls);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"event"} = $event_id;
$COOKIE{"competitor_id"} = $competitor_id;

$GET{"control"} = "201";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "202";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "205";
hashes_to_artificial_file();
my($cmd, $output);
$cmd = "php ../OMeet/reach_control.php";
$output = qx($cmd);

if ($output !~ /Found wrong control: 205/) {
  error_and_exit("Web page output wrong, correct control string not found.\n$output");
}

#print $output;

my($controls_found_path, @directory_contents);
$controls_found_path = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"} . "/Competitors/${competitor_id}/controls_found";
@directory_contents = check_directory_contents($controls_found_path, qw(start));
if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) || grep(/[0-9]+,205$/, @directory_contents)) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}

if ($output !~ /input type=submit value="Skip controls: 203, 204/) {
  error_and_exit("Did not see form (button) for skipping the controls.\n$output");
}

if ($output !~ /input type=hidden name="skipped_controls" value="skip-203,skip-204"/) {
  error_and_exit("Did not see form (skipped_controls) for skipping the controls.\n$output");
}
 
success();


###########
# Test 4 - Arrive at control 5
# Two controls skipped acknowledged
# Validate that the correct entry is created
%TEST_INFO = qw(Testname SkipTwoControlsOKed);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"event"} = $event_id;
$COOKIE{"competitor_id"} = $competitor_id;

%GET = %COOKIE;
$COOKIE{"${competitor_id}_skipped_controls"} = "skip-203,skip-204";
$GET{"control"} = "205";
reach_control_with_skip(4, \%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 5 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishWithSkippedControls);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
$COOKIE{"${competitor_id}_skipped_controls"} = "skip-203,skip-204";
%GET = (); # empty hash

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);

success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
