#!/usr/bin/perl

use strict;

require "../testing/testHelpers.pl";
require "./setup_member_info.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output);

create_key_file();
mkdir(get_base_path("UnitTestPlayground"));
setup_member_files(get_base_path("UnitTestPlayground"));
set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);

###########
# Test 1 - Lookup an existing member by SI stick
# 
%TEST_INFO = qw(Testname TestGoodMemberLookupBySiStick);
%GET = qw(key UnitTestPlayground si_stick 2108369);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/stick_lookup.php";
$output = qx($cmd);

if ($output !~ /Welcome Mark OConnell/) {
  error_and_exit("Name of member \"Mark OConnell\" not found.\n$output");
}

if ($output !~ /How are you orienteering today/) {
  error_and_exit("Success message not found for \"Mark OConnell\".\n$output");
}

if ($output !~ /value="2108369" readonly/) {
  error_and_exit("SI stick of member \"Mark OConnell\" not found.\n$output");
}

if ($output !~ /value="yes" checked/) {
  error_and_exit("SI stick of member found but not checked as default.\n$output");
}

success();

###########
# Test 2 - Lookup a different existing member by SI stick
# 
%TEST_INFO = qw(Testname TestSecondGoodMemberLookupBySiStick);
%GET = qw(key UnitTestPlayground si_stick 559);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/stick_lookup.php";
$output = qx($cmd);

if ($output !~ /Welcome Issi Finlayson/) {
  error_and_exit("Name of member \"Issi Finlayson\" not found.\n$output");
}

if (($output !~ /How are you orienteering today/) || ($output !~ /value="559" readonly/)) {
  error_and_exit("SI stick of member \"Issi Finlayson\" not found.\n$output");
}

if ($output !~ /value="yes" checked/) {
  error_and_exit("SI stick of member found but not checked as default.\n$output");
}

success();

###########
# Test 3 - Lookup using a non-registered SI stick
# 
%TEST_INFO = qw(Testname TestUnregisteredSiStick);
%GET = qw(key UnitTestPlayground si_stick 271828);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/stick_lookup.php";
$output = qx($cmd);

if ($output !~ /No member with SI unit "271828" found/) {
  error_and_exit("Found non-existing stick 271828.\n$output");
}

success();

###########
# Test 4 - Lookup without a si stick specified
# 
%TEST_INFO = qw(Testname TestNoSiStickSpecified);
%GET = qw(key UnitTestPlayground competitor_first_name William competitor_last_name Blake);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/stick_lookup.php";
$output = qx($cmd);

if ($output !~ /Unspecified SI unit number/) {
  error_and_exit("Failed to recognize that no SI stick was specified.\n$output");
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
