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
# Test 1 - Success non-member registration
# 
%TEST_INFO = qw(Testname TestNonMemberAllInfoProvided);
%GET = qw(key UnitTestPlayground competitor_first_name Mark competitor_last_name OConnell club_name QOC si_stick 32768 email mark@mkoconnell.com cell_number 5086148225 car_info ChevyBoltEV3470 waiver_signed signed);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Mark last_name OConnell club_name QOC si_stick 32768 email_address mark@mkoconnell.com
                        cell_phone 5086148225 car_info ChevyBoltEV3470 is_member no);

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();

###########
# Test 2 - Success non-member registration
# Even if they claim to be a NEOC member, they are registered as a non-member
%TEST_INFO = qw(Testname TestNonMemberAllInfoProvidedClaimsNEOC);
%GET = qw(key UnitTestPlayground competitor_first_name Isabelle competitor_last_name Davenport club_name NEOC si_stick 32768 email mark@mkoconnell.com cell_number 5086148225 car_info ChevyBoltEV3470 waiver_signed signed);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Isabelle last_name Davenport club_name NEOC si_stick 32768 email_address mark@mkoconnell.com
                        cell_phone 5086148225 car_info ChevyBoltEV3470 is_member no);

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();

###########
# Test 3 - Fail non-member registration - bad stick number
# 
%TEST_INFO = qw(Testname TestNonMemberBadStickId);
%GET = qw(key UnitTestPlayground competitor_first_name Dasha competitor_last_name Wolfson club_name UNO si_stick 1o24 email dasha@umassamherst.edu cell_number 5083291200 car_info RedCamaro waiver_signed signed);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ /Invalid si_stick/) {
  error_and_exit("Did not detect poor si_stick.\n$output");
}

success();


###########
# Test 4 - Fail non-member registration - no first name
# 
%TEST_INFO = qw(Testname TestNonMemberNoFirstName);
%GET = qw(key UnitTestPlayground competitor_last_name Baldwin club_name UNO si_stick 124 email dasha@umassamherst.edu cell_number 5083291200 car_info RedCamaro waiver_signed signed);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ /Invalid \(empty\) first name/) {
  error_and_exit("Bad first name not detected.\n$output");
}

success();

###########
# Test 4 - Fail non-member registration - no last name
# 
%TEST_INFO = qw(Testname TestNonMemberNoLastName);
%GET = qw(key UnitTestPlayground competitor_first_name Karen club_name NEOC si_stick 124 email dasha@umassamherst.edu cell_number 5083291200 car_info RedCamaro waiver_signed signed);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ /Invalid \(empty\) last name/) {
  error_and_exit("Bad first name not detected.\n$output");
}

success();


###########
# Test 5 - Success non-member registration
# Test with less than all information provided
%TEST_INFO = qw(Testname TestNonMemberSomeInfoProvided);
%GET = qw(key UnitTestPlayground competitor_first_name Freddie competitor_last_name Mercury club_name DVOC email mark@mkoconnell.com cell_number 5086148225 car_info ChevyBoltEV3470 waiver_signed signed);
$GET{"si_stick"} = "";
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Freddie last_name Mercury club_name DVOC email_address mark@mkoconnell.com
                        cell_phone 5086148225 car_info ChevyBoltEV3470 is_member no);
$expected_hash{"si_stick"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();

###########
# Test 6 - Success non-member registration
# Test with very little information provided
%TEST_INFO = qw(Testname TestNonMemberMinimalInfoProvided);
%GET = qw(key UnitTestPlayground competitor_first_name Queen competitor_last_name Elizabeth waiver_signed signed);
$GET{"si_stick"} = "";
$GET{"club_name"} = "";
$GET{"email"} = "";
$GET{"cell_number"} = "";
$GET{"car_info"} = "";
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ m#URL=../OMeetRegistration/register.php\?.*registration_info=([^"]+)"#) {
  error_and_exit("Redirect URL not found.\n$output");
}
my($info_hash_ref) = get_specified_info($1);
my(%expected_hash) = qw(first_name Queen last_name Elizabeth is_member no);
$expected_hash{"si_stick"} = "";
$expected_hash{"club_name"} = "";
$expected_hash{"email_address"} = "";
$expected_hash{"cell_phone"} = "";
$expected_hash{"car_info"} = "";

my($error_string) = compare_hashes(\%expected_hash, $info_hash_ref);

if ($error_string ne "") {
  error_and_exit("Registration information is wrong:\n$1\n$error_string");
}

success();


###########
# Test 7 - Fail non-member registration - no waiver signed
# 
%TEST_INFO = qw(Testname TestNonMemberNoWaiver);
%GET = qw(key UnitTestPlayground competitor_first_name Dasha competitor_last_name Wolfson club_name UNO si_stick 1024 email dasha@umassamherst.edu cell_number 5083291200 car_info RedCamaro);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/non_member.php";
$output = qx($cmd);

if ($output !~ /The waiver must be acknowledged/) {
  error_and_exit("Did not detect no waiver signed.\n$output");
}

success();

#################
# End the test successfully
qx(rm artificial_input);
remove_member_files(get_base_path("UnitTestPlayground"));
my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
