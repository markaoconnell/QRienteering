#!/usr/bin/perl

use strict;
use MIME::Base64;

require "../testing/testHelpers.pl";
require "./setup_member_info.pl";
require "../testing/success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output);

create_key_file();
mkdir(get_base_path("UnitTestPlayground"));
setup_member_files(get_base_path("UnitTestPlayground"));

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);

initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");



###############
# Take the registration_info field and crack it into its constituent parts
sub get_specified_info {
  my($info) = @_;

  my(@fields) = split(",", $info);
  my(%field_hash);
  my($i);
  for ($i = 0; $i < scalar(@fields); $i += 2) {
    $field_hash{$fields[$i]} = decode_base64($fields[$i + 1]);
  }

  return(\%field_hash);
}


###############
# Compare the two hashes - they should be identical
# Report on any discrepancies
sub compare_hashes {
  my($expected_hash_ref, $actual_hash_ref) = @_;

  # Copy the hashes so they can be manipulated with impunity
  my(%expected_hash) = %{$expected_hash_ref};
  my(%actual_hash) = %{$actual_hash_ref};

  my($error_string) = "";

  my($key);
  foreach $key (keys(%expected_hash)) {
    if (defined($actual_hash{$key})) {
      if ($expected_hash{$key} ne $actual_hash{$key}) {
        $error_string .= "$key has different values - expected \"$expected_hash{$key}\" vs actual \"$actual_hash{$key}\"\n";
      }
      delete($actual_hash{$key});
    }
    else {
      $error_string .= "$key not found in actual hash\n";
    }
  }

  # The actual hash contains extra values!
  if (scalar(keys(%actual_hash)) != 0) {
    my(@extra_fields) = map { "$_ => $actual_hash{$_}" } keys(%actual_hash) ;
    $error_string .= "Extra values found in actual hash:\n\t" . join("\n\t", @extra_fields) . "\n";
  }

  return($error_string);
}



###########
# Test 1 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberUsingStick);
%GET = qw(key UnitTestPlayground member_id 31 si_stick 3959473 cell_number 5083959473 email karen@mkoconnell.com waiver_signed signed quick_lookup_member_id 11-31);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^&"]+)[&"]#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Karen last_name Yeowell club_name NEOC si_stick 3959473 member_id 31 is_member yes email_address karen@mkoconnell.com);
$expected_hash{"cell_phone"} = "5083959473";
$expected_hash{"car_info"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();


###########
# Test 2 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberUsingCarAndPhone);
%GET = qw(key UnitTestPlayground member_id 31 si_stick 141421 car_info VWFox cell_number 5083959473 waiver_signed signed quick_lookup_member_id 11-31);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^&"]+)[&"]#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Karen last_name Yeowell club_name NEOC si_stick 141421 member_id 31 is_member yes car_info VWFox cell_phone 5083959473);
$expected_hash{"email_address"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();

###########
# Test 3 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberNotUsingStick);
%GET = qw(key UnitTestPlayground member_id 31 cell_number 5083959473 waiver_signed signed quick_lookup_member_id 11-31);
$GET{"si_stick"} = "";
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^&"]+)[&"]#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Karen last_name Yeowell club_name NEOC member_id 31 is_member yes);
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "5083959473";
$expected_hash{"car_info"} = "";
$expected_hash{"si_stick"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();

###########
# Test 4 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberOtherClubDefault);
%GET = qw(key UnitTestPlayground member_id 41 cell_number 5086148225 si_stick 1421 waiver_signed signed quick_lookup_member_id 1-41);
$GET{"event"} = $event_id;
set_club_name("UnitTestPlayground", "BOK");
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^&"]+)[&"]#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Larry last_name Berrill club_name BOK member_id 41 is_member yes si_stick 1421);
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "5086148225";
$expected_hash{"car_info"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

unset_club_name("UnitTestPlayground");

success();





###########
# Test 5 - Failed member registration - bad stick id specified
# 
%TEST_INFO = qw(Testname TestMemberUsingBadStickNumber);
%GET = qw(key UnitTestPlayground member_id 41 cell_number 123456789 si_stick 14xx21 waiver_signed signed quick_lookup_member_id 1-41);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ /Invalid SI unit id "14xx21", only numbers allowed.  Please go back and re-enter/) {
  error_and_exit("Bad si unit id error message not found.\n$output");
}

success();


###########
# Test 6 - Failed member registration - no waiver
# 
%TEST_INFO = qw(Testname TestMemberNoWaiver);
%GET = qw(key UnitTestPlayground member_id 41 cell_number 123456789 si_stick 1421 quick_lookup_member_id 1-41);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ /The waiver must be acknowledged in order to participate in this event./) {
  error_and_exit("No waiver error message not found.\n$output");
}

success();




###########
# Test 8 - Failed member registration - no member id specified
# 
%TEST_INFO = qw(Testname TestNoMemberId);
%GET = qw(key UnitTestPlayground waiver_signed signed);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ /please go back and enter a valid first name/) {
  error_and_exit("Unexpected error when member_id not specified.\n$output");
}

success();




###########
# Test 11 - Failed member registration - bad id
# 
%TEST_INFO = qw(Testname TestMemberUsingBadId);
%GET = qw(key UnitTestPlayground member_id 17100 si_stick 314159 waiver_signed signed quick_lookup_member_id 1-17100);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ /Invalid \(empty\) first name/) {
  error_and_exit("Redirect URL not found.\n$output");
}

success();

###########
# Test 12 - Failure - no event specified
# 
%TEST_INFO = qw(Testname TestNoEventSpecified);
%GET = qw(key UnitTestPlayground member_id 17100 si_stick 314159 waiver_signed signed quick_lookup_member_id 1-17100);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_registration.php";
$output = qx($cmd);

if ($output !~ /Unknown event \(empty\)/) {
  error_and_exit("Error message not found that event was not specified.\n$output");
}

success();


#################
# End the test successfully
remove_member_files(get_base_path("UnitTestPlayground"));
my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
