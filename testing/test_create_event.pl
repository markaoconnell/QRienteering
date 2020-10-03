#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);

create_key_file();
set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);


###########
# Test 1 - Create a normal new event
%TEST_INFO = qw(Testname TestCreateEvent1);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest1 course_description White,201,202--newline--Yellow,202,204--newline--Orange,204,208);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event1_id) = $TEST_INFO{"event_id"};

success();


###########
# Test 1.1 - Clone the new event
%TEST_INFO = qw(Testname TestCloneEvent1);
%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground);
$GET{"clone_event"} = $event1_id;

my($output);
my($cmd) = "php ../OMeetMgmt/create_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if (($output !~ /l:White,201,202/) || ($output !~ /l:Yellow,202,204/) || ($output !~ /l:Orange,204,208/)) {
  error_and_exit("Course description lines not found.\n$output");
}

if (($output !~ m#form action=./create_event.php#) || ($output !~ /Copy of MOCTest1/)) {
  error_and_exit("Did not find form to create event Copy of MOCTest1.\n$output");
}

success();


###########
# Test 2 - Try some comments
%TEST_INFO = qw(Testname TestCreateEvent2);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest2 course_description --TestAComment--newline--White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--Orange,204,208);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event2_id) = $TEST_INFO{"event_id"};

success();


###########
# Test 3 - Prefix the course name with l:
%TEST_INFO = qw(Testname TestCreateEvent3);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest3 course_description --TestAComment--newline--l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--l:Orange,204,208);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event3_id) = $TEST_INFO{"event_id"};

success();



###########
# Test 4 - Test creating a scoreO course
%TEST_INFO = qw(Testname TestCreateEvent4);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest4 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:3600:10,204:10,208:20);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event4_id) = $TEST_INFO{"event_id"};

success();


###########
# Test 4.1 - Clone the scoreO event
%TEST_INFO = qw(Testname TestCloneScoreOEvent4);
%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground);
$GET{"clone_event"} = $event4_id;

my($output);
my($cmd) = "php ../OMeetMgmt/create_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if (($output !~ /l:White,201,202/) || ($output !~ /l:Yellow,202,204/)) {
  error_and_exit("Course description lines not found for non-score events.\n$output");
}

if ($output !~ /s:Orange:3600s:10,204:10,208:20/) {
  error_and_exit("Course description lines not found for ScoreO event.\n$output");
}

if (($output !~ m#form action=./create_event.php#) || ($output !~ /Copy of MOCTest4/)) {
  error_and_exit("Did not find form to create event Copy of MOCTest1.\n$output");
}

success();

###########
# Test 5 - Test creating a scoreO course
# Format the time differently
%TEST_INFO = qw(Testname TestCreateEventScoreFormattedTime);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest5 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:1h:10,204:10,208:20);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event5_id) = $TEST_INFO{"event_id"};

success();


###########
# Test 6 - Test creating a scoreO course
# Format the time in minutes
%TEST_INFO = qw(Testname TestCreateEventScoreFormattedTimeMinutes);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest6 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:60m:10,204:10,208:20);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event6_id) = $TEST_INFO{"event_id"};

success();


###########
# Test 7 - Test creating a scoreO course
# Format the time in minutes
%TEST_INFO = qw(Testname TestCreateEventScoreODuplicateControl);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest7 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:1h30m:10,204:10,208:20);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event7_id) = $TEST_INFO{"event_id"};


success();




###########
# Test 8 - Test passing bad parameters for the score course (too few params)
%TEST_INFO = qw(Testname TestFailCreateEvent1);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest8 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:3600,204:10,208:20);

create_event_fail("looks wrong: Unexpected number entries:", \%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();



###########
# Test 9 - Test passing bad parameters for the score course (time poorly formatted)
%TEST_INFO = qw(Testname TestFailCreateEvent2);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest9 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:1h5d:1,204:10,208:20);

create_event_fail("not in format XXhYYmZZs", \%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();


###########
# Test 10 - Test passing bad parameters for the score course (time poorly formatted)
%TEST_INFO = qw(Testname TestFailCreateEvent3);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest10 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:1h5s:1,204:10,208:20,208:30);

create_event_fail("duplicated with different point values", \%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();



###########
# Test 11 - Attempt to clone a bad event
%TEST_INFO = qw(Testname TestCloneBadEvent);
%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground);
$GET{"clone_event"} = "event-not_there";

my($output);
my($cmd) = "php ../OMeetMgmt/create_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if ($output !~ /Event not found/) {
  error_and_exit("Did not find error message about bad event.\n$output");
}

if ($output =~ m#form action=./create_event.php#) {
  error_and_exit("Found create_event form, should not be there.\n$output");
}

success();


###########
# Test 12 - Test creating an event and then adding a course 
%TEST_INFO = qw(Testname TestCreateEventThenAddACourse);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true key UnitTestPlayground event_name MOCTest12 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event12_id) = $TEST_INFO{"event_id"};

%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground submit true course_description Orange,203,206);
$GET{"event"} = $event12_id;

$cmd = "php ../OMeetMgmt/add_course_to_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if ($output !~ /Course Orange successfully added to MOCTest12/) {
  error_and_exit("Did not see message about adding Orange course\n$output");
}

if (($output !~ /<li>White/) || ($output !~ /<li>Yellow/) || ($output !~ /<li>Orange/)) {
  error_and_exit("Did not see full course list\n$output");
}

%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground);
$GET{"clone_event"} = $event12_id;

my($output);
my($cmd) = "php ../OMeetMgmt/create_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if (($output !~ /l:White,201,202/) || ($output !~ /l:Yellow,202,204/)) {
  error_and_exit("Course description lines not found for non-score events.\n$output");
}

if ($output !~ /l:Orange,203,206/) {
  error_and_exit("Course description lines not found for Orange event.\n$output");
}

if (($output !~ m#form action=./create_event.php#) || ($output !~ /Copy of MOCTest12/)) {
  error_and_exit("Did not find form to create event Copy of MOCTest1.\n$output");
}

success();


###########
# Test 13 - Test adding another course 
%TEST_INFO = qw(Testname TestAddASecondCourse);
%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground submit true course_description s:GetEmAll:0:0,203:1,206:1,202:1,201:1,204:1,205:1);
$GET{"event"} = $event12_id;

$cmd = "php ../OMeetMgmt/add_course_to_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if ($output !~ /Course GetEmAll successfully added to MOCTest12/) {
  error_and_exit("Did not see message about adding GetEmAll course\n$output");
}

if (($output !~ /<li>White/) || ($output !~ /<li>Yellow/) || ($output !~ /<li>Orange/) || ($output !~ /<li>GetEmAll/)) {
  error_and_exit("Did not see full course list\n$output");
}

%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground);
$GET{"clone_event"} = $event12_id;

my($output);
my($cmd) = "php ../OMeetMgmt/create_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if (($output !~ /l:White,201,202/) || ($output !~ /l:Yellow,202,204/)) {
  error_and_exit("Course description lines not found for non-score events.\n$output");
}

if ($output !~ /l:Orange,203,206/) {
  error_and_exit("Course description lines not found for Orange event.\n$output");
}

if ($output !~ /s:GetEmAll:0s:0,203:1,206:1,202:1,201:1,204:1,205:1/) {
  error_and_exit("Course description lines not found for GetEmAll event.\n$output");
}

if (($output !~ m#form action=./create_event.php#) || ($output !~ /Copy of MOCTest12/)) {
  error_and_exit("Did not find form to create event Copy of MOCTest1.\n$output");
}

success();


###########
# Test 14 - Call add a course with a bad event
%TEST_INFO = qw(Testname TestAddACourseBadEvent);
%POST = ();
%COOKIE = ();  # empty hash
%GET = qw(key UnitTestPlayground submit true course_description s:GetEmAll:0:0,203:1,206:1,202:1,201:1,204:1,205:1);
$GET{"event"} = "event-not_there";

$cmd = "php ../OMeetMgmt/add_course_to_event.php";
hashes_to_artificial_file();
$output = qx($cmd);

if ($output !~ /No event directory found/) {
  error_and_exit("Did not see message about bad event\n$output");
}

success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
