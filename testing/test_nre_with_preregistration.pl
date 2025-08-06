#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my(%REGISTRATION_INFO);
my($cmd, $output, $competitor_id);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_NRE_Registration";
my($COMPETITOR_FIRST_NAME) = "Mark";
my($COMPETITOR_LAST_NAME) = "_OConnell_NRE_Registration";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");

# Set up the NRE classification tables
set_using_nre_classes("UnitTestPlayground", $event_id);
set_nre_classes("UnitTestPlayground");

sub create_good_preregistration_file {
  my($fake_filename) = "fake_prereg_entries";
  open(FAKE_PREREG_FILE, ">./${fake_filename}");
  print FAKE_PREREG_FILE "Mark,OConnell,Brown,2108369,5086148225,mark\@mkoconnell.com,NEOC,yes,1967,m,M Brown,y\n";
  print FAKE_PREREG_FILE "Karen,Yeowell,Green,377865,508-395-9473,karen\@mkoconnell.com,NEOC,yes,1968,f,F50+,y\n";
  print FAKE_PREREG_FILE "Lydia,OConnell,Orange,141421,508-395-9473,lydia\@mkoconnell.com,NEOC,yes,2001,f,F Orange,y\n";
  print FAKE_PREREG_FILE "Foreign,Person,Blue,314159,+33-32-34-45-99,ferriner\@somewhere.com,SWO,yes,1990,m,M-21+,n\n";
  close(FAKE_PREREG_FILE);

  return($fake_filename);
}


###########
# Test 1 - Upload preregistration file which is correct
# 
%TEST_INFO = qw(Testname UploadCorrectPregistrationFile);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;
%POST = qw(upload_preregistrants yes handle_current replace);
%COOKIE = ();

my($fake_prereg_file) = create_good_preregistration_file();

hashes_to_artificial_file();
add_uploaded_file("prereg_file", $fake_prereg_file, 20);
$cmd = "php ../OMeetMgmt/event_management.php";
$output = qx($cmd);

if ($output !~ /Successfully added 4 preregistrants/) {
  error_and_exit("Web page output wrong, count of good pregistrants not found or incorrect.\n$output");
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
qx(rm fake_prereg_entries);
