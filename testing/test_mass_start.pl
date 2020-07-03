#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($output);

my(@COMPETITOR_BASE_NAMES) = qw(MarkOC KarenY JohnOC LydiaOC Granny Grandad LinaN Timber Androo Angie Janet James Robert Gramma);
my($competitors_used_count) = 0;

create_key_file();

sub get_next_competitor_name {
  my($name) = $COMPETITOR_BASE_NAMES[$competitors_used_count % scalar(@COMPETITOR_BASE_NAMES)] . "_${competitors_used_count}";
  $competitors_used_count++;
  return($name);
}

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);

sub run_mass_start {
  my($cmd) = "php ../OMeetMgmt/mass_start.php";
  hashes_to_artificial_file();
  my($output);
  $output = qx($cmd);

  return($output);
}

sub run_mass_start_courses {
  my($cmd) = "php ../OMeetMgmt/mass_start_courses.php";

  hashes_to_artificial_file();
  my($output);
  $output = qx($cmd);

  return($output);
}


############
# Test 1 - run with no events
#
%TEST_INFO = qw(Testname TestMassStartNoEvents);
%GET = qw(key UnitTestPlayground);
$output = run_mass_start();
if ($output !~ /No available events/) {
  error_and_exit("Unexpected output - events found when there should be none.\n$output");
}
%GET = ();

success();


###########
# Test 2 - Multiple events - should see event list
#
%TEST_INFO = qw(Testname TestMassStartMultipleEvents);

####
# Create one event
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


mkdir(get_base_path("UnitTestPlayground") . "/event-MOCEvent");
mkdir(get_base_path("UnitTestPlayground") . "/event-TheThirdEvent");

%GET = qw(key UnitTestPlayground);
$output = run_mass_start();

if (($output !~ /event-MOCEvent/) || ($output !~ /event-TheThirdEvent/) || ($output !~ /event-[0-9a-f]+/)) {
  error_and_exit("Expected to see event list but did not.\n$output");
}

success();



###########
# Test 3 - Multiple events - one passed via get
# Should see course list
%TEST_INFO = qw(Testname TestMassStartSelectOneEvent);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;

$output = run_mass_start();

if (($output =~ /event-MOCEvent/) || ($output =~ /event-TheThirdEvent/)) {
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
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;

rmdir(get_base_path("UnitTestPlayground") . "/event-MOCEvent");
rmdir(get_base_path("UnitTestPlayground") . "/event-TheThirdEvent");

$output = run_mass_start();

if (($output =~ /event-MOCEvent/) || ($output =~ /event-TheThirdEvent/)) {
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
%GET = qw(key UnitTestPlayground mass_start_00-White 00-White mass_start_02-ScoreO 02-ScoreO);


$output = run_mass_start();

if (($output =~ /event-MOCEvent/) || ($output =~ /event-TheThirdEvent/)) {
  error_and_exit("Expected to see confirmed course list but found events.\n$output");
}

if ($output =~ /01-Yellow/) {
  error_and_exit("Expected to see confirmed course list but found Yellow instead.\n$output");
}

# The courses could be listed in either order arbitrarily
if (($output !~ /00-White,02-ScoreO/) && ($output !~ /02-ScoreO,00-White/)) {
  error_and_exit("Expected to see confirmed course list but did not.\n$output");
}

success();


###########
# Test 6 - One event
# One course selected
%TEST_INFO = qw(Testname TestMassStartOneCourse);
%GET = qw(key UnitTestPlayground mass_start_02-ScoreO 02-ScoreO);


$output = run_mass_start();

if (($output =~ /event-MOCEvent/) || ($output =~ /event-TheThirdEvent/)) {
  error_and_exit("Expected to see confirmed course list but found events.\n$output");
}

if (($output =~ /01-Yellow/) || ($output =~ /00-White/)) {
  error_and_exit("Expected to see confirmed course list but found White or Yellow instead.\n$output");
}

if ($output !~ /02-ScoreO/) {
  error_and_exit("Expected to see confirmed course list but did not.\n$output");
}

success();


#############
# Test 7 - actually start some competitors!
# Register three competitors, 2 on the "correct" course, and do a start
%TEST_INFO = qw(Testname TestMassStartTwoCompetitors);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"event"} = $event_id;
my($competitor_1_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_1_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_1) = $TEST_INFO{"competitor_id"};

my($competitor_2_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_2_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_2) = $TEST_INFO{"competitor_id"};

%GET = qw(key UnitTestPlayground course 01-Yellow);
$GET{"event"} = $event_id;
my($unstarted_competitor_name) = get_next_competitor_name();
$GET{"competitor_name"} = $unstarted_competitor_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($unstarted_competitor) = $TEST_INFO{"competitor_id"};


%GET = qw(key UnitTestPlayground courses_to_start 02-ScoreO);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

if (($output =~ /Competitors started BEFORE the mass start/) || ($output =~ /Bad courses specified, no competitors started/) ||
    ($output =~ /No competitors started - second mass start/) || ($output =~ /ERROR: No event or bad event/)) {
  error_and_exit("Incorrect output after mass_start_courses, unexpected error messages.\n$output");
}

if ($output =~ /$unstarted_competitor_name/) {
  error_and_exit("Started competitor ${unstarted_competitor_name} who is on wrong course 01-Yellow.\n$output");
}

if (($output !~ /$competitor_1_name on/) || ($output !~ /$competitor_2_name on/)) {
  error_and_exit("Did not see competitor ${competitor_1_name} or ${competitor_2_name} as started.\n$output");
}

my($path) = get_base_path("UnitTestPlayground"). "/${event_id}";
if ((! -f "${path}/Competitors/${competitor_1}/controls_found/start") ||
    (! -f "${path}/Competitors/${competitor_2}/controls_found/start")) {
  error_and_exit("Did not see competitor ${competitor_1} or ${competitor_2} start file.");
}

if (-f "${path}/Competitors/${unstarted_competitor}/controls_found/start") {
  error_and_exit("Why is competitor ${unstarted_competitor} marked as started?");
}

success();



#############
# Test 8 - try a second mass start - no one more should start
#
%TEST_INFO = qw(Testname TestMassStartSecondTimeNoAction);
%GET = qw(key UnitTestPlayground courses_to_start 02-ScoreO);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

if (($output =~ /Bad courses specified, no competitors started/) || ($output =~ /ERROR: No event or bad event/)) {
  error_and_exit("Incorrect output after mass_start_courses, unexpected error messages.\n$output");
}

if (($output !~ /Competitors started BEFORE the mass start/) || ($output !~ /No competitors started - second mass start/)) {
  error_and_exit("Incorrect output after second mass_start_courses, no competitors started msg or no second mass start msg.\n$output");
}

if ($output =~ /$unstarted_competitor_name/) {
  error_and_exit("Started competitor ${unstarted_competitor_name} who is on wrong course 01-Yellow.\n$output");
}

if (($output !~ /$competitor_1_name already on/) || ($output !~ /$competitor_2_name already on/)) {
  error_and_exit("Did not see competitor ${competitor_1_name} or ${competitor_2_name} as started.\n$output");
}

success();



#############
# Test 9 - Register more on ScoreO and start them
%TEST_INFO = qw(Testname TestMassStartAdditionalCompetitors);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"event"} = $event_id;
my($competitor_3_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_3_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_3) = $TEST_INFO{"competitor_id"};

my($competitor_4_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_4_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_4) = $TEST_INFO{"competitor_id"};


%GET = qw(key UnitTestPlayground courses_to_start 02-ScoreO);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

if (($output =~ /Bad courses specified, no competitors started/) || ($output =~ /ERROR: No event or bad event/)) {
  error_and_exit("Incorrect output after mass_start_courses, unexpected error messages.\n$output");
} 

if ($output =~ /No competitors started - second mass start/) {
  error_and_exit("Incorrect output after mass_start_courses, says no competitors started.\n$output");
}

if ($output !~ /Competitors started BEFORE the mass start/) {
  error_and_exit("Incorrect output after second mass_start_courses, no competitors started msg.\n$output");
}


if ($output =~ /$unstarted_competitor_name/) {
  error_and_exit("Started competitor ${unstarted_competitor_name} who is on wrong course 01-Yellow.\n$output");
}

if (($output !~ /$competitor_1_name already on/) || ($output !~ /$competitor_2_name already on/)) {
  error_and_exit("Did not see competitor ${competitor_1_name} or ${competitor_2_name} as started.\n$output");
}

if (($output !~ /$competitor_3_name on/) || ($output !~ /$competitor_4_name on/)) {
  error_and_exit("Did not see competitor ${competitor_3_name} or ${competitor_4_name} as started.\n$output");
}

$path = get_base_path("UnitTestPlayground"). "/${event_id}";
if ((! -f "${path}/Competitors/${competitor_3}/controls_found/start") ||
    (! -f "${path}/Competitors/${competitor_4}/controls_found/start")) {
  error_and_exit("Did not see competitor ${competitor_3} or ${competitor_4} start file.");
}

if (-f "${path}/Competitors/${unstarted_competitor}/controls_found/start") {
  error_and_exit("Why is competitor ${unstarted_competitor} marked as started?");
}


#############
# Test 10 - Register more on ScoreO and White and start them both
%TEST_INFO = qw(Testname TestMassStartAdditionalCompetitorsTwoCourses);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"event"} = $event_id;
my($competitor_5_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_5_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_5) = $TEST_INFO{"competitor_id"};

my($competitor_6_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_6_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_6) = $TEST_INFO{"competitor_id"};

%GET = qw(key UnitTestPlayground course 00-White);
$GET{"event"} = $event_id;
my($competitor_7_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_7_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_7) = $TEST_INFO{"competitor_id"};

my($competitor_8_name) = get_next_competitor_name();
$GET{"competitor_name"} = $competitor_8_name;
register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
my($competitor_8) = $TEST_INFO{"competitor_id"};


%GET = qw(key UnitTestPlayground courses_to_start 02-ScoreO,00-White);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

if (($output =~ /Bad courses specified, no competitors started/) || ($output =~ /ERROR: No event or bad event/)) {
  error_and_exit("Incorrect output after mass_start_courses, unexpected error messages.\n$output");
} 

if ($output =~ /No competitors started - second mass start/) {
  error_and_exit("Incorrect output after mass_start_courses, says no competitors started.\n$output");
}

if ($output !~ /Competitors started BEFORE the mass start/) {
  error_and_exit("Incorrect output after second mass_start_courses, no competitors started msg.\n$output");
}


if ($output =~ /$unstarted_competitor_name/) {
  error_and_exit("Started competitor ${unstarted_competitor_name} who is on wrong course 01-Yellow.\n$output");
}

if (($output !~ /$competitor_1_name already on/) || ($output !~ /$competitor_2_name already on/)) {
  error_and_exit("Did not see competitor ${competitor_1_name} or ${competitor_2_name} as started.\n$output");
}

if (($output !~ /$competitor_3_name already on/) || ($output !~ /$competitor_4_name already on/)) {
  error_and_exit("Did not see competitor ${competitor_3_name} or ${competitor_4_name} as started.\n$output");
}

if (($output !~ /$competitor_5_name on/) || ($output !~ /$competitor_6_name on/)) {
  error_and_exit("Did not see competitor ${competitor_5_name} or ${competitor_6_name} as started.\n$output");
}

if (($output !~ /$competitor_7_name on/) || ($output !~ /$competitor_8_name on/)) {
  error_and_exit("Did not see competitor ${competitor_7_name} or ${competitor_8_name} as started.\n$output");
}

$path = get_base_path("UnitTestPlayground"). "/${event_id}";
if ((! -f "${path}/Competitors/${competitor_5}/controls_found/start") ||
    (! -f "${path}/Competitors/${competitor_6}/controls_found/start")) {
  error_and_exit("Did not see competitor ${competitor_5} or ${competitor_6} start file.");
}

if ((! -f "${path}/Competitors/${competitor_7}/controls_found/start") ||
    (! -f "${path}/Competitors/${competitor_8}/controls_found/start")) {
  error_and_exit("Did not see competitor ${competitor_7} or ${competitor_8} start file.");
}

if (-f "${path}/Competitors/${unstarted_competitor}/controls_found/start") {
  error_and_exit("Why is competitor ${unstarted_competitor} marked as started?");
}

success();


#############
# Test 11 - try a second mass start - no one more should start
# Add a bad course
%TEST_INFO = qw(Testname TestMassStartReplayWithBadCourse);
%GET = qw(key UnitTestPlayground courses_to_start 02-ScoreO,03-Orange);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

if ($output !~ /Bad courses specified, no competitors started/) {
  error_and_exit("Incorrect output after mass_start_courses, no bad course message for 03-Orange.\n$output");
}

if ($output =~ /ERROR: No event or bad event/) {
  error_and_exit("Incorrect output after mass_start_courses, unexpected bad event message.\n$output");
}

if (($output !~ /Competitors started BEFORE the mass start/) || ($output !~ /No competitors started - second mass start/)) {
  error_and_exit("Incorrect output after second mass_start_courses, no competitors started msg or no second mass start msg.\n$output");
}

if ($output =~ /$unstarted_competitor_name on/) {
  error_and_exit("Started competitor ${unstarted_competitor_name} who is on wrong course 01-Yellow.\n$output");
}

if (($output !~ /$competitor_1_name already on/) || ($output !~ /$competitor_2_name already on/) ||
    ($output !~ /$competitor_3_name already on/) || ($output !~ /$competitor_4_name already on/) ||
    ($output !~ /$competitor_5_name already on/) || ($output !~ /$competitor_6_name already on/)) {
  error_and_exit("Did not see competitor already on messages.\n$output");
}

if (($output =~ /$competitor_7_name/) || ($output =~ /$competitor_8_name/)) {
  error_and_exit("Seeing messages for ${competitor_7_name} or ${competitor_8_name} even though White not started.\n$output");
}

$path = get_base_path("UnitTestPlayground"). "/${event_id}";
if (-f "${path}/Competitors/${unstarted_competitor}/controls_found/start") {
  error_and_exit("Why is competitor ${unstarted_competitor} marked as started?");
}

success();


#############
# Test 12 - start a bad event
# 
%TEST_INFO = qw(Testname TestMassStartBadEvent);
%GET = qw(key UnitTestPlayground event event-BadTestingEvent courses_to_start 02-ScoreO);
$output = run_mass_start_courses();


if ($output !~ /ERROR: No event or bad event/) {
  error_and_exit("Incorrect output after mass_start_courses, unexpected bad event message.\n$output");
}

if (($output =~ /Competitors started BEFORE the mass start/) || ($output =~ /No competitors started - second mass start/)) {
  error_and_exit("Incorrect output after second mass_start_courses, no competitors started msg or no second mass start msg.\n$output");
}

if ($output =~ /Bad courses specified, no competitors started/) {
  error_and_exit("Incorrect output after mass_start_courses, no bad course message for 03-Orange.\n$output");
}

success();


############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
