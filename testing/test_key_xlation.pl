#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($COMPETITOR_NAME) = "Mark_OConnell_Success_Testing";
my($competitor_id);
my($cmd, $output);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


# Scripts to check
# view_results
# on_course
# register (BYOM)
# manage_events
# self_report_1
# member registration / non-member registration / preregistered checkin

###########
# Test 1 - view results with a xlation key

%TEST_INFO = qw(Testname TestViewResultsXlt);
%GET = qw(key UnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeet/view_results.php";
$output = qx($cmd);

if ($output !~ m#../OMeet/view_results.php\?event=${event_id}&key=UnitTestPlayground#) {
  error_and_exit("Did not see view results output properly.\n${output}");
}

if ($output !~ /####,Event,${event_id},/) {
  error_and_exit("Did not see view results parseable output.\n${output}");
}


success();

###########
# Test 2 - on_course with a xlation key

%TEST_INFO = qw(Testname TestOnCourseXlt);
%GET = qw(key UnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeet/on_course.php";
$output = qx($cmd);

if ($output !~ /Competitors not yet finished for/) {
  error_and_exit("Did not see on_course output properly.\n${output}");
}

success();

###########
# Test 3 - register with a xlation key

%TEST_INFO = qw(Testname TestRegisterXlt);
%GET = qw(key UnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register.php";
$output = qx($cmd);

if ($output !~ /Registration for orienteering event: UnitTesting/) {
  error_and_exit("Did not see registration output properly.\n${output}");
}

if ($output !~ /####,COURSE,Brown,07-Brown/) {
  error_and_exit("Did not registration parseable output for courses.\n${output}");
}

success();

###########
# Test 4 - manage events with a xlation_key

%TEST_INFO = qw(Testname TestManageEventXlt);
%GET = qw(key UnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/manage_events.php";
$output = qx($cmd);

if ($output =~ /[?&]key=UnitTestXlt/) {
  error_and_exit("Why is the xlation key used as a regular key?\n${output}");
}

if ($output !~ /orig_key=UnitTestXlt/) {
  error_and_exit("Did not see xlation key used as original key (should be just for QR codes).\n${output}");
}

my($count);
$count = () = ($output =~ /orig_key=UnitTestXlt/);
if ($count != 1) {
  error_and_exit("Saw too many times that xlation key was used as original key (should be just for QR codes).\n${output}");
}

success();

###########
# Test 5 - meet registration with a xlation_key

%TEST_INFO = qw(Testname TestMeetRegistrationXlt);
%GET = qw(key UnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/competition_register.php";
$output = qx($cmd);

if ($output !~ /action=".\/add_safety_info.php"/) {
  error_and_exit("Did not see add_safety_info as form action.\n${output}");
}

if ($output !~ /<input type=\"hidden\" name=\"key\" value=\"UnitTestPlayground\" >/) {
  error_and_exit("Did see xlated key in the form as a hidden input.\n${output}");
}

success();

###########
# Test 6 - view results with a bad key

%TEST_INFO = qw(Testname TestViewResultsBadKey);
%GET = qw(key UnitTestXltBad);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeet/view_results.php";
$output = qx($cmd);

if ($output !~ /Unknown key/) {
  error_and_exit("Did not see unknown key error message.\n${output}");
}

if ($output =~ /####,Event,${event_id},/) {
  error_and_exit("Did see view results parseable output.\n${output}");
}


success();

###########
# Test 7 - on_course with a xlation key

%TEST_INFO = qw(Testname TestOnCourseBadKey);
%GET = qw(key UnitTestXltBad);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeet/on_course.php";
$output = qx($cmd);

if ($output !~ /Unknown key/) {
  error_and_exit("Did not see unknown key error message.\n${output}");
}

success();

###########
# Test 8 - register with a bad key

%TEST_INFO = qw(Testname TestRegisterBadKey);
%GET = qw(key UnitTestBadXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register.php";
$output = qx($cmd);

if ($output !~ /Unknown key/) {
  error_and_exit("Did not see unknown key error message.\n${output}");
}

if ($output =~ /####,COURSE,Brown,07-Brown/) {
  error_and_exit("Did not registration parseable output for courses.\n${output}");
}

success();

###########
# Test 9 - manage events with a bad key

%TEST_INFO = qw(Testname TestManageEventXltBad);
%GET = qw(key BadUnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/manage_events.php";
$output = qx($cmd);

if ($output !~ /No such access key/) {
  error_and_exit("Did not see unknown key error message.\n${output}");
}

success();

###########
# Test 10 - meet registration with a bad key

%TEST_INFO = qw(Testname TestMeetRegistrationBadKey);
%GET = qw(key NoSuchUnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/competition_register.php";
$output = qx($cmd);

if ($output !~ /Unknown key/) {
  error_and_exit("Did not see unknown key error message.\n${output}");
}

success();


###########
# Test 11 - QR code generation key management

%TEST_INFO = qw(Testname TestQRCodeWithKeyTranslation);
%GET = qw(orig_key UnitTestXlt key UnitTestPlayground);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/get_event_qr_codes.php";
$output = qx($cmd);

if ($output !~ /\/register.php.*key=UnitTestPlayground.*Event specific.*BYOM registration/) {
  error_and_exit("Did not see proper link for BYOM for a specific event.\n${output}");
}

if ($output !~ /\/register.php.*key=UnitTestXlt.*Generic BYOM registration/) {
  error_and_exit("Did not see proper link for generic BYOM.\n${output}");
}

if ($output !~ /competition_register.php.*key=UnitTestXlt.*Generic registration/) {
  error_and_exit("Did not see proper link for generic meet registration.\n${output}");
}

if ($output !~ /competition_register.php.*key=UnitTestXlt.*Non member registration/) {
  error_and_exit("Did not see proper link for non-member meet registration.\n${output}");
}

if ($output !~ /competition_register.php.*key=UnitTestXlt.*Member registration/) {
  error_and_exit("Did not see proper link for member meet registration.\n${output}");
}

if ($output !~ /view_results.php.*key=UnitTestPlayground.*View results of UnitTesting/) {
  error_and_exit("Did not see proper link for view results for a specific event.\n${output}");
}

if ($output !~ /on_course.php.*key=UnitTestPlayground.*View competitors still running for UnitTesting/) {
  error_and_exit("Did not see proper link for competitors on course for a specific event.\n${output}");
}

if ($output !~ /self_report_1.php.*key=UnitTestPlayground.*Self report a result for UnitTesting/) {
  error_and_exit("Did not see proper link for competitors self reporting for a specific event.\n${output}");
}

if ($output !~ /view_results.php.*key=UnitTestXlt.*View results of open events .reusable./) {
  error_and_exit("Did not see proper link for view results for all events.\n${output}");
}

if ($output !~ /on_course.php.*key=UnitTestXlt.*View competitors still running for any open event .reusable./) {
  error_and_exit("Did not see proper link for competitors on course for all open events.\n${output}");
}

if ($output !~ /self_report_1.php.*key=UnitTestXlt.*Self report a result .reusable./) {
  error_and_exit("Did not see proper link for competitors self reporting for all events.\n${output}");
}

success();


###########
# Test 12 - self_report with a xlation_key

%TEST_INFO = qw(Testname TestSelfReportXlt);
%GET = qw(key UnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_1.php";
$output = qx($cmd);

if ($output !~ /action=".\/self_report_2.php"/) {
  error_and_exit("Did not see self_report_2 as form action.\n${output}");
}

if ($output !~ /<input type=\"hidden\" name=\"key\" value=\"UnitTestPlayground\">/) {
  error_and_exit("Did not see xlated key in the form as a hidden input.\n${output}");
}

success();

###########
# Test 13 - self_report with a bad key

%TEST_INFO = qw(Testname TestSelfReportBadKey);
%GET = qw(key UnitTestXltBad);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_1.php";
$output = qx($cmd);

if ($output !~ /Unknown key/) {
  error_and_exit("Did not see unknown key error message.\n${output}");
}


success();






############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
