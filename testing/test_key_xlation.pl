#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($COMPETITOR_NAME) = "Mark_OConnell_Success_Testing";
my($competitor_id);
my($cmd, $output);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


# Scripts to check
# view_results
# on_course
# register (BYOM)
# manage_events
# member registration / non-member registration / preregistered checkin

###########
# Test 1 - view results with a xlation key

%TEST_INFO = qw(Testname TestViewResultsXlt);
%GET = qw(key UnitTestXlt);
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeet/view_results.php";
$output = qx($cmd);

if ($output !~ /kdkdkdkdkd/) {
  error_and_exit("Did not see view results output properly.\n${output}");
}

success();







############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
