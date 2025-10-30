#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST, %REGISTRATION_INFO);
my($COMPETITOR_1) = "Mark_OConnell--space-----space--2";
my($COMPETITOR_2) = "Karen_Yeowell--space--+2";   # +3 HTML encoded
my($COMPETITOR_3) = "LydBid--space--(2)";       # (2) HTML encoded
my($COMPETITOR_4) = "JohnnyJohnCon--space--3";
my($COMPETITOR_5) = "LinaNowak";
my($COMPETITOR_6) = "RoxyAndTheGemstoneKitties";
my($COMPETITOR_1_RE) = "Mark_OConnell - 2";
my($COMPETITOR_2_RE) = "Karen_Yeowell \\+2";   # +3 HTML encoded
my($COMPETITOR_3_RE) = "LydBid \\(2\\)";       # (2) HTML encoded
my($COMPETITOR_4_RE) = "JohnnyJohnCon 3";
my($COMPETITOR_5_RE) = "LinaNowak";
my($COMPETITOR_6_RE) = "RoxyAndTheGemstoneKitties";
my($competitor_1_id, $competitor_2_id, $competitor_3_id, $competitor_4_id, $competitor_5_id, $competitor_6_id);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
create_key_file();
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");

# Set up the NRE classification tables
set_using_nre_classes("UnitTestPlayground", $event_id);
set_nre_classes("UnitTestPlayground");


sub register_one_entrant {
  %GET = qw(key UnitTestPlayground);
  $GET{"course"} = $_[1];
  $GET{"competitor_name"} = $_[0];
  $GET{"event"} = $event_id;
  my($birth_year, $gender);
  $birth_year = $_[2];
  $gender = $_[3];
  my($expecting_class_entry) = $_[4];
  %COOKIE = ();  # empty hash
  
  #print "Register $_[0] on $_[1]\n";

  %REGISTRATION_INFO = qw(club_name NEOC email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ChevyBoltEV3470MA is_member yes);
  $REGISTRATION_INFO{"first_name"} = $GET{"competitor_name"};
  $REGISTRATION_INFO{"first_name"} =~ s/--space--/ /g;
  $REGISTRATION_INFO{"last_name"} = "";
  $REGISTRATION_INFO{"classification_info"} = values_to_classification_info($birth_year, $gender, "");

  register_member_successfully_for_nre(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO, $expecting_class_entry);
  return($TEST_INFO{"competitor_id"});
}

sub check_results {
  my($expected_table_rows) = @_;

  %GET = qw(key UnitTestPlayground);
  $GET{"event"} = $event_id;
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../OMeet/view_results.php";
  my($output);
  $output = qx($cmd);

  my($actual_table_rows);
  $actual_table_rows = () = $output =~ /(<tr><td>)/g;

  if ($actual_table_rows != $expected_table_rows) {
    error_and_exit("Found $actual_table_rows instead of $expected_table_rows in results output.\n$output");
  }

  if ($output !~ /\#\#\#\#,CourseList,00-White,01-Yellow,02-ScoreO,03-Butterfly,04-GetEmAll,05-Green,06-Red,07-Brown\n/) {
    error_and_exit("Did not find expected course list in results output.\n$output");
  }

  return ($output);
}

sub check_on_course {
  my($expected_table_rows) = @_;

  %GET = qw(key UnitTestPlayground);
  $GET{"event"} = $event_id;
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../OMeet/on_course.php";
  my($output);
  $output = qx($cmd);

  my($actual_table_rows);
  $actual_table_rows = () = $output =~ /(<tr><td>)/g;

  if ($actual_table_rows != $expected_table_rows) {
    error_and_exit("Found $actual_table_rows instead of $expected_table_rows in on_course output.\n$output");
  }
  
  return ($output);
}

sub check_competitor_on_course {
  my($competitor_name, $competitor_id) = @_;

  %GET = qw(key UnitTestPlayground include_competitor_id 1);
  $GET{"event"} = $event_id;
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../OMeet/on_course.php";
  my($output);
  $output = qx($cmd);

  $competitor_name =~ s/--space--/ /g;
  my($competitor_name_for_match) = $competitor_name;
  $competitor_name_for_match =~ s/\+/\\+/g;
  $competitor_name_for_match =~ s/\(/\\(/g;
  $competitor_name_for_match =~ s/\)/\\)/g;

  if ($output !~ /$competitor_name_for_match ? \($competitor_id\)/) {
    error_and_exit("Name and id - $competitor_name and $competitor_id - not found in on_course output.\n$output");
  }
  
  return ($output);
}

sub check_splits {
  my($result_file, $expected_splits_ref) = @_;
  $TEST_INFO{"subroutine"} = "check_splits for $result_file";

  my($result_competitor);

  if ($result_file =~ /^[0-9]+,[0-9]+,([0-9a-f]+)$/) {
    $result_competitor = $1;
  }
  else {
    error_and_exit("Incorrect result file: $result_file.");
  }

  if ( -f get_base_path("UnitTestPlayground") . "/${event_id}/Competitors/$result_competitor/course") {
    my($path) = get_base_path("UnitTestPlayground") . "/${event_id}/Competitors/$result_competitor/course";
    my($course) = qx(cat $path);
    chomp($course);

    %GET = qw(key UnitTestPlayground);
    $GET{"course"} = $course;
    $GET{"entry"} = $result_file;
    $GET{"event"} = $event_id;
    %COOKIE = ();
    hashes_to_artificial_file();

    my($cmd) = "php ../OMeet/show_splits.php";
    my($output);
    $output = qx($cmd);

    my($actual_split_rows);
    $actual_split_rows = () = $output =~ /(<td>\d\d:\d\d:\d\d)/g;

    if ($actual_split_rows != ($expected_splits_ref->{$result_competitor} + 2)) {
      error_and_exit("Wrong rows in splits file for $result_competitor, $actual_split_rows vs expected " .
                          ($expected_splits_ref->{$result_competitor} + 2) . "\n$output");
    }
  }
  else {
    error_and_exit("No course file found for $result_competitor.");
  }
}

###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname NreRegister6AndCheckOnCourse);

$competitor_1_id = register_one_entrant($COMPETITOR_1, "01-Yellow", "2001", "f", 1);
$competitor_2_id = register_one_entrant($COMPETITOR_2, "01-Yellow", "2010", "f", 1);
$competitor_3_id = register_one_entrant($COMPETITOR_3, "00-White", "2010", "m", 1);
$competitor_4_id = register_one_entrant($COMPETITOR_4, "00-White", "1998", "m", 1);
$competitor_5_id = register_one_entrant($COMPETITOR_5, "02-ScoreO", "2000", "f", 0);
$competitor_6_id = register_one_entrant($COMPETITOR_6, "02-ScoreO", "2005", "m", 0);

check_results(0);
check_on_course(6);
check_competitor_on_course($COMPETITOR_1, $competitor_1_id);

success();



###########
# Test 2 - Competitor 1 starts the course and reaches two controls
# Competitor 2 starts the course
# Competitor 5 start the course and finds a control
# 3 people on course
# 0 results

# Competitor 1 starts and gets two controls
%TEST_INFO = qw(Testname NreTestThreeStartersAtEvent);
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"event"} = $event_id;
$COOKIE{"competitor_id"} = $competitor_1_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "202";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "204";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);



# Competitor 2 starts
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);


# Competitor 5 starts and gets a control
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_5_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "301";
reach_score_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "304";
reach_score_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);


check_results(0);
my($output) = check_on_course(6);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2_RE ?</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output !~ m#$COMPETITOR_1_RE ?</td><td>[0-9:]+</td><td>2</td>#) ||
    ($no_newline_output !~ m#$COMPETITOR_5_RE ?</td><td>[0-9:]+</td><td>304</td>#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}

check_competitor_on_course($COMPETITOR_5, $competitor_5_id);

success();



###########
# Test 3 - Competitor_1 finishes
# Competitor 3 starts
# Competitor 2 finds 3 controls
# Competitor 3 finds 1 control
# Competitor 4 starts
# Competitor 5 finds another control
# Competitor 6 starts
%TEST_INFO = qw(Testname NreOneFinisherThreeMoreStarters);

# Competitor 1 finds two more controls
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "206";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "208";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 3 starts
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);


# Competitor 6 starts
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_6_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);



# Competitor 2 finds 2 controls
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$GET{"control"} = "202";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "204";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 3 finds a control
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "201";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 5 finds a control
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_5_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "305";
reach_score_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 2 finds the next control
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$GET{"control"} = "206";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 6 finds a control
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_6_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$GET{"control"} = "303";
reach_score_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 4 starts
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_4_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

# Competitor 1 finds the final control and finishes
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$GET{"control"} = "210";
reach_control_successfully(4, \%GET, \%COOKIE, \%TEST_INFO);
finish_successfully(\%GET, \%COOKIE, \%TEST_INFO);

my($output) = check_results(1);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if ($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1_RE ?</a></td><td>[0-9 smh:]+</td>#) {
  error_and_exit("View result output for $COMPETITOR_1 wrong.\n$output");
}

my($output) = check_on_course(5);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2_RE ?</td><td>[0-9:]+</td><td>3</td>#) || ($no_newline_output !~ m#$COMPETITOR_3_RE ?</td><td>[0-9:]+</td><td>1</td>#) ||
    ($no_newline_output !~ m#$COMPETITOR_5_RE ?</td><td>[0-9:]+</td><td>305</td>#) || ($no_newline_output !~ m#$COMPETITOR_6_RE ?</td><td>[0-9:]+</td><td>303</td>#) || 
    ($no_newline_output !~ m#$COMPETITOR_4_RE ?</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output =~ m#$COMPETITOR_1_RE ?#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}

check_competitor_on_course($COMPETITOR_3, $competitor_3_id);
check_competitor_on_course($COMPETITOR_6, $competitor_6_id);
check_competitor_on_course($COMPETITOR_5, $competitor_5_id);

success();



###########
# Test 4 -
# Competitor 2 DNFs
# Competitor 3 finds all controls and finishes
# Competitor 4 finds 2 controls and DNFs
# Competitor 5 finishes
# Competitor 6 finds another control and finishes
%TEST_INFO = qw(Testname NreAllFinishWithTwoDNFs);

# Competitor 3 finds more controls
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "202";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "203";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 6 finds a control
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_6_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "304";
reach_score_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);




# Competitor 2 DNFs
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);



# Competitor 3 finds all remaining controls
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "204";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "205";
reach_control_successfully(4, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 4 finds some controls then DNFs
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_4_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$GET{"control"} = "201";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "202";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);


# Competitor 5 finishes
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_5_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

finish_score_successfully(100, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 3 finishes
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

finish_successfully(\%GET, \%COOKIE, \%TEST_INFO);


# Competitor 6 finishes
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_6_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

finish_score_successfully(70, \%GET, \%COOKIE, \%TEST_INFO);


#########
# Validate results
my($output) = check_results(6);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if (($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1_RE ?</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_2_id">$COMPETITOR_2_RE ?</a></td><td>DNF</td>#) ||
    ($no_newline_output !~ m#,$competitor_3_id">$COMPETITOR_3_RE ?</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_5_id">$COMPETITOR_5_RE ?</a></td><td>[0-9 smh:]+</td><td>100</td>#) ||
    ($no_newline_output !~ m#,$competitor_6_id">$COMPETITOR_6_RE ?</a></td><td>[0-9 smh:]+</td><td>70</td>#) ||
    ($no_newline_output !~ m#,$competitor_4_id">$COMPETITOR_4_RE ?</a></td><td>DNF</td>#)) {
  error_and_exit("View result output wrong for 1 or more competitors.\n$output");
}

check_on_course(0);

success();



#################
#Test 5 - check the splits

%TEST_INFO = qw(Testname NreCheckSplitsForEntrants);

my(%expected_number_splits);
$expected_number_splits{$competitor_1_id} = 5;
$expected_number_splits{$competitor_2_id} = 3;
$expected_number_splits{$competitor_3_id} = 5;
$expected_number_splits{$competitor_4_id} = 2;
$expected_number_splits{$competitor_5_id} = 3;
$expected_number_splits{$competitor_6_id} = 2;

my($results_path) = get_base_path("UnitTestPlayground") . "/${event_id}/Results/*";
my(@results_files) = qx(ls -1 $results_path);
chomp(@results_files);

@results_files = grep(!/$event_id/, @results_files);

#print "Found files " . join("--", @results_files);
my($result_file);
for $result_file (@results_files) {
  next if ($result_file eq "");
  check_splits($result_file, \%expected_number_splits);
}

success();

#################
#Test 6 - check the stats

%TEST_INFO = qw(Testname NreCheckStatsForEvent);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;

hashes_to_artificial_file();

my($cmd) = "php ../OMeetMgmt/meet_statistics.php";
my($output);
$output = qx($cmd);

if ($output !~ /6 unique/) {
  error_and_exit("Did not find 6 unique entrants in output.\n$output");
}

if ($output !~ /15 total participants/) {
  error_and_exit("Did not find 15 total participants in output.\n$output");
}

my($actual_table_rows);
$actual_table_rows = () = $output =~ /(<tr><td>)/g;

if ($actual_table_rows != 9) {
  error_and_exit("Found $actual_table_rows instead of 9 rows in results output.\n$output");
}

success();

#################
# Test 6 - Can we view the results per class?

%TEST_INFO = qw(Testname NreViewResultsByClass);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;

%COOKIE = ();
hashes_to_artificial_file();

my($cmd) = "php ../OMeet/view_results_by_class.php";
my($output);
$output = qx($cmd);

my($actual_table_rows);
$actual_table_rows = () = $output =~ /(<tr><td>)/g;

# 4 results should appear
if ($actual_table_rows != 4) {
  error_and_exit("Found $actual_table_rows instead of 4 in results output.\n$output");
}

if ($output =~ /\#\#\#\#,CourseList,00-White,01-Yellow,02-ScoreO,03-Butterfly,04-GetEmAll,05-Green,06-Red,07-Brown\n/) {
  error_and_exit("Should not find expected course list in results by class output.\n$output");
}

success();

#################
# Test 7 - Can we get the winsplits results?

%TEST_INFO = qw(Testname GetNreWinsplitsResults);
%GET = qw(key UnitTestPlayground show_as_html 1);
$GET{"event"} = $event_id;

%COOKIE = ();
hashes_to_artificial_file();

my($cmd) = "php ../OMeetMgmt/download_results_csv.php";
my($output);
$output = qx($cmd);

my($actual_table_rows);
$actual_table_rows = () = $output =~ /(;[mf];;)/g;

if ($output !~ /White;0;;5;2;\d+:\d+:\d+;\d+:\d+:\d+;201;0:\d\d;202;0:\d\d;203;-----;204;-----;205;-----;/) {
  error_and_exit("Did not find expected splits output.\n$output");
}

# 4 results should appear
if ($actual_table_rows != 4) {
  error_and_exit("Found $actual_table_rows instead of 4 in results output.\n$output");
}

success();

#################
# Test 8 - Can we get the IOFXML results?

%TEST_INFO = qw(Testname GetNreIofXMLResults);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;

%COOKIE = ();
hashes_to_artificial_file();

my($cmd) = "php ../OMeetMgmt/download_results_iofxml.php";
my($output);
$output = qx($cmd);

my($actual_control_lines);
$actual_control_lines = () = $output =~ /(<ControlCode>)/g;

# 4 results should appear
if ($actual_control_lines != 20) {
  error_and_exit("Found $actual_control_lines instead of 20 in results output.\n$output");
}

success();

#################
# Test 9 - Can we get the OUSA results?

%TEST_INFO = qw(Testname GetNreOUSAResults);
%POST = qw(key UnitTestPlayground);
$POST{"event"} = $event_id;

%COOKIE = ();
hashes_to_artificial_file();

my($cmd) = "php ../OMeetMgmt/download_results_ousacsv.php";
my($output);
$output = qx($cmd);

my($actual_table_entries);
$actual_table_entries = () = $output =~ /(,,,)/g;

# 4 results should appear
if ($actual_table_entries != 4) {
  error_and_exit("Found $actual_table_entries instead of 4 in results output.\n$output");
}

success();



#################
# Test 10 - Mark a competitor as award ineligible and validate that it shows in the results

%TEST_INFO = qw(Testname ResultsWithIneligibleRunners);
%GET = qw(key UnitTestPlayground);
$GET{"event"} = $event_id;

%COOKIE = ();

# Mark two competitors as ineligible
open(OUTFILE, ">" . get_base_path("UnitTestPlayground") . "/${event_id}/Competitors/${competitor_1_id}/award_ineligible");
close(OUTFILE);

open(OUTFILE, ">" . get_base_path("UnitTestPlayground") . "/${event_id}/Competitors/${competitor_5_id}/award_ineligible");
close(OUTFILE);

# Validate results
my($output) = check_results(6);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if (($no_newline_output !~ m#,$competitor_1_id"><span style="color: red;">\(x\)</span> $COMPETITOR_1_RE ?</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_2_id">$COMPETITOR_2_RE ?</a></td><td>DNF</td>#) ||
    ($no_newline_output !~ m#,$competitor_3_id">$COMPETITOR_3_RE ?</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_5_id"><span style="color: red;">\(x\)</span> $COMPETITOR_5_RE ?</a></td><td>[0-9 smh:]+</td><td>100</td>#) ||
    ($no_newline_output !~ m#,$competitor_6_id">$COMPETITOR_6_RE ?</a></td><td>[0-9 smh:]+</td><td>70</td>#) ||
    ($no_newline_output !~ m#,$competitor_4_id">$COMPETITOR_4_RE ?</a></td><td>DNF</td>#)) {
  error_and_exit("View result output wrong for 1 or more competitors.\n$output");
}

success();


############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
