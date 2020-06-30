#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_DNF_Testing";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
set_no_redirects_for_event("UnitTestingEvent", "UnitTestPlayground");
%POST = ();

###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname TestSuccessRegistration);
%GET = qw(key UnitTestPlayground event UnitTestingEvent course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

success();




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestSuccessStart);
%COOKIE = qw(key UnitTestPlayground event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 3 - find a control
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFind201);
%COOKIE = qw(key UnitTestPlayground event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = qw(control 201);

reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

success();


###########
# Test 4 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishEarlyDNF);
%COOKIE = qw(key UnitTestPlayground event UnitTestingEvent course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = (); # empty hash

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);

success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
