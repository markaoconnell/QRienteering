#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);


###########
# Test 1 - Create a normal new event
%TEST_INFO = qw(Testname TestCreateEvent1);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true event_name MOCTest1 course_description White,201,202--newline--Yellow,202,204--newline--Orange,204,208);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();

###########
# Test 2 - Try some comments
%TEST_INFO = qw(Testname TestCreateEvent2);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true event_name MOCTest2 course_description --TestAComment--newline--White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--Orange,204,208);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();


###########
# Test 3 - Prefix the course name with l:
%TEST_INFO = qw(Testname TestCreateEvent3);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true event_name MOCTest3 course_description --TestAComment--newline--l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--l:Orange,204,208);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();



###########
# Test 4 - Test creating a scoreO course
%TEST_INFO = qw(Testname TestCreateEvent4);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true event_name MOCTest4 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:3600:10,204:10,208:20);

create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();




###########
# Test 5 - Test passing bad parameters for the score course (too few params)
%TEST_INFO = qw(Testname TestFailCreateEvent1);
%GET = ();
%COOKIE = ();  # empty hash
%POST = qw(submit true event_name MOCTest5 course_description l:White,201,202--newline----TryAnotherComment--newline--Yellow,202,204--newline--s:Orange:3600,204:10,208:20);

create_event_fail("looks wrong: Unexpected number entries:", \%GET, \%COOKIE, \%POST, \%TEST_INFO);

success();




############
# Cleanup

qx(rm -rf MOCTest1Event MOCTest2Event MOCTest3Event MOCTest4Event);
qx(rm artificial_input);
