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
# Test 1 - Lookup an existing member with an SI stick
# 
%TEST_INFO = qw(Testname TestGoodMemberLookupWithSiStick);
%GET = qw(key UnitTestPlayground competitor_first_name Mark competitor_last_name OConnell);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/name_lookup.php";
$output = qx($cmd);

if ($output !~ /Welcome Mark OConnell/) {
  error_and_exit("Name of member \"Mark OConnell\" not found.\n$output");
}

if ($output !~ /value="2108369"/) {
  error_and_exit("SI stick of member \"Mark OConnell\" not found.\n$output");
}

if ($output !~ /value="yes" checked/) {
  error_and_exit("SI stick of member found but not checked as default.\n$output");
}

success();

###########
# Test 2 - Lookup an existing member without an SI stick
# 
%TEST_INFO = qw(Testname TestGoodMemberLookupNoSiStick);
%GET = qw(key UnitTestPlayground competitor_first_name Lawrence competitor_last_name Berrill);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/name_lookup.php";
$output = qx($cmd);

if ($output !~ /Welcome Larry Berrill/) {
  error_and_exit("Name of member \"Larry Berrill\" not found.\n$output");
}

if ($output !~ /How are you orienteering today/) {
  error_and_exit("SI stick of member \"Larry Berrill\" was incorrectly found.\n$output");
}

if ($output !~ /value="no" checked/) {
  error_and_exit("SI stick of member not found but default choice appears wrong.\n$output");
}

success();

###########
# Test 3 - Lookup someone not a member
# 
%TEST_INFO = qw(Testname TestNotAMember);
%GET = qw(key UnitTestPlayground competitor_first_name William competitor_last_name Blake);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/name_lookup.php";
$output = qx($cmd);

if ($output =~ /Welcome William Blake/) {
  error_and_exit("Name of member \"William Blake\" incorrectly found.\n$output");
}

if ($output !~ /No such member William Blake found/) {
  error_and_exit("No such member message for \"William Blake\" not found.\n$output");
}

success();


###########
# Test 4 - Lookup an ambiguous member
# 
%TEST_INFO = qw(Testname TestAmbiguousMember);
%GET = qw(key UnitTestPlayground competitor_first_name Is competitor_last_name Finlayson);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/name_lookup.php";
$output = qx($cmd);

if ($output !~ /Ambiguous member name/) {
  error_and_exit("Member name Is Finlayson should be ambiguous but was found.\n$output");
}

if ($output !~ /radio[a-zA-Z0-9_">= ]+Ian Finlayson/) {
  error_and_exit("Member name Ian Finlayson should have a radio button.\n$output");
}

if ($output !~ /radio[a-zA-Z0-9_">= ]+Isla Finlayson/) {
  error_and_exit("Member name Isla Finlayson should have a radio button.\n$output");
}

if ($output !~ /radio[a-zA-Z0-9_">= ]+Issi Finlayson/) {
  error_and_exit("Member name Issi Finlayson should have a radio button.\n$output");
}

my(@matches);
if (( @matches = $output =~ /(type=radio)/g ) != 3) {
  error_and_exit("Expected three radio buttons for ambiguous member.\n" . join(",", @matches) . "\n$output");
}

success();


###########
# Test 5 - Lookup given a member id (after ambiguous member)
# 
%TEST_INFO = qw(Testname TestMemberLookupWithSiStick);
%GET = qw(key UnitTestPlayground member_id 109);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/name_lookup.php";
$output = qx($cmd);

if ($output !~ /Welcome Victoria Campbell/) {
  error_and_exit("Member 109 -> Victoria Campbell was not found.\n$output");
}

if ($output !~ /value="1024"/) {
  error_and_exit("SI stick of member \"Victoria Campbell\" not found.\n$output");
}

if ($output !~ /value="yes" checked/) {
  error_and_exit("SI stick of member Victoria Campbell found but not checked as default.\n$output");
}

success();


###########
# Test 6 - Lookup given a member id (after ambiguous member)
# No SI stick
%TEST_INFO = qw(Testname TestMemberLookupNoSiStick);
%GET = qw(key UnitTestPlayground member_id 171);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/name_lookup.php";
$output = qx($cmd);

if ($output !~ /Welcome Peter Amram/) {
  error_and_exit("Member 171 -> Peter Amram was not found.\n$output");
}

if ($output !~ /How are you orienteering today/) {
  error_and_exit("SI stick of member \Peter Amram\" was incorrectly found.\n$output");
}

if ($output !~ /value="no" checked/) {
  error_and_exit("SI stick of member Peter Amram not found but default wrong.\n$output");
}

success();

###########
# Test 7 - Test bad member id
# 
%TEST_INFO = qw(Testname TestMemberLookupNoSiStick);
%GET = qw(key UnitTestPlayground member_id 141421);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/name_lookup.php";
$output = qx($cmd);

if ($output !~ /No such member id 141421 found/) {
  error_and_exit("No bad member id message found.\n$output");
}

if ($output =~ /How are you orienteering today/) {
  error_and_exit("Bad member id but found SI stick message.\n$output");
}

success();



###########
# Test 8 - Make sure that members in the cookie appear properly
# 
%TEST_INFO = qw(Testname TestSavedMemberIdLookup);
%GET = qw(key UnitTestPlayground member 1);
%COOKIE = ();  # empty hash

my($time_now) = time();
my($saved_member_id_string) = "31:${time_now},314:${time_now}";
$COOKIE{"UnitTestPlayground-member_ids"} = $saved_member_id_string;

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/competition_register.php";
$output = qx($cmd);

if ($output !~ /Register known member on this device/) {
  error_and_exit("Message about known members not found.\n$output");
}

if ($output !~ /Karen Yeowell/) {
  error_and_exit("Saved member Karen Yeowell (31) not found.\n$output");
}

if ($output !~ /Mark OConnell/) {
  error_and_exit("Saved member Mark OConnell (314) not found.\n$output");
}

success();


###########
# Test 9 - Make sure that members in the cookie appear properly
# 
%TEST_INFO = qw(Testname TestSavedMemberIdLookupSomeTimedOut);
%GET = qw(key UnitTestPlayground member 1);
%COOKIE = ();  # empty hash

my($time_now) = time();
my($time_earlier) = time() - (86400 * 30 * 5);
my($saved_member_id_string) = "31:${time_now},314:${time_earlier}";
$COOKIE{"UnitTestPlayground-member_ids"} = $saved_member_id_string;

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/competition_register.php";
$output = qx($cmd);

if ($output !~ /Register known member on this device/) {
  error_and_exit("Message about known members not found.\n$output");
}

if ($output !~ /Karen Yeowell/) {
  error_and_exit("Saved member Karen Yeowell (31) not found.\n$output");
}

if ($output =~ /Mark OConnell/) {
  error_and_exit("Saved member Mark OConnell (314) found even though timed out.\n$output");
}

success();


###########
# Test 10 - Make sure things work if all cookie members are timed out
# 
%TEST_INFO = qw(Testname TestSavedMemberIdLookupAllTimedOut);
%GET = qw(key UnitTestPlayground member 1);
%COOKIE = ();  # empty hash

my($time_now) = time();
my($time_earlier) = time() - (86400 * 30 * 5);
my($saved_member_id_string) = "31:${time_earlier},314:${time_earlier}";
$COOKIE{"UnitTestPlayground-member_ids"} = $saved_member_id_string;

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/competition_register.php";
$output = qx($cmd);

if ($output =~ /Register known member on this device/) {
  error_and_exit("Message about known members found even though all members timed out.\n$output");
}

if ($output =~ /Karen Yeowell/) {
  error_and_exit("Saved member Karen Yeowell (31) found even though timed out.\n$output");
}

if ($output =~ /Mark OConnell/) {
  error_and_exit("Saved member Mark OConnell (314) found even though timed out.\n$output");
}

success();


###########
# Test 11 - Make sure things work if there is no cookie for saved members
# 
%TEST_INFO = qw(Testname TestSavedMemberIdLookupNoCookie);
%GET = qw(key UnitTestPlayground member 1);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/competition_register.php";
$output = qx($cmd);

if ($output =~ /Register known member on this device/) {
  error_and_exit("Message about known members found even though no saved member cookie present.\n$output");
}

if ($output !~ /<u>NEOC club member registration/) {
  error_and_exit("Message about member registration not found even though member was specified.\n$output");
}

if ($output =~ /Non-NEOC club member registration/) {
  error_and_exit("Message about non-member registration found even though member was specified.\n$output");
}

success();



###########
# Test 12 - Make sure things work for non-members
# 
%TEST_INFO = qw(Testname TestRegistrationNonMember);
%GET = qw(key UnitTestPlayground);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/competition_register.php";
$output = qx($cmd);

if ($output =~ /Register known member on this device/) {
  error_and_exit("Message about known members found even though no saved member cookie present.\n$output");
}

if ($output =~ /<u>NEOC club member registration/) {
  error_and_exit("Message about member registration found even though member was not specified.\n$output");
}

if ($output !~ /Non-NEOC club member registration/) {
  error_and_exit("Message about non-member registration not found even though member was not specified.\n$output");
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
