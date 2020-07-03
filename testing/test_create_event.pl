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
%POST = qw(submit true key UnitTestPlayground event_name MOCTest7 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:1h30m:10,204:10,208:20 204:10 208:20 204:10);

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




############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
