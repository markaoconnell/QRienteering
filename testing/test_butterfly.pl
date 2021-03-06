#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($COMPETITOR_NAME) = "Mark_OConnell_Flutterby";
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
%TEST_INFO = qw(Testname TestButterflyRegistration);
%GET = qw(key UnitTestPlayground course 03-Butterfly);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

success();




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestButterflyStart);
%COOKIE = qw(key UnitTestPlayground course 03-Butterfly);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 3 - find all correct controls
# Validate that the correct entry is created
%TEST_INFO = qw(Testname FindAllButterflyControls);
%COOKIE = qw(key UnitTestPlayground course 03-Butterfly);
$COOKIE{"event"} = $event_id;
$COOKIE{"competitor_id"} = $competitor_id;

$GET{"control"} = "401";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "402";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "403";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);

sleep(2);  # Make sure that the repeated control will have a different timestamp
$GET{"control"} = "401";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "404";
reach_control_successfully(4, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "405";
reach_control_successfully(5, \%GET, \%COOKIE, \%TEST_INFO);

sleep(2);  # Make sure that the repeated control will have a different timestamp
$GET{"control"} = "401";
reach_control_successfully(6, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "406";
reach_control_successfully(7, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "407";
reach_control_successfully(8, \%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 4 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishSuccessWhite);
%COOKIE = qw(key UnitTestPlayground course 03-Butterfly);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = (); # empty hash

finish_successfully(\%GET, \%COOKIE, \%TEST_INFO);

success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
