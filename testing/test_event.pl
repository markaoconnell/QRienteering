#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($COMPETITOR_1) = "Mark_OConnell";
my($COMPETITOR_2) = "Karen_Yeowell";
my($COMPETITOR_3) = "LydBid";
my($COMPETITOR_4) = "JohnnyJohnCon";
my($competitor_1_id, $competitor_2_id, $competitor_3_id, $competitor_4_id);

initialize_event();
set_test_info(\%GET, \%COOKIE, \%TEST_INFO, $0);

sub register_one_entrant {
  %GET = qw(event UnitTestingEvent);
  $GET{"course"} = $_[1];
  $GET{"competitor_name"} = $_[0];
  %COOKIE = ();  # empty hash
  
  #print "Register $_[0] on $_[1]\n";

  register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
  return($TEST_INFO{"competitor_id"});
}

sub check_results {
  my($expected_table_rows) = @_;

  %GET = qw(event UnitTestingEvent);
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../view_results.php";
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

  %GET = qw(event UnitTestingEvent);
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../on_course.php";
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

  %GET = qw(event UnitTestingEvent include_competitor_id 1);
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../on_course.php";
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

  if ($result_file =~ /^[0-9]+,([0-9a-f]+)$/) {
    $result_competitor = $1;
  }
  else {
    error_and_exit("Incorrect result file: $result_file.");
  }

  if ( -f "./UnitTestingEvent/Competitors/$result_competitor/course") {
    my($course) = qx(cat ./UnitTestingEvent/Competitors/$result_competitor/course);
    chomp($course);

    %GET = qw(event UnitTestingEvent);
    $GET{"course"} = $course;
    $GET{"entry"} = $result_file;
    %COOKIE = ();
    hashes_to_artificial_file();

    my($cmd) = "php ../show_splits.php";
    my($output);
    $output = qx($cmd);

    my($actual_split_rows);
    $actual_split_rows = () = $output =~ /(<tr><td>)/g;

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
%TEST_INFO = qw(Testname Register4AndCheckOnCourse);

$competitor_1_id = register_one_entrant($COMPETITOR_1, "02-Yellow");
$competitor_2_id = register_one_entrant($COMPETITOR_2, "02-Yellow");
$competitor_3_id = register_one_entrant($COMPETITOR_3, "01-White");
$competitor_4_id = register_one_entrant($COMPETITOR_4, "01-White");

check_results(0);
check_on_course(4);
check_competitor_on_course($COMPETITOR_1, $competitor_1_id);

success();



###########
# Test 2 - Competitor 1 starts the course and reaches two controls
# Competitor 2 starts the course
# 2 people on course
# 0 results

# Competitor 1 starts and gets two controls
%TEST_INFO = qw(Testname TestTwoStartersAtEvent);
%COOKIE = qw(event UnitTestingEvent course 02-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "202";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "204";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);



# Competitor 2 starts
%COOKIE = qw(event UnitTestingEvent course 02-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

check_results(0);
my($output) = check_on_course(4);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output !~ m#$COMPETITOR_1</td><td>[0-9:]+</td><td>2</td>#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}


check_competitor_on_course($COMPETITOR_2, $competitor_2_id);

success();



###########
# Test 3 - Competitor_1 finishes
# Competitor 3 starts
# Competitor 2 finds 3 controls
# Competitor 3 finds 1 control
# Competitor 4 starts
%TEST_INFO = qw(Testname OneFinisherTwoMoreStarters);

# Competitor 1 finds two more controls
%COOKIE = qw(event UnitTestingEvent course 02-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
%GET = ();  # empty hash


$GET{"control"} = "206";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "208";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 3 starts
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);



# Competitor 2 finds 2 controls
%COOKIE = qw(event UnitTestingEvent course 02-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
%GET = ();  # empty hash

$GET{"control"} = "202";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "204";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 3 finds a control
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
%GET = ();  # empty hash


$GET{"control"} = "201";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 2 finds the next control
%COOKIE = qw(event UnitTestingEvent course 02-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
%GET = ();  # empty hash

$GET{"control"} = "206";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 4 starts
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_4_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

# Competitor 1 finds the final control and finishes
%COOKIE = qw(event UnitTestingEvent course 02-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
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

my($output) = check_on_course(3);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2</td><td>[0-9:]+</td><td>3</td>#) || ($no_newline_output !~ m#$COMPETITOR_3</td><td>[0-9:]+</td><td>1</td>#) ||
    ($no_newline_output !~ m#$COMPETITOR_4</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output =~ m#$COMPETITOR_1#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}

check_competitor_on_course($COMPETITOR_3, $competitor_3_id);

success();



###########
# Test 4 -
# Competitor 2 DNFs
# Competitor 3 finds all controls and finishes
# Competitor 4 finds 2 controls and DNFs
%TEST_INFO = qw(Testname AllFinishWithTwoDNFs);

# Competitor 3 finds more controls
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
%GET = ();  # empty hash


$GET{"control"} = "202";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "203";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);




# Competitor 2 DNFs
%COOKIE = qw(event UnitTestingEvent course 02-Yellow);
$COOKIE{"competitor_id"} = $competitor_2_id;
%GET = ();  # empty hash

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);



# Competitor 3 finds all remaining controls
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
%GET = ();  # empty hash


$GET{"control"} = "204";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "205";
reach_control_successfully(4, \%GET, \%COOKIE, \%TEST_INFO);


# Competitor 4 finds some controls then DNFs
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_4_id;
%GET = ();  # empty hash

$GET{"control"} = "201";
reach_control_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "202";
reach_control_successfully(1, \%GET, \%COOKIE, \%TEST_INFO);

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);


# Competitor 3 finishes
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_3_id;
%GET = ();  # empty hash

finish_successfully(\%GET, \%COOKIE, \%TEST_INFO);


#########
# Validate results
my($output) = check_results(4);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if (($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_2_id">$COMPETITOR_2</a></td><td>DNF</td>#) ||
    ($no_newline_output !~ m#,$competitor_3_id">$COMPETITOR_3</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_4_id">$COMPETITOR_4</a></td><td>DNF</td>#)) {
  error_and_exit("View result output wrong for 1 or more competitors.\n$output");
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

my(@results_files) = qx(ls -1 ./UnitTestingEvent/Results/*);
chomp(@results_files);

@results_files = grep(!/UnitTestingEvent/, @results_files);

#print "Found files " . join(",", @results_files);
my($result_file);
for $result_file (@results_files) {
  next if ($result_file eq "");
  check_splits($result_file, \%expected_number_splits);
}

success();

############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);