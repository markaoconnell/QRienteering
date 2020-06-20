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
my($cmd, $output);

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
# Test 2 - try and start the course
# Should error - si stick starters done use QR start
%TEST_INFO = qw(Testname TestQRStartWithSiStickFails);
%COOKIE = qw(event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /ERROR: ${COMPETITOR_FIRST_NAME} ${COMPETITOR_LAST_NAME} registered for UnitTestingEvent with si_stick, should not scan start QR code/) {
  error_and_exit("Web page output wrong, bad registration error not found.\n$output");
}

#print $output;

success();


###########
# Test 3 - Try scanning a control
# Si stick users should not scan controls
%TEST_INFO = qw(Testname ReachControlWhenUsingSiStickFails);
%COOKIE = qw(event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();
$GET{"control"} = "201";

hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output !~ /registered with si stick, should not scan QR code/) {
  error_and_exit("Web page output wrong, should receive QR scanning error.\n$output");
}

#print $output;

success();


###############
# Test 4 - Validate non-SI stick users can still register and run the course
#
%TEST_INFO = qw(Testname MixingQRandSiStickUsersWorks);
%GET = qw(event UnitTestingEvent course 00-White competitor_name MarkOconnellQREntry);
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($qr_competitor_id) = $TEST_INFO{"competitor_id"};

%COOKIE = qw(event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $qr_competitor_id;
%GET = ();
start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

%GET = ();
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

%GET = (); # empty hash
finish_successfully(\%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 5 - SI stick users should not scan the finish QR code
#
%TEST_INFO = qw(Testname TestQRFinishScanBySiStickUserFails);
%COOKIE = qw(event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;

%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if ($output !~ /ERROR: If using si stick, do not scan the finish QR code, use si stick to finish instead/) {
  error_and_exit("Web page output wrong, should receive error about scanning QR code.\n$output");
}

#print $output;

success();

###########
# Test 6 - Si Stick user finishes the course
# 
%TEST_INFO = qw(Testname TestSiStickFinishAfterQRFinisher);
%COOKIE = ();
%GET = qw(event UnitTestingEvent); # empty hash

my(@si_results) = qw(2108369;200 start:200 finish:800 201:210 202:300 203:440 204:600 205:700);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_id, "00-White", \%GET, \%COOKIE, \%TEST_INFO);

success();


############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
