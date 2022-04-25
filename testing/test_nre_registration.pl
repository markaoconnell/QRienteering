#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my(%REGISTRATION_INFO);
my($cmd, $output, $competitor_id);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_NRE_Registration";
my($COMPETITOR_FIRST_NAME) = "Mark";
my($COMPETITOR_LAST_NAME) = "_OConnell_NRE_Registration";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");

# Set up the NRE classification tables
set_using_nre_classes("UnitTestPlayground", $event_id);
set_nre_classes("UnitTestPlayground");


###########
# Test 1 - Register normally and see if classified correctly
# 
%TEST_INFO = qw(Testname TestRegisterMale55Green);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"course"} = "05-Green";
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$REGISTRATION_INFO{"classification_info"} = values_to_classification_info("1967", "m", "");
my($raw_registration_info) = hash_to_registration_info_string(\%REGISTRATION_INFO);
$GET{"registration_info"} = $raw_registration_info;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /\#\#\#\#,CLASS,M55\+/) {
  error_and_exit("Web page output wrong, correct parseable class entry not found.\n$output");
}

print $output;

success();

###########
# Test 2 - Register normally for a rec course (no birth year or gender specified)
# 
%TEST_INFO = qw(Testname TestRegisterRecGreen);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"course"} = "05-Green";
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
#$REGISTRATION_INFO{"classification_info"} = values_to_classification_info("1967", "m", "");
my($raw_registration_info) = hash_to_registration_info_string(\%REGISTRATION_INFO);
$GET{"registration_info"} = $raw_registration_info;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output =~ /\#\#\#\#,CLASS/) {
  error_and_exit("Web page output wrong, parseable class entry was found.\n$output");
}

print $output;

success();

###########
# Test 3 - Register normally for an open course 
# 
%TEST_INFO = qw(Testname TestRegisterMaleOpenBrown);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"course"} = "07-Brown";
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$REGISTRATION_INFO{"classification_info"} = values_to_classification_info("1967", "m", "");
my($raw_registration_info) = hash_to_registration_info_string(\%REGISTRATION_INFO);
$GET{"registration_info"} = $raw_registration_info;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /\#\#\#\#,CLASS,M Brown/) {
  error_and_exit("Web page output wrong, correct parseable class entry was not found.\n$output");
}

print $output;

success();

exit();



###########
# Test 2 - Register with no name
# Should return an error message
%TEST_INFO = qw(Testname TestStartRegistrationNoName);
%COOKIE = ();
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"course"} = "00-White";
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /Competitor name must be specified/) {
  error_and_exit("Web page output wrong, should receive error about no competitor name.\n$output");
}

#print $output;

success();



###########
# Test 3 - Register with no course
# Should return an error message
%TEST_INFO = qw(Testname TestStartRegistrationNoCourse);
%COOKIE = ();
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /Course must be specified/) {
  error_and_exit("Web page output wrong, should receive error about no course specified.\n$output");
}

#print $output;

success();





############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
