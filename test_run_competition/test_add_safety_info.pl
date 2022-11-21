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
%TEST_INFO = qw(Testname TestMemberUsingDefaultStick);
%GET = qw(key UnitTestPlayground member_id 31 using_stick yes si_stick_number 3959473 quick_lookup_member_id 11-31);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /type=hidden name="waiver_signed" value="signed"/) {
  error_and_exit("Waiver signed hidden input not found.\n$output");
}

if ($output !~ /type=hidden name="member_id" value="31"/) {
  error_and_exit("Hidden input member_id not found.\n$output");
}

if ($output !~ /type=hidden name="si_stick" value="3959473"/) {
  error_and_exit("Hidden input si_stick not found.\n$output");
}

if ($output !~ /input type="text" size=50 name="email" value="karen\@mkoconnell.com"/) {
  error_and_exit("Presupplied email address not found.\n$output");
}

if ($output !~ /input type="text" size=50 name="cell_number" value="5083959473"/) {
  error_and_exit("Presupplied email address not found.\n$output");
}

success();


###########
# Test 2 - Success member registration
# Try using a wrong optimized lookup key - should still retrieve the email
%TEST_INFO = qw(Testname TestMemberUsingDifferentStick);
%GET = qw(key UnitTestPlayground member_id 31 using_stick yes si_stick_number 141421 quick_lookup_member_id 12-31);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /type=hidden name="waiver_signed" value="signed"/) {
  error_and_exit("Waiver signed hidden input not found.\n$output");
}

if ($output !~ /type=hidden name="member_id" value="31"/) {
  error_and_exit("Hidden input member_id not found.\n$output");
}

if ($output !~ /type=hidden name="si_stick" value="141421"/) {
  error_and_exit("Hidden input si_stick not found.\n$output");
}

if ($output !~ /input type="text" size=50 name="email" value="karen\@mkoconnell.com"/) {
  error_and_exit("Presupplied email address not found.\n$output");
}

if ($output !~ /input type="text" size=50 name="cell_number" value="5083959473"/) {
  error_and_exit("Presupplied email address not found.\n$output");
}


success();

###########
# Test 3 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberWithStickSpecifiedButSaysQRienteering);
%GET = qw(key UnitTestPlayground member_id 31 using_stick no si_stick_number 141421 quick_lookup_member_id 2-31);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /type=hidden name="waiver_signed" value="signed"/) {
  error_and_exit("Waiver signed hidden input not found.\n$output");
}

if ($output !~ /type=hidden name="member_id" value="31"/) {
  error_and_exit("Hidden input member_id not found.\n$output");
}

if ($output !~ /Overriding and using SI unit orienteering/)  {
  error_and_exit("Override message should be present but was not.\n$output");
}

if ($output !~ /type=hidden name="si_stick" value="141421"/) {
  error_and_exit("Hidden input si_stick should have the value 141421 but did not.\n$output");
}

if ($output !~ /input type="text" size=50 name="email" value="karen\@mkoconnell.com"/) {
  error_and_exit("Presupplied email address not found.\n$output");
}

if ($output !~ /input type="text" size=50 name="cell_number" value="5083959473"/) {
  error_and_exit("Presupplied email address not found.\n$output");
}


success();

###########
# Test 3a - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberNotUsingStick);
%GET = qw(key UnitTestPlayground member_id 31 using_stick no);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /type=hidden name="waiver_signed" value="signed"/) {
  error_and_exit("Waiver signed hidden input not found.\n$output");
}

if ($output !~ /type=hidden name="member_id" value="31"/) {
  error_and_exit("Hidden input member_id not found.\n$output");
}

if ($output =~ /Overriding and using SI unit orienteering/)  {
  error_and_exit("Override message should not be present but was.\n$output");
}

if ($output !~ /type=hidden name="si_stick" value=""/) {
  error_and_exit("Hidden input si_stick should have a value.\n$output");
}

if ($output !~ /input type="text" size=50 name="email"  >/) {
  error_and_exit("Presupplied email address found.\n$output");
}

success();

###########
# Test 4 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberNotUsingStick);
%GET = qw(key UnitTestPlayground member_id 41 using_stick no);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);


if ($output !~ /type=hidden name="waiver_signed" value="signed"/) {
  error_and_exit("Waiver signed hidden input not found.\n$output");
}

if ($output !~ /type=hidden name="member_id" value="41"/) {
  error_and_exit("Hidden input member_id not found.\n$output");
}

if ($output !~ /type=hidden name="si_stick" value=""/) {
  error_and_exit("Empty si_stick entry not found.\n$output");
}

if ($output !~ /input type="text" size=50 name="email"  >/) {
  error_and_exit("Presupplied email address found.\n$output");
}

success();



###########
# Test 5 - Success member registration
# 
%TEST_INFO = qw(Testname TestMemberUsingStickButNoDefault);
%GET = qw(key UnitTestPlayground member_id 171 using_stick yes si_stick_number 314159);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);


if ($output !~ /type=hidden name="waiver_signed" value="signed"/) {
  error_and_exit("Waiver signed hidden input not found.\n$output");
}

if ($output !~ /type=hidden name="member_id" value="171"/) {
  error_and_exit("Hidden input member_id not found.\n$output");
}

if ($output !~ /type=hidden name="si_stick" value="314159"/) {
  error_and_exit("Empty si_stick entry not found.\n$output");
}

if ($output !~ /input type="text" size=50 name="email"  >/) {
  error_and_exit("Presupplied email address found.\n$output");
}


success();



###########
# Test 6 - Failed member registration - bad stick id specified
# 
%TEST_INFO = qw(Testname TestMemberUsingBadStickNumber);
%GET = qw(key UnitTestPlayground member_id 41 using_stick yes si_stick_number 14xx21);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /Invalid si unit id "14xx21", only numbers allowed.  Please go back and re-enter./) {
  error_and_exit("Bad stick error message not found.\n$output");
}

success();


###########
# Test 7 - Failed member registration - bad stick id specified
# 
%TEST_INFO = qw(Testname TestMemberUsingEmptyStickNumber);
%GET = qw(key UnitTestPlayground member_id 41 using_stick yes);
$GET{"si_stick_number"} = "";
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /Yes specified for SI unit usage but no SI unit number found/) {
  error_and_exit("Bad stick error message not found.\n$output");
}

success();


###########
# Test 8 - Failed member registration - no member id specified
# 
%TEST_INFO = qw(Testname TestNoMember);
%GET = qw(key UnitTestPlayground using_stick yes si_stick_number 14xx21);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /please go back and enter a valid first name/) {
  error_and_exit("Bad error message: \"No member id\" not found.\n$output");
}

success();

###########
# Test 9 - Failed member registration - using stick must be yes or no
# 
%TEST_INFO = qw(Testname TestUsingStickWrong);
%GET = qw(key UnitTestPlayground member_id 314 using_stick maybe si_stick_number 14xx21);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /Invalid value \"maybe\" for SI unit usage/) {
  error_and_exit("Bad stick error message not found.\n$output");
}

success();

###########
# Test 10 - Failed member registration - Using stick with no ID
# 
%TEST_INFO = qw(Testname TestUsingStickButNoStick);
%GET = qw(key UnitTestPlayground member_id 314 using_stick yes);
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /Yes specified for SI unit usage but no SI unit number found/) {
  error_and_exit("Bad SI unit error message not found.\n$output");
}

success();


###########
# Test 11 - Failed registration - no event specified
# 
%TEST_INFO = qw(Testname TestWithoutAnEvent);
%GET = qw(key UnitTestPlayground member_id 314 using_stick no);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetWithMemberList/add_safety_info.php";
$output = qx($cmd);

if ($output !~ /Unknown event \(empty\)/) {
  error_and_exit("No error message found that the event was not specified.\n$output");
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
