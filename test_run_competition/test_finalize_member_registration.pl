#!/usr/bin/perl

use strict;
use MIME::Base64;

require "../testing/testHelpers.pl";
require "./setup_member_info.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output);

create_key_file();
mkdir(get_base_path("UnitTestPlayground"));
setup_member_files(get_base_path("UnitTestPlayground"));

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);

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
%TEST_INFO = qw(Testname TestMemberUsingDefaultStick);
%GET = qw(key UnitTestPlayground member_id 31 using_stick yes si_stick_number 3959473);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Karen last_name Yeowell club_name NEOC si_stick 3959473 is_member yes);
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "";
$expected_hash{"car_info"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();


###########
# Test 2 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberUsingDifferentStick);
%GET = qw(key UnitTestPlayground member_id 31 using_stick yes si_stick_number 141421);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Karen last_name Yeowell club_name NEOC si_stick 141421 is_member yes);
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "";
$expected_hash{"car_info"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();

###########
# Test 3 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberNotUsingStick);
%GET = qw(key UnitTestPlayground member_id 31 using_stick no si_stick_number 141421);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Karen last_name Yeowell club_name NEOC is_member yes);
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "";
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
%TEST_INFO = qw(Testname TestMemberNotUsingStickBadStickNumber);
%GET = qw(key UnitTestPlayground member_id 41 using_stick no si_stick_number 14xx21);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Larry last_name Berrill club_name NEOC is_member yes);
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "";
$expected_hash{"car_info"} = "";
$expected_hash{"si_stick"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();



###########
# Test 5 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberUsingStickButNoDefault);
%GET = qw(key UnitTestPlayground member_id 171 using_stick yes si_stick_number 314159);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Peter last_name Amram club_name NEOC is_member yes si_stick 314159);
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "";
$expected_hash{"car_info"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();



###########
# Test 6 - Failed member registration - bad stick id specified
# 
%TEST_INFO = qw(Testname TestMemberUsingBadStickNumber);
%GET = qw(key UnitTestPlayground member_id 41 using_stick yes si_stick_number 14xx21);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ /Yes specified for SI stick usage but invalid SI stick number found/) {
  error_and_exit("Bad stick error message not found.\n$output");
}

success();


###########
# Test 7 - Failed member registration - bad stick id specified
# 
%TEST_INFO = qw(Testname TestMemberUsingEmptyStickNumber);
%GET = qw(key UnitTestPlayground member_id 41 using_stick yes);
$GET{"si_stick_number"} = "";
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ /Yes specified for SI stick usage but no SI stick number found/) {
  error_and_exit("Bad stick error message not found.\n$output");
}

success();


###########
# Test 8 - Failed member registration - no member id specified
# 
%TEST_INFO = qw(Testname TestNoMember);
%GET = qw(key UnitTestPlayground using_stick yes si_stick_number 14xx21);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ /No member id specified/) {
  error_and_exit("Bad error message: \"No member id\" not found.\n$output");
}

success();

###########
# Test 9 - Failed member registration - using stick must be yes or no
# 
%TEST_INFO = qw(Testname TestUsingStickWrong);
%GET = qw(key UnitTestPlayground member_id 314 using_stick maybe si_stick_number 14xx21);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ /Invalid value \"maybe\" for SI stick usage/) {
  error_and_exit("Bad stick error message not found.\n$output");
}

success();

###########
# Test 10 - Failed member registration - Using stick with no ID
# 
%TEST_INFO = qw(Testname TestUsingStickButNoStick);
%GET = qw(key UnitTestPlayground member_id 314 using_stick yes);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ /Yes specified for SI stick usage but no SI stick number found/) {
  error_and_exit("Bad stick error message not found.\n$output");
}

success();



###########
# Test 11 - Failed member registration - bad id
# 
%TEST_INFO = qw(Testname TestMemberUsingBadId);
%GET = qw(key UnitTestPlayground member_id 17100 using_stick yes si_stick_number 314159);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/finalize_member_registration.php";
$output = qx($cmd);

if ($output !~ /No such member id 17100 found/) {
  error_and_exit("Redirect URL not found.\n$output");
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
