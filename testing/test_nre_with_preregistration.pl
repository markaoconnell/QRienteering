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

####################################
sub create_good_preregistration_file {
  my($fake_filename) = "fake_prereg_entries";
  open(FAKE_PREREG_FILE, ">./${fake_filename}");
  print FAKE_PREREG_FILE "Mark,OConnell,Brown,10:24,2108369,5086148225,mark\@mkoconnell.com,NEOC,yes,1967,m,M Brown,y\n";
  print FAKE_PREREG_FILE "Karen,Yeowell,Green,10:44,377865,508-395-9473,karen\@mkoconnell.com,NEOC,yes,1968,f,F50+,y\n";
  print FAKE_PREREG_FILE "Lydia,OConnell,Yellow,11:14,141421,508-395-9473,lydia\@mkoconnell.com,NEOC,yes,2001,f,F Yellow,y\n";
  print FAKE_PREREG_FILE "Foreign,Person,Red,11:38,314159,+33-32-34-45-99,ferriner\@somewhere.com,SWO,yes,1990,m,M30+,n\n";
  close(FAKE_PREREG_FILE);

  return($fake_filename);
}

####################################
sub validate_award_eligibility {
  my($id) = @_;
  my($path) = get_base_path("UnitTestPlayground") . "/${event_id}/Competitors/${id}";
  return ( ! -f "${path}/award_ineligible" );
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


###########
# Test 2 - View preregistration which were uploaded
# 
%TEST_INFO = qw(Testname ViewUploadedPreregistrations);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;
%POST = ();
%COOKIE = ();

my($fake_prereg_file) = create_good_preregistration_file();

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/view_preregistrations.php";
$output = qx($cmd);

if ($output !~ /View preregistrations/) {
  error_and_exit("Web page output wrong, no view pregistrations string.\n$output");
}

if ($output !~ /Mark,OConnell,Brown,\d{2}.\d{2},2108369,/) {
  error_and_exit("Web page output wrong, Mark OConnell registration not present.\n$output");
}

if ($output !~ /Foreign,Person,Red,\d{2}.\d{2},314159,/) {
  error_and_exit("Web page output wrong, Foreign Person registration not present.\n$output");
}


#print $output;

success();



###########
# Test 3 - Auto-start the entrants, validate the files
# 
%TEST_INFO = qw(Testname AutoStartPreregisteredEntrants);
%GET = qw(key UnitTestPlayground auto_start true auto_start_show_id true);
$GET{"event"} = $event_id;
%POST = ();
%COOKIE = ();

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/view_preregistrations.php";
$output = qx($cmd);

if ($output !~ /Registered Mark OConnell--[0-9a-f]+-- on Brown/) {
  error_and_exit("Web page output wrong, Mark OConnell registration not present.\n$output");
}

if ($output !~ /Registered Foreign Person--[0-9a-f]+-- on Red/) {
  error_and_exit("Web page output wrong, Foreign Person registration not present.\n$output");
}

my($competitor_1_id) = $output =~ /Registered Mark OConnell--([0-9a-f]*)-- on Brown/;
my($competitor_2_id) = $output =~ /Registered Foreign Person--([0-9a-f]*)-- on Red/;

#print "1: ${competitor_1_id}, 2: ${competitor_2_id}\n";

if (!validate_award_eligibility($competitor_1_id)) {
  error_and_exit("Mark OConnell - ${competitor_1_id} - incorrectly marked as award ineligible.");
}

if (validate_award_eligibility($competitor_2_id)) {
  error_and_exit("Foreign Person - ${competitor_2_id} - incorrectly marked as award eligible.");
}


#print $output;

success();


###########
# Test 4 - Toggle the award eligibility
# 
%TEST_INFO = qw(Testname ToggleAwardEligibility);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;
$GET{"competitor"} = $competitor_1_id;
%POST = ();
%COOKIE = ();

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/toggle_competitor_award_eligibility.php";
$output = qx($cmd);

if ($output !~ /Competitor Mark OConnell is now <strong>ineligible/) {
  error_and_exit("Incorrect toggle output - Mark OConnell should now be ineligible.\n${output}");
}

if (validate_award_eligibility($competitor_1_id)) {
  error_and_exit("Mark OConnell - ${competitor_1_id} - incorrectly marked as award eligible.");
}

if (validate_award_eligibility($competitor_2_id)) {
  error_and_exit("Foreign Person - ${competitor_2_id} - incorrectly marked as award eligible.");
}


#print $output;

success();



###########
# Test 5 - Toggle the award eligibility second competitor
# 
%TEST_INFO = qw(Testname ToggleAwardEligibilitySecondTry);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;
$GET{"competitor"} = $competitor_2_id;
%POST = ();
%COOKIE = ();

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/toggle_competitor_award_eligibility.php";
$output = qx($cmd);

if ($output !~ /Competitor Foreign Person is now eligible/) {
  error_and_exit("Incorrect toggle output - Foreign Person should now be eligible.\n${output}");
}

if (validate_award_eligibility($competitor_1_id)) {
  error_and_exit("Mark OConnell - ${competitor_1_id} - incorrectly marked as award eligible.");
}

if (!validate_award_eligibility($competitor_2_id)) {
  error_and_exit("Foreign Person - ${competitor_2_id} - incorrectly marked as award ineligible.");
}


#print $output;

success();



###########
# Test 6 - Toggle the award eligibility again
# 
%TEST_INFO = qw(Testname ToggleAwardEligibilityToggleBack);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;
$GET{"competitor"} = $competitor_1_id;
%POST = ();
%COOKIE = ();

hashes_to_artificial_file();
$cmd = "php ../OMeetMgmt/toggle_competitor_award_eligibility.php";
$output = qx($cmd);

if ($output !~ /Competitor Mark OConnell is now eligible/) {
  error_and_exit("Incorrect toggle output - Mark OConnell should now be eligible.\n${output}");
}

if (!validate_award_eligibility($competitor_1_id)) {
  error_and_exit("Mark OConnell - ${competitor_1_id} - incorrectly marked as award ineligible.");
}

if (!validate_award_eligibility($competitor_2_id)) {
  error_and_exit("Foreign Person - ${competitor_2_id} - incorrectly marked as award ineligible.");
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
