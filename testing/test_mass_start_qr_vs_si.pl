#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($COMPETITOR_1) = "Mark_OConnell";
my($COMPETITOR_2) = "Karen_Yeowell";
my($COMPETITOR_3) = "LydBid";
my($COMPETITOR_4) = "JohnnyJohnCon";
my($COMPETITOR_5) = "LinaNowak";
my($COMPETITOR_6) = "RoxyAndTheGemstoneKitties";
my($competitor_1_id, $competitor_2_id, $competitor_3_id, $competitor_4_id, $competitor_5_id, $competitor_6_id);
my($output);

# For si stick registrants
my(%REGISTRATION_INFO);
my($SI_COMPETITOR_1_FIRST_NAME) = "Mark";
my($SI_COMPETITOR_1_LAST_NAME) = "OConnell_SiStick_Testing";
my($SI_COMPETITOR_1_NAME) = "${SI_COMPETITOR_1_FIRST_NAME} ${SI_COMPETITOR_1_LAST_NAME}";

my($SI_COMPETITOR_2_FIRST_NAME) = "Hermione";
my($SI_COMPETITOR_2_LAST_NAME) = "GrangerSiStick";
my($SI_COMPETITOR_2_NAME) = "${SI_COMPETITOR_2_FIRST_NAME} ${SI_COMPETITOR_2_LAST_NAME}";

my($SI_COMPETITOR_3_FIRST_NAME) = "Ginny";
my($SI_COMPETITOR_3_LAST_NAME) = "WeasleySiStick";
my($SI_COMPETITOR_3_NAME) = "${SI_COMPETITOR_3_FIRST_NAME} ${SI_COMPETITOR_3_LAST_NAME}";

my($si_competitor_1_id, $si_competitor_2_id, $si_competitor_3_id);

##################
sub validate_file_present {
  my($filename) = @_;

  if (! -f "$filename") {
    error_and_exit("Expected file not found: $filename");
  }
}


###############
# Main program

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");
set_email_properties("UnitTestPlayground");
set_default_timezone("UnitTestPlayground", "UTC");




###########
sub register_si_entrant {
  %GET = qw(key UnitTestPlayground);
  $GET{"event"} = $event_id;
  $GET{"course"} = $_[1];
  my($reg_info_ref) = $_[0];
  $GET{"competitor_name"} = $reg_info_ref->{"first_name"} . "--space--" . $reg_info_ref->{"last_name"};
  %COOKIE = ();  # empty hash
  
  #print "Register $_[0] on $_[1]\n";

  register_member_successfully(\%GET, \%COOKIE, $reg_info_ref, \%TEST_INFO);
  return($TEST_INFO{"competitor_id"});
}


sub run_mass_start_courses {
  my($cmd) = "php ../OMeetMgmt/mass_start_courses.php";

  hashes_to_artificial_file();
  my($output);
  $output = qx($cmd);

  return($output);
}

sub register_one_entrant {
  %GET = qw(key UnitTestPlayground);
  $GET{"course"} = $_[1];
  $GET{"competitor_name"} = $_[0];
  $GET{"event"} = $event_id;
  %COOKIE = ();  # empty hash
  
  #print "Register $_[0] on $_[1]\n";

  register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
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

  if ($output !~ /$competitor_name \($competitor_id\)/) {
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

  my($path) = get_base_path("UnitTestPlayground") . "/${event_id}";
  if ( -f "${path}/Competitors/$result_competitor/course") {
    my($cat_cmd) = "cat ${path}/Competitors/${result_competitor}/course";
    my($course) = qx($cat_cmd);
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


#my(@si_results) = qw(2108369;0 start:0 finish:800 201:210 202:300 203:440 204:600 205:700);
sub adjust_si_results {
  my($time_adjust, @si_results) = @_;
  my($i);
  for ($i = 2; $i < scalar(@si_results); $i++) {
    my($control, $timestamp) = split(":", $si_results[$i]);
    $si_results[$i] = $control . ":" . ($timestamp + $time_adjust);
  }

  return (@si_results);
}

#validate_si_results("${path}/Competitors/${si_competitor_1_id}/controls_found", @si_results);
sub validate_si_results {
  my($competitor_path, @si_results) = @_;
  my($i);
  for ($i = 3; $i < scalar(@si_results); $i++) {
    my($control, $timestamp) = split(":", $si_results[$i]);
    my($control_found_file) = sprintf("%06d,%s", $timestamp, $control);
    validate_file_present("${competitor_path}/${control_found_file}");
  }
}

###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname Register9AndCheckOnCourse);

$competitor_1_id = register_one_entrant($COMPETITOR_1, "01-Yellow");
$competitor_2_id = register_one_entrant($COMPETITOR_2, "01-Yellow");
$competitor_3_id = register_one_entrant($COMPETITOR_3, "00-White");
$competitor_4_id = register_one_entrant($COMPETITOR_4, "00-White");
$competitor_5_id = register_one_entrant($COMPETITOR_5, "02-ScoreO");
$competitor_6_id = register_one_entrant($COMPETITOR_6, "02-ScoreO");

# Also register three people using si sticks
%REGISTRATION_INFO = qw(club_name NEOC si_stick 2108369 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info ToyotaCorolla is_member yes);
$REGISTRATION_INFO{"first_name"} = $SI_COMPETITOR_1_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $SI_COMPETITOR_1_LAST_NAME;
$si_competitor_1_id = register_si_entrant(\%REGISTRATION_INFO, "00-White");


%REGISTRATION_INFO = qw(club_name DVOA si_stick 314159 email_address hermione:@mkoconnell.com cell_phone 5083959473 car_info ToyotaCorolla is_member yes);
$REGISTRATION_INFO{"first_name"} = $SI_COMPETITOR_2_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $SI_COMPETITOR_2_LAST_NAME;
$si_competitor_2_id = register_si_entrant(\%REGISTRATION_INFO, "01-Yellow");


%REGISTRATION_INFO = qw(club_name QOC si_stick 141421 email_address ginny:@mkoconnell.com cell_phone 5083291200 car_info FlyingCar is_member yes);
$REGISTRATION_INFO{"first_name"} = $SI_COMPETITOR_3_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $SI_COMPETITOR_3_LAST_NAME;
$si_competitor_3_id = register_si_entrant(\%REGISTRATION_INFO, "02-ScoreO");



check_results(0);
check_on_course(9);
check_competitor_on_course($COMPETITOR_1, $competitor_1_id);
check_competitor_on_course($SI_COMPETITOR_1_NAME, $si_competitor_1_id);

success();



###########
# Test 2 - Mass start of Yellow and ScoreO
# Then Competitor 1 reaches two controls
# Competitor 5 finds a control
# 9 people on course
# 0 results

%TEST_INFO = qw(Testname TestStartersAtEventYellowScoreMassStart);
%GET = qw(key UnitTestPlayground courses_to_start 01-Yellow,02-ScoreO);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

if (($output !~ /$COMPETITOR_1 on/) || ($output !~ /$COMPETITOR_2 on/) || ($output !~ /$COMPETITOR_5 on/) ||
    ($output !~ /$COMPETITOR_6 on/) || ($output =~ /$COMPETITOR_3/) || ($output =~ /$COMPETITOR_4/)) {
  error_and_exit("Incorrect results from starting only Yellow and ScoreO.\n$output");
}

if ($output =~ /ALREADY_STARTED/) {
  error_and_exit("No entrants should be in the already started state.\n$output");
}

if (($output !~ /\#\#\#\#,STARTED,$COMPETITOR_1,01-Yellow/) ||
    ($output !~ /\#\#\#\#,STARTED,$COMPETITOR_2,01-Yellow/) ||
    ($output !~ /\#\#\#\#,STARTED,$COMPETITOR_5,02-ScoreO/) ||
    ($output !~ /\#\#\#\#,STARTED,$COMPETITOR_6,02-ScoreO/)) {
  error_and_exit("Correct parseable entries not found for Yellow or ScoreO.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_3,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_4,00-White/)) {
  error_and_exit("Should not see a parseable entry for White.\n$output");
}

# Was only a QR start, no si entrants should appear at the moment
if (($output =~ /$SI_COMPETITOR_1_NAME/) || ($output =~ /$SI_COMPETITOR_2_NAME on/) ||
    ($output =~ /$SI_COMPETITOR_3_NAME on/)) {
  error_and_exit("Incorrect si stick results - should only start qr runners.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_1_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_2_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_3_NAME,/)) {
  error_and_exit("Should not see a parseable entry for SI users.\n$output");
}


$GET{"si_stick_time"} = 36000;  # 10 am start time
$output = run_mass_start_courses();

# Started si stick registrants should also appear now
if (($output =~ /$SI_COMPETITOR_1_NAME/) || ($output !~ /$SI_COMPETITOR_2_NAME on/) ||
    ($output !~ /$SI_COMPETITOR_3_NAME on/)) {
  error_and_exit("Incorrect si stick results from starting only Yellow and ScoreO.\n$output");
}

if (($output =~ /$COMPETITOR_1 on/) || ($output =~ /$COMPETITOR_2 on/) || ($output =~ /$COMPETITOR_5 on/) ||
    ($output =~ /$COMPETITOR_6 on/) || ($output =~ /$COMPETITOR_3/) || ($output =~ /$COMPETITOR_4/)) {
  error_and_exit("QR competitors should not appear for a SI only start of Yellow and ScoreO.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_1_NAME,/) ||
    ($output !~ /\#\#\#\#,STARTED,$SI_COMPETITOR_2_NAME,/) ||
    ($output !~ /\#\#\#\#,STARTED,$SI_COMPETITOR_3_NAME,/)) {
  error_and_exit("Seeing incorrect parseable entries for SI users.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_1,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_2,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_5,02-ScoreO/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_6,02-ScoreO/)) {
  error_and_exit("Parseable entries found for QR for Yellow or ScoreO incorrectly.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_3,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_4,00-White/)) {
  error_and_exit("Should not see a parseable entry for QR White.\n$output");
}



# Competitor 1 gets two controls
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$GET{"control"} = "202";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "204";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);



# Competitor 5 gets a control
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_5_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$GET{"control"} = "301";
reach_score_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "304";
reach_score_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);


check_results(0);
my($output) = check_on_course(9);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output !~ m#$COMPETITOR_1</td><td>[0-9:]+</td><td>2</td>#) ||
    ($no_newline_output !~ m#$COMPETITOR_5</td><td>[0-9:]+</td><td>304</td>#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}

check_competitor_on_course($COMPETITOR_5, $competitor_5_id);
check_competitor_on_course($SI_COMPETITOR_2_NAME, $si_competitor_2_id);

success();



###########
# Test 3 - Competitor_1 finishes
# Mass start White (competitors 3 and 4, si competitor 1)
# Competitor 2 finds 3 controls
# Competitor 3 finds 1 control
# Competitor 5 finds another control
# Competitor 6 finds a control
%TEST_INFO = qw(Testname TestMassStartWhite);
%GET = qw(key UnitTestPlayground courses_to_start 00-White si_stick_time 37800);  # 10:30am start time
$GET{"event"} = $event_id;
$output = run_mass_start_courses();


if (($output =~ /$COMPETITOR_1/) || ($output =~ /$COMPETITOR_2/) || ($output =~ /$COMPETITOR_5/) ||
    ($output =~ /$COMPETITOR_6/) || ($output =~ /$COMPETITOR_3 on/) || ($output =~ /$COMPETITOR_4 on/)) {
  error_and_exit("Incorrect results from starting only White and not Yellow or ScoreO with si time - should not include qr runners.\n$output");
}

# si stick registrants should not appear - only a QR start
if (($output !~ /$SI_COMPETITOR_1_NAME on/) || ($output =~ /$SI_COMPETITOR_2_NAME/) ||
    ($output =~ /$SI_COMPETITOR_3_NAME/)) {
  error_and_exit("Incorrect si stick results from starting only White.\n$output");
}

# Should be no one already started
if ($output =~ /ALREADY_STARTED/) {
  error_and_exit("No entrants should be in the already started state.\n$output");
}

# Only a SI start, no QR runners should appear
if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_1,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_2,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_3,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_4,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_5,02-ScoreO/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_6,02-ScoreO/)) {
  error_and_exit("Should not see parseable entries for QR runners on White.\n$output");
}

if (($output !~ /\#\#\#\#,STARTED,$SI_COMPETITOR_1_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_2_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_3_NAME,/)) {
  error_and_exit("Incorrect parseable entries for SI users.\n$output");
}


%GET = qw(key UnitTestPlayground courses_to_start 00-White);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

if (($output =~ /$COMPETITOR_1/) || ($output =~ /$COMPETITOR_2/) || ($output =~ /$COMPETITOR_5/) ||
    ($output =~ /$COMPETITOR_6/) || ($output !~ /$COMPETITOR_3 on/) || ($output !~ /$COMPETITOR_4 on/)) {
  error_and_exit("Incorrect results from starting only White and not Yellow or ScoreO.\n$output");
}

if (($output =~ /$SI_COMPETITOR_1_NAME on/) || ($output =~ /$SI_COMPETITOR_2_NAME/) ||
    ($output =~ /$SI_COMPETITOR_3_NAME/)) {
  error_and_exit("No si competitors should appear for a QR mass start on White.\n$output");
}

# Should be no one already started
if ($output =~ /ALREADY_STARTED/) {
  error_and_exit("No entrants should be in the already started state.\n$output");
}

# Only a QR start, no SI runners should appear
if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_1,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_2,01-Yellow/) ||
    ($output !~ /\#\#\#\#,STARTED,$COMPETITOR_3,00-White/) ||
    ($output !~ /\#\#\#\#,STARTED,$COMPETITOR_4,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_5,02-ScoreO/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_6,02-ScoreO/)) {
  error_and_exit("Incorrect parseable entries for QR runners on White.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_1_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_2_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_3_NAME,/)) {
  error_and_exit("Parseable entries for SI users should not appear in a QR start.\n$output");
}

# Competitor 1 finds two more controls
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "206";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "208";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);



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

if ($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1</a></td><td>[0-9 smh:]+</td>#) {
  error_and_exit("View result output for $COMPETITOR_1 wrong.\n$output");
}

my($output) = check_on_course(8);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2</td><td>[0-9:]+</td><td>3</td>#) || ($no_newline_output !~ m#$COMPETITOR_3</td><td>[0-9:]+</td><td>1</td>#) ||
    ($no_newline_output !~ m#$COMPETITOR_5</td><td>[0-9:]+</td><td>305</td>#) || ($no_newline_output !~ m#$COMPETITOR_6</td><td>[0-9:]+</td><td>303</td>#) || 
    ($no_newline_output !~ m#$COMPETITOR_4</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output =~ m#$COMPETITOR_1#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}

check_competitor_on_course($COMPETITOR_3, $competitor_3_id);
check_competitor_on_course($COMPETITOR_6, $competitor_6_id);
check_competitor_on_course($COMPETITOR_5, $competitor_5_id);
check_competitor_on_course($SI_COMPETITOR_3_NAME, $si_competitor_3_id);

success();



###########
# Test 4 -
# Competitor 2 DNFs
# Competitor 3 finds all controls and finishes
# Competitor 4 finds 2 controls and DNFs
# Competitor 5 finishes
# Competitor 6 finds another control and finishes
# All three si competitors finish
%TEST_INFO = qw(Testname AllFinishWithTwoDNFs);

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

##########
# For the si stick results, we need to adjust the control times
# based on the mass start time.  Get the mass start time 
# for the current competitor.
my($mass_start_time) = get_competitor_mass_start_time("UnitTestPlayground", $event_id, $si_competitor_1_id);
##########

# si competitor 1 finishes
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(2108369;0 start:0 finish:800 201:210 202:300 203:440 204:600 205:700);
@si_results = adjust_si_results($mass_start_time, @si_results);
print "New si_results are: " . join(",", @si_results) . "\n";
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($si_competitor_1_id, "2108369", "00-White", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};
validate_si_results("${path}/Competitors/${si_competitor_1_id}/controls_found", @si_results);


# si competitor 2 finishes
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$mass_start_time = get_competitor_mass_start_time("UnitTestPlayground", $event_id, $si_competitor_2_id);
my(@si_results) = qw(314159;0 start:0 finish:1200 202:303 204:459 206:588 208:999 210:1102);
@si_results = adjust_si_results($mass_start_time, @si_results);
print "New si_results are: " . join(",", @si_results) . "\n";
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($si_competitor_2_id, "314159", "01-Yellow", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};
validate_si_results("${path}/Competitors/${si_competitor_2_id}/controls_found", @si_results);



# si competitor 3 finishes
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
$mass_start_time = get_competitor_mass_start_time("UnitTestPlayground", $event_id, $si_competitor_3_id);
my(@si_results) = qw(141421;0 start:0 finish:1523 301:444 305:742 302:808 304:1414);
@si_results = adjust_si_results($mass_start_time, @si_results);
print "New si_results are: " . join(",", @si_results) . "\n";
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


# 21 minutes over time (1203 seconds really, 20m23s), 120 points - 21 point penalty
finish_scoreO_with_stick_successfully($si_competitor_3_id, "141421", "02-ScoreO", 99, \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};
validate_si_results("${path}/Competitors/${si_competitor_3_id}/controls_found", @si_results);


#########
# Validate results
my($output) = check_results(9);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if (($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_2_id">$COMPETITOR_2</a></td><td>DNF</td>#) ||
    ($no_newline_output !~ m#,$competitor_3_id">$COMPETITOR_3</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_5_id">$COMPETITOR_5</a></td><td>[0-9 smh:]+</td><td>100</td>#) ||
    ($no_newline_output !~ m#,$competitor_6_id">$COMPETITOR_6</a></td><td>[0-9 smh:]+</td><td>70</td>#) ||
    ($no_newline_output !~ m#,$competitor_4_id">$COMPETITOR_4</a></td><td>DNF</td>#)) {
  error_and_exit("View result output wrong for 1 or more competitors.\n$output");
}

if (($no_newline_output !~ m#,$si_competitor_1_id">$SI_COMPETITOR_1_NAME</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$si_competitor_2_id">$SI_COMPETITOR_2_NAME</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$si_competitor_3_id">$SI_COMPETITOR_3_NAME</a></td><td>[0-9 smh:]+</td>#)) {
  error_and_exit("View result output wrong for 1 or more si stick competitors.\n$output");
}

check_on_course(0);

success();



#################
#Test 5 - check the splits

%TEST_INFO = qw(Testname CheckSplitsForEntrants);

my(%expected_number_splits);
$expected_number_splits{$competitor_1_id} = 5;
$expected_number_splits{$competitor_2_id} = 3;
$expected_number_splits{$competitor_3_id} = 5;
$expected_number_splits{$competitor_4_id} = 2;
$expected_number_splits{$competitor_5_id} = 3;
$expected_number_splits{$competitor_6_id} = 2;
$expected_number_splits{$si_competitor_1_id} = 5;
$expected_number_splits{$si_competitor_2_id} = 5;
$expected_number_splits{$si_competitor_3_id} = 4;

my($ls_cmd) = "ls -1 " . get_base_path("UnitTestPlayground") . "/${event_id}/Results/*";
my(@results_files) = qx($ls_cmd);
chomp(@results_files);

@results_files = grep(!/$event_id/, @results_files);

#print "Found files " . join("--", @results_files);
my($result_file);
for $result_file (@results_files) {
  next if ($result_file eq "");
  check_splits($result_file, \%expected_number_splits);
}

success();

###################
# Test 6 - restart the events (just SI)

%TEST_INFO = qw(Testname RedoMassStartValidateSIStartedEntries);
%GET = qw(key UnitTestPlayground courses_to_start 00-White,01-Yellow,02-ScoreO si_stick_time 41400);  # 11:30am start time
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

# Should be no one already started
if ($output !~ /ALREADY_STARTED/) {
  error_and_exit("All entrants should be in the already started state.\n$output");
}

my($num_started);
$num_started = () = ($output =~ /(ALREADY_STARTED,.*)/g);
if ($num_started != 3) {
  error_and_exit("Should have found three already started SI runners.\n$output");
}

# No new starts should appear
if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_1,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_2,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_3,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_4,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_5,02-ScoreO/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_6,02-ScoreO/)) {
  error_and_exit("Incorrect parseable entries for QR runners on White.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_1_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_2_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_3_NAME,/)) {
  error_and_exit("Parseable entries for SI users should not appear - runners already started.\n$output");
}

success();

###################
# Test 7 - restart the events (just QR)

%TEST_INFO = qw(Testname RedoMassStartValidateQRStartedEntries);
%GET = qw(key UnitTestPlayground courses_to_start 00-White,01-Yellow,02-ScoreO);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

# Everyone should have already started
if ($output !~ /ALREADY_STARTED/) {
  error_and_exit("All entrants should be in the already started state.\n$output");
}

my($num_started);
$num_started = () = ($output =~ /(ALREADY_STARTED,.*)/g);
if ($num_started != 6) {
  error_and_exit("Should have found six already started QR runners.\n$output");
}

# No new starts should appear
if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_1,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_2,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_3,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_4,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_5,02-ScoreO/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_6,02-ScoreO/)) {
  error_and_exit("Incorrect parseable entries for QR runners on White.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_1_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_2_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_3_NAME,/)) {
  error_and_exit("Parseable entries for SI users should not appear - runners already started.\n$output");
}

success();


###################
# Test 8 - restart the events (All)

%TEST_INFO = qw(Testname RedoMassStartValidateAllStartedEntries);
%GET = qw(key UnitTestPlayground courses_to_start 00-White,01-Yellow,02-ScoreO universal_start yes);
$GET{"event"} = $event_id;
$output = run_mass_start_courses();

# Everyone should have already started
if ($output !~ /ALREADY_STARTED/) {
  error_and_exit("All entrants should be in the already started state.\n$output");
}

my($num_started);
$num_started = () = ($output =~ /(ALREADY_STARTED,.*)/g);
if ($num_started != 9) {
  error_and_exit("Should have found nine already started runners.\n$output");
}

# No new starts should appear
if (($output =~ /\#\#\#\#,STARTED,$COMPETITOR_1,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_2,01-Yellow/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_3,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_4,00-White/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_5,02-ScoreO/) ||
    ($output =~ /\#\#\#\#,STARTED,$COMPETITOR_6,02-ScoreO/)) {
  error_and_exit("Incorrect parseable entries for QR runners on White.\n$output");
}

if (($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_1_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_2_NAME,/) ||
    ($output =~ /\#\#\#\#,STARTED,$SI_COMPETITOR_3_NAME,/)) {
  error_and_exit("Parseable entries for SI users should not appear - runners already started.\n$output");
}

success();


############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
