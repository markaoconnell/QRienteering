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




############
# Cleanup

qx(rm -rf MOCTest1Event);
qx(rm artificial_input);
