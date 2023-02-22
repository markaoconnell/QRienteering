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

#print $output;

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

#print $output;

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

#print $output;

success();

###########
# Test 4 - Pre-specified class info overrides birth year/gender
# 
%TEST_INFO = qw(Testname TestRegisterMaleForceOpenRed);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"course"} = "07-Brown";
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$REGISTRATION_INFO{"classification_info"} = values_to_classification_info("1967", "m", "M Red");
my($raw_registration_info) = hash_to_registration_info_string(\%REGISTRATION_INFO);
$GET{"registration_info"} = $raw_registration_info;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /\#\#\#\#,CLASS,M Red/) {
  error_and_exit("Web page output wrong, correct parseable class entry was not found.\n$output");
}

#print $output;

success();


###########
# Test 5 - Only one of birth_year / gender specified - no class assigned
# 
%TEST_INFO = qw(Testname TestRegisterMaleNoBirthYear);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"course"} = "07-Brown";
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$REGISTRATION_INFO{"classification_info"} = values_to_classification_info("", "m", "");
my($raw_registration_info) = hash_to_registration_info_string(\%REGISTRATION_INFO);
$GET{"registration_info"} = $raw_registration_info;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output =~ /\#\#\#\#,CLASS,/) {
  error_and_exit("Web page output wrong, parseable class entry was incorrectly found.\n$output");
}

#print $output;

success();

###########
# Test 6 - No gender specified - no class assigned
# 
%TEST_INFO = qw(Testname TestRegisterBirthYearNoGender);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"course"} = "07-Brown";
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$REGISTRATION_INFO{"classification_info"} = values_to_classification_info("1967", "", "");
my($raw_registration_info) = hash_to_registration_info_string(\%REGISTRATION_INFO);
$GET{"registration_info"} = $raw_registration_info;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output =~ /\#\#\#\#,CLASS,/) {
  error_and_exit("Web page output wrong, parseable class entry was incorrectly found.\n$output");
}

#print $output;

success();


###########
# Test 7 - 
# Add safety info - Prefilled birth_year / gender if no class specified
%TEST_INFO = qw(Testname AddSafetyInfoBirthYearGenderPrefilled);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"competitor_first_name"} = $COMPETITOR_FIRST_NAME;
$GET{"competitor_last_name"} = $COMPETITOR_LAST_NAME;
$GET{"course"} = "07-Brown";
$GET{"si_stick"} = "";
$GET{"member_id"} = "";
$GET{"club_name"} = "";
$GET{"classification_info"} = values_to_classification_info("1967", "m", "");
hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /name="birth_year" value="1967"/) {
  error_and_exit("Web page output wrong, birth year was not prefilled.\n$output");
}

if ($output !~ /name="gender" value="m" checked/) {
  error_and_exit("Web page output wrong, gender was not prefilled.\n$output");
}

#print $output;

success();

###########
# Test 8 - 
# Add safety info - No prefilled birth_year / gender if class specified
%TEST_INFO = qw(Testname AddSafetyInfoNoBirthYearGenderWithClassSpecified);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"competitor_first_name"} = $COMPETITOR_FIRST_NAME;
$GET{"competitor_last_name"} = $COMPETITOR_LAST_NAME;
$GET{"course"} = "07-Brown";
$GET{"si_stick"} = "";
$GET{"member_id"} = "";
$GET{"club_name"} = "";
$GET{"classification_info"} = values_to_classification_info("1967", "m", "M Brown");
hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output =~ /name="birth_year" value=/) {
  error_and_exit("Web page output wrong, birth year prompt is present.\n$output");
}

if ($output =~ /name="gender" value=/) {
  error_and_exit("Web page output wrong, gender prompt is present.\n$output");
}

#print $output;

success();

###########
# Test 9 - 
# Add safety info - Blank birth year / gender if no classification info
%TEST_INFO = qw(Testname AddSafetyInfoBlankBirthYearGenderPromptsWhenNoPresuppliedInfo);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"competitor_first_name"} = $COMPETITOR_FIRST_NAME;
$GET{"competitor_last_name"} = $COMPETITOR_LAST_NAME;
$GET{"course"} = "07-Brown";
$GET{"si_stick"} = "";
$GET{"member_id"} = "";
$GET{"club_name"} = "";
#$GET{"classification_info"} = values_to_classification_info("1967", "m", "M Brown");
hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /name="birth_year" value=""/) {
  error_and_exit("Web page output wrong, blank birth year prompt not present.\n$output");
}

if ($output !~ /name="gender" value="m" *>/) {
  error_and_exit("Web page output wrong, unchecked gender prompt not present.\n$output");
}

if ($output !~ /name="gender" value="f" *>/) {
  error_and_exit("Web page output wrong, unchecked gender prompt not present.\n$output");
}

#print $output;

success();

###########
# Test 10 - 
# Finalize_registration - Supplied birth_year / gender overrides what was found in member DB (or preregistration DB)
%TEST_INFO = qw(Testname FinalizeSuppliedValuesOverrideMemberDBValues);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"competitor_first_name"} = $COMPETITOR_FIRST_NAME;
$GET{"competitor_last_name"} = $COMPETITOR_LAST_NAME;
$GET{"course"} = "07-Brown";
$GET{"si_stick"} = "";
$GET{"cell_number"} = "5086148225";
$GET{"waiver_signed"} = "signed";
$GET{"member_id"} = "";
$GET{"club_name"} = "";
$GET{"classification_info"} = values_to_classification_info("1967", "m", "M Brown");
$GET{"birth_year"} = "2001";
$GET{"gender"} = "f";
hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ /OMeetRegistration\/register.php/) {
  error_and_exit("Web page output wrong, register.php redirect not found.\n$output");
}

if ($output !~ /registration_info=([^&"]*)[&"]/) {
  error_and_exit("Web page output wrong, registration_info parameters not found.\n$output");
}

my($found_registration_info) = $1;
#print "Found registration info is: $found_registration_info.\n";

my(%forwarded_info) = registration_info_string_to_hash($found_registration_info);
#print "Classification info is: " . $forwarded_info{"classification_info"} . "\n";
#print "It should be          : " . values_to_classification_info("2001", "f", "M Brown") . "\n";
if ($forwarded_info{"classification_info"} ne values_to_classification_info("2001", "f", "M Brown")) {
  error_and_exit("New values 2001 and f did not override original values of 1967 and m for OUSA classification.\n$output");
}

#print $output;

success();

###########
# Test 11 - 
# Finalize_registration - No cell phone found, should error
%TEST_INFO = qw(Testname FinalizeNoCellPhoneProvided);
%COOKIE = ();
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"competitor_first_name"} = $COMPETITOR_FIRST_NAME;
$GET{"competitor_last_name"} = $COMPETITOR_LAST_NAME;
$GET{"course"} = "07-Brown";
$GET{"si_stick"} = "";
#$GET{"cell_number"} = "5086148225";
$GET{"waiver_signed"} = "signed";
$GET{"member_id"} = "";
$GET{"club_name"} = "";
$GET{"classification_info"} = values_to_classification_info("1967", "m", "M Brown");
$GET{"birth_year"} = "2001";
$GET{"gender"} = "f";
hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ /Invalid \(empty\) cell phone/) {
  error_and_exit("Cell phone missing error not found.\n$output");
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
