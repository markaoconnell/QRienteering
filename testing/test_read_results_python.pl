#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST, %INI_FILE);
my($output);

my(@COMPETITOR_BASE_NAMES) = qw(MarkOC KarenY JohnOC LydiaOC Granny Grandad LinaN Timber Androo Angie Janet James Robert Gramma);
my($competitors_used_count) = 0;

create_key_file();
mkdir("./or_path_for_testing");

sub get_next_competitor_name {
  my($name) = $COMPETITOR_BASE_NAMES[$competitors_used_count % scalar(@COMPETITOR_BASE_NAMES)] . "_${competitors_used_count}";
  $competitors_used_count++;
  return($name);
}

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);

sub make_ini_file {
  open(INI_FILE_HANDLE, ">./read_results.ini");
  my($key);
  foreach $key (keys(%INI_FILE)) {
    print INI_FILE_HANDLE "$key = " . $INI_FILE{$key} . "\n";
  }
  close(INI_FILE_HANDLE);
}

sub remove_ini_file {
  unlink("./read_results.ini");
}

sub touch_file {
  my($filename) = @_;
  open(FOO, ">$filename");
  close(FOO);
}

sub run_read_results {
  my($cmd_line_params) = @_;
  make_ini_file();

  my($cmd) = "echo 1 | python ../OMeetWithMemberList/read_results.py $cmd_line_params";
  print "Running $cmd\n";
  my($output);
  $output = qx($cmd);
  #print "$output\n";

  return ($output);
}


############
# Test 1 - run with no events
#
%TEST_INFO = qw(Testname TestReadResultsNoEvents);
%INI_FILE = qw(key UnitTestPlayground testing_run 1 verbose 1 or_path ./or_path_for_testing);
$output = run_read_results("");
if ($output !~ /No currently open \(actively ongoing\) events found/) {
  error_and_exit("Unexpected output - events found when there should be none.\n$output");
}

success();


###########
# Test 2 - Multiple events - should see event list
#
%TEST_INFO = qw(Testname TestReadResultsMultipleEvents);

####
# Create one event
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


mkdir(get_base_path("UnitTestPlayground") . "/event-deadbeef");
mkdir(get_base_path("UnitTestPlayground") . "/event-abcdef");

$output = run_read_results("");

if (($output !~ /event-deadbeef/) || ($output !~ /event-abcdef/) || ($output !~ /event-[0-9a-f]+/)) {
  error_and_exit("Expected to see event list but did not.\n$output");
}

success();



###########
# Test 3 - Multiple events - one passed via command line
# Should see course list
%TEST_INFO = qw(Testname TestReadResultsEventCommandLine);

$output = run_read_results("-e $event_id");

if (($output =~ /event-deadbeef/) || ($output =~ /event-abcdef/)) {
  error_and_exit("Did not expect to event list.\n$output");
}

if ($output !~ /Awaiting new results/) {
  error_and_exit("Did not see message about awaiting results.\n$output");
}

success();


###########
# Test 4 - One event
# Should auto-choose the event if there is only 1
%TEST_INFO = qw(Testname TestReadResultsOnlyOneEvent);

rmdir(get_base_path("UnitTestPlayground") . "/event-deadbeef");
rmdir(get_base_path("UnitTestPlayground") . "/event-abcdef");

$output = run_read_results("");

if (($output =~ /event-deadbeef/) || ($output =~ /event-abcdef/)) {
  error_and_exit("Did not expect to event list.\n$output");
}

if ($output !~ /Awaiting new results/) {
  error_and_exit("Did not see message about awaiting results.\n$output");
}

if ($output !~ /Processing results for event UnitTesting/) {
  error_and_exit("Expected to see message that UnitTesting event was auto-chosen.\n$output");
}

success();



###########
# Test 5 - pass a bad event on the command line
# 
%TEST_INFO = qw(Testname TestReadResultsBadCommandLineEvent);

$output = run_read_results("-e event-not_there");

if ($output =~ /Awaiting new results/) {
  error_and_exit("Saw message about awaiting results but should see error.\n$output");
}

if ($output !~ /Event event-not_there not found, please check if event event-not_there and key UnitTestPlayground are valid/) {
  error_and_exit("Expected to see message that UnitTesting event was auto-chosen.\n$output");
}

success();


###########
# Test 6 - pass a bad url on the command line
# This won't work in testing mode - so can only test this live.  Oh well.
#%TEST_INFO = qw(Testname TestReadResultsBadCommandLineURL);
#
#$output = run_read_results("-u http://www.mkoconnell.com/OMeetNotThereEver");
#
#if ($output =~ /Awaiting new results/) {
#  error_and_exit("Saw message about awaiting results but should see error.\n$output");
#}
#
#if ($output !~ /Processing results for event UnitTesting/) {
#  error_and_exit("Expected to see message that UnitTesting event was auto-chosen.\n$output");
#}
#
#success();


###########
# Test 7 - Put extra stuff in the INI file - shouldn't matter
# should all just work
%TEST_INFO = qw(Testname TestReadResultsExtraINIFileLines);
%INI_FILE = qw(key UnitTestPlayground testing_run 1 verbose 1 or_path ./or_path_for_testing extra_field_1 dummy1 extra_field_2 dummy2);

$output = run_read_results("");

if (($output =~ /event-deadbeef/) || ($output =~ /event-abcdef/)) {
  error_and_exit("Did not expect to event list.\n$output");
}

if ($output !~ /Awaiting new results/) {
  error_and_exit("Did not see message about awaiting results.\n$output");
}

if ($output !~ /Processing results for event UnitTesting/) {
  error_and_exit("Expected to see message that UnitTesting event was auto-chosen.\n$output");
}

success();


###########
# Test 8 - Use a bad or_path
# 
%TEST_INFO = qw(Testname TestReadResultsBadOrPath);
%INI_FILE = qw(key UnitTestPlayground testing_run 1 verbose 1 or_path ./no_such_dir_is_there extra_field_1 dummy1 extra_field_2 dummy2);

$output = run_read_results("");

if (($output =~ /event-deadbeef/) || ($output =~ /event-abcdef/)) {
  error_and_exit("Did not expect to event list.\n$output");
}

if ($output =~ /Awaiting new results/) {
  error_and_exit("Incorrectly saw message about awaiting results.\n$output");
}

if ($output !~ m#ERROR: No such directory "./no_such_dir_is_there"#) {
  error_and_exit("Expected to see message that UnitTesting event was auto-chosen.\n$output");
}

success();


###########
# Test 9 - Create some fake OR results from prior events
# 
%TEST_INFO = qw(Testname TestReadResultsMultipleOREvents);
%INI_FILE = qw(key UnitTestPlayground testing_run 1 verbose 1 or_path ./or_path_for_testing);

mkdir("./or_path_for_testing/1");
touch_file("./or_path_for_testing/1/results.csv");
mkdir("./or_path_for_testing/2");
touch_file("./or_path_for_testing/2/results.csv");
mkdir("./or_path_for_testing/3");
touch_file("./or_path_for_testing/3/results.csv");

$output = run_read_results("");

if (($output =~ /event-deadbeef/) || ($output =~ /event-abcdef/)) {
  error_and_exit("Did not expect to event list.\n$output");
}

if ($output !~ /Awaiting new results/) {
  error_and_exit("Did not see message about awaiting results.\n$output");
}

if ($output !~ /Using OR event 3/) {
  error_and_exit("Did not see message that the 3rd OR event is being used.\n$output");
}

success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
$rm_cmd = "rm -rf ./or_path_for_testing";
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
remove_ini_file();
qx(rm artificial_input);
