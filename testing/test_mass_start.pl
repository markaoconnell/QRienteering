#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($output);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);

sub run_mass_start {
  my($cmd) = "php ../mass_start.php";
  my($output);
  $output = qx($cmd);

  return($output);
}


############
# Test 1 - run with no events
#
%TEST_INFO = qw(Testname TestMassStartNoEvents);
hashes_to_artificial_file();
$output = run_mass_start();
if ($output !~ /No available events/) {
  error_and_exit("Unexpected output - events found when there should be none.\n$output");
}

success();


############
# Create one event
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
set_no_redirects_for_event("UnitTestingEvent");
%POST = ();


###########
# Test 2 - Multiple events - should see event list
#
%TEST_INFO = qw(Testname TestMassStartMultipleEvents);
%GET = ();

mkdir("./MOCEvent");
mkdir("./TheThirdEvent");

hashes_to_artificial_file();
$output = run_mass_start();

if (($output !~ /MOCEvent/) || ($output !~ /TheThirdEvent/) || ($output !~ /UnitTestingEvent/)) {
  error_and_exit("Expected to see event list but did not.\n$output");
}

success();



###########
# Test 3 - Multiple events - one passed via get
# Should see course list
%TEST_INFO = qw(Testname TestMassStartSelectOneEvent);
%GET = qw(event UnitTestingEvent);

hashes_to_artificial_file();
$output = run_mass_start();

if (($output =~ /MOCEvent/) || ($output =~ /TheThirdEvent/) || ($output =~ /UnitTestingEent/)) {
  error_and_exit("Expected to see course list but found events.\n$output");
}

if (($output !~ /00-White/) || ($output !~ /01-Yellow/) || ($output !~ /02-ScoreO/)) {
  error_and_exit("Expected to see course list but did not.\n$output");
}

success();


###########
# Test 4 - One event
# Should see course list
%TEST_INFO = qw(Testname TestMassStartOnlyOneEvent);
%GET = qw();

rmdir("./MOCEvent");
rmdir("./TheThirdEvent");

hashes_to_artificial_file();
$output = run_mass_start();

if (($output =~ /MOCEvent/) || ($output =~ /TheThirdEvent/) || ($output =~ /UnitTestingEent/)) {
  error_and_exit("Expected to see course list but found events.\n$output");
}

if (($output !~ /00-White/) || ($output !~ /01-Yellow/) || ($output !~ /02-ScoreO/)) {
  error_and_exit("Expected to see course list but did not.\n$output");
}

success();



###########
# Test 5 - One event
# Some courses selected
%TEST_INFO = qw(Testname TestMassStartTwoCourses);
%GET = qw(mass_start_00-White 00-White mass_start_02-ScoreO 02-ScoreO);


hashes_to_artificial_file();
$output = run_mass_start();

if (($output =~ /MOCEvent/) || ($output =~ /TheThirdEvent/) || ($output =~ /UnitTestingEent/)) {
  error_and_exit("Expected to see confirmed course list but found events.\n$output");
}

if ($output =~ /01-Yellow/) {
  error_and_exit("Expected to see confirmed course list but found Yellow instead.\n$output");
}

if ($output !~ /00-White,02-ScoreO/) {
  error_and_exit("Expected to see confirmed course list but did not.\n$output");
}

success();


###########
# Test 6 - One event
# One course selected
%TEST_INFO = qw(Testname TestMassStartOneCourse);
%GET = qw(mass_start_02-ScoreO 02-ScoreO);


hashes_to_artificial_file();
$output = run_mass_start();

if (($output =~ /MOCEvent/) || ($output =~ /TheThirdEvent/) || ($output =~ /UnitTestingEent/)) {
  error_and_exit("Expected to see confirmed course list but found events.\n$output");
}

if (($output =~ /01-Yellow/) || ($output =~ /00-White/)) {
  error_and_exit("Expected to see confirmed course list but found White or Yellow instead.\n$output");
}

if ($output !~ /02-ScoreO/) {
  error_and_exit("Expected to see confirmed course list but did not.\n$output");
}

success();






############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
