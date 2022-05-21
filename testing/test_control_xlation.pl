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

# This is confusing
# Assume that the maps are printed with controls 511-515, but those SI boxes are broken
# Now those SI boxes (with QR codes) are replaced by 201-205 (this could also happen if the
# maps are printed and the wrong set of QR codes was brought, or the QR codes for 511-515
# have water damage and are unreadable)
# To avoid reprinting the maps, QR codes 201-205 are placed on the course, but the labels are
# changed to 511-515.  The underlying course in QRienteering will say 201-205, but the display
# messages should say 511-515 (which matches what is on the map)
set_xlation_for_control($event_id, "UnitTestPlayground", "201", "511");
set_xlation_for_control($event_id, "UnitTestPlayground", "202", "512");
set_xlation_for_control($event_id, "UnitTestPlayground", "203", "513");
set_xlation_for_control($event_id, "UnitTestPlayground", "204", "514");
set_xlation_for_control($event_id, "UnitTestPlayground", "205", "515");


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
# Test 3 - find all correct controls
# Validate that the correct entry is created
%TEST_INFO = qw(Testname FindAllValidControls);
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"event"} = $event_id;
$COOKIE{"competitor_id"} = $competitor_id;

$GET{"control"} = "201";
reach_xlated_control(0, "511", \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "202";
reach_xlated_control(1, "512", \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "203";
reach_xlated_control(2, "513", \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "204";
reach_xlated_control(3, "514", \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "205";
reach_xlated_control(4, "515", \%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 4 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishSuccessWhite);
%COOKIE = qw(key UnitTestPlayground course 00-White);
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
