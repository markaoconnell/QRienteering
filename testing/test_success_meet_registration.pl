#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my(%REGISTRATION_INFO);
my($COMPETITOR_FIRST_NAME) = "Mark";
my($COMPETITOR_LAST_NAME) = "OConnell_Registration_Testing";
my($COMPETITOR_NAME) = "${COMPETITOR_FIRST_NAME}--space--${COMPETITOR_LAST_NAME}";
my($competitor_id);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
set_no_redirects_for_event("UnitTestingEvent");


###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname TestSuccessRegistrationMemberWithStick);
%GET = qw(event UnitTestingEvent course 00-White);
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$GET{"competitor_name"} = $COMPETITOR_NAME;
%COOKIE = ();  # empty hash

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

success();




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestSuccessStart);
%COOKIE = qw(event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 3 - find all correct controls
# Validate that the correct entry is created
%TEST_INFO = qw(Testname FindAllValidControls);
%COOKIE = qw(event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;

$GET{"control"} = "201";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "202";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "203";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "204";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "205";
reach_control_successfully(4, \%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 4 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishSuccessWhite);
%COOKIE = qw(event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = (); # empty hash

finish_successfully(\%GET, \%COOKIE, \%TEST_INFO);

success();



############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
