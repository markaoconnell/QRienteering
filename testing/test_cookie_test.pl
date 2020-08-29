#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);

sub run_cookie_test {
  hashes_to_artificial_file();
  my($cmd);
  $cmd = "php ../OMeetRegistration/cookie_test.php";
  my($output);
  $output = qx($cmd);
#  print $output;

  return($output);
}

###########
# Test 1 - No cookie, nothing expected - just show the button
# 
%TEST_INFO = qw(Testname TestInitialCookieTestScreen);
%GET = ();
%COOKIE = ();  # empty hash

my($output);
$output = run_cookie_test();

if ($output !~ m#action="./cookie_test.php"#) {
  error_and_exit("Expected to see form for cookie test but did not.\n$output");
}

if ($output =~ /All seems good/) {
  error_and_exit("Should not see all seems good message.\n$output");
}

if ($output =~ /Cookie not found, please see troubleshooting guide/) {
  error_and_exit("Should not see message directing user to troubleshooting guide.\n$output");
}

success();


###########
# Test 2 - Cookie found when expecting the cookie
# 
%TEST_INFO = qw(Testname TestCookieIsPresentCorrectly);
%GET = qw(expecting_cookie 1);
%COOKIE = qw(QRienteering_test_cookie foobar);

my($output);
$output = run_cookie_test();

if ($output =~ m#action="./cookie_test.php"#) {
  error_and_exit("Did not expect to see form for cookie test.\n$output");
}

if ($output !~ /All seems good/) {
  error_and_exit("Did not see all seems good message.\n$output");
}

if ($output =~ /is this a second try for the test/) {
  error_and_exit("Should not see message about a second scan.\n$output");
}

if ($output =~ /Cookie not found, please see troubleshooting guide/) {
  error_and_exit("Should not see message directing user to troubleshooting guide.\n$output");
}


success();


###########
# Test 3 - Cookie found when not expecting one
# 
%TEST_INFO = qw(Testname TestCookieIsPresentSecondScan);
%GET = ();
%COOKIE = qw(QRienteering_test_cookie foobar);

my($output);
$output = run_cookie_test();

if ($output =~ m#action="./cookie_test.php"#) {
  error_and_exit("Did not expect to see form for cookie test.\n$output");
}

if ($output !~ /All seems good/) {
  error_and_exit("Did not see all seems good message.\n$output");
}

if ($output !~ /is this a second try for the test/) {
  error_and_exit("Should see message about a second scan.\n$output");
}

if ($output =~ /Cookie not found, please see troubleshooting guide/) {
  error_and_exit("Should not see message directing user to troubleshooting guide.\n$output");
}


success();


###########
# Test 4 - Cookie not found when expecting one
# 
%TEST_INFO = qw(Testname TestNoCookieFoundIsAProblem);
%GET = qw(expecting_cookie 1);
%COOKIE = ();

my($output);
$output = run_cookie_test();

if ($output =~ m#action="./cookie_test.php"#) {
  error_and_exit("Did not expect to see form for cookie test.\n$output");
}

if ($output =~ /All seems good/) {
  error_and_exit("Did not see all seems good message.\n$output");
}

if ($output =~ /is this a second try for the test/) {
  error_and_exit("Should see message about a second scan.\n$output");
}

if ($output !~ /Cookie not found, please see troubleshooting guide/) {
  error_and_exit("Should see message directing user to troubleshooting guide.\n$output");
}

success();


###########
# Test 5 - Wrong cookie found when expecting one
# 
%TEST_INFO = qw(Testname TestNoCookieFoundIsAProblem);
%GET = qw(expecting_cookie 1);
%COOKIE = qw(this_is_not_the_cookie_youre_looking_for 1);

my($output);
$output = run_cookie_test();

if ($output =~ m#action="./cookie_test.php"#) {
  error_and_exit("Did not expect to see form for cookie test.\n$output");
}

if ($output =~ /All seems good/) {
  error_and_exit("Did not see all seems good message.\n$output");
}

if ($output =~ /is this a second try for the test/) {
  error_and_exit("Should see message about a second scan.\n$output");
}

if ($output !~ /Cookie not found, please see troubleshooting guide/) {
  error_and_exit("Should see message directing user to troubleshooting guide.\n$output");
}

success();



###########
# Test 6 - Extra cookies don't matter
# 
%TEST_INFO = qw(Testname TestManyCookiesPresentCorrectly);
%GET = qw(expecting_cookie 1);
%COOKIE = qw(QRienteering_test_cookie foobar extra_cookie 1 course 02-ScoreO);

my($output);
$output = run_cookie_test();

if ($output =~ m#action="./cookie_test.php"#) {
  error_and_exit("Did not expect to see form for cookie test.\n$output");
}

if ($output !~ /All seems good/) {
  error_and_exit("Did not see all seems good message.\n$output");
}

if ($output =~ /is this a second try for the test/) {
  error_and_exit("Should not see message about a second scan.\n$output");
}

if ($output =~ /Cookie not found, please see troubleshooting guide/) {
  error_and_exit("Should not see message directing user to troubleshooting guide.\n$output");
}


success();




############
# Cleanup

qx(rm artificial_input);
