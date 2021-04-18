#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output, $competitor_id);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_Bad_Registration";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


###########
# Test 1 - Register with no cookie support
# Should return an error message
%TEST_INFO = qw(Testname TestStartRegistrationNoCookies);
%COOKIE = ();
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"course"} = "00-White";
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /Proper cookie support not being detected/) {
  error_and_exit("Web page output wrong, should receive error about cookie support.\n$output");
}

#print $output;

success();



###########
# Test 2 - Register with no name
# Should return an error message
%TEST_INFO = qw(Testname TestStartRegistrationNoName);
%COOKIE = ();
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"course"} = "00-White";
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /Competitor name must be specified/) {
  error_and_exit("Web page output wrong, should receive error about no competitor name.\n$output");
}

#print $output;

success();



###########
# Test 3 - Register with no course
# Should return an error message
%TEST_INFO = qw(Testname TestStartRegistrationNoCourse);
%COOKIE = ();
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$GET{"competitor_name"} = $COMPETITOR_NAME;
$COOKIE{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/register_competitor.php";
$output = qx($cmd);

if ($output !~ /Course must be specified/) {
  error_and_exit("Web page output wrong, should receive error about no course specified.\n$output");
}

#print $output;

success();





############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
