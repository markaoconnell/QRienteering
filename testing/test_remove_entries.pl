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

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
create_key_file();
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");

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

sub remove_one_entrant {
  %GET = qw(key UnitTestPlayground);
  $GET{"Remove-" . $_[0]} = $_[0];
  $GET{"event"} = $event_id;
  $GET{"confirmed"} = "true";
  %COOKIE = ();  # empty hash
  
  hashes_to_artificial_file();

  my($cmd) = "php ../OMeetMgmt/remove_from_event.php";
  my($output);
  $output = qx($cmd);

  my($name_of_competitor_to_remove) = $_[1];
  if ($output !~ /$name_of_competitor_to_remove/) {
    error_and_exit("Did not see competitor name ${name_of_competitor_to_remove} in removal list.\n$output");
  }

  my($removed_competitor_path) = get_base_path($GET{"key"}) . "/" . $GET{"event"} . "/removed_competitors/" . $_[0];
  if (! -d $removed_competitor_path) {
    error_and_exit("Removed competitor entry $removed_competitor_path not found.");
  }

  return;
}

sub remove_all_entrants {
  %GET = qw(key UnitTestPlayground);
  $GET{"Remove-all"} = 1;
  $GET{"event"} = $event_id;
  $GET{"confirmed"} = "true";
  %COOKIE = ();  # empty hash
  
  hashes_to_artificial_file();

  my($cmd) = "php ../OMeetMgmt/remove_from_event.php";
  my($output);
  $output = qx($cmd);

  if ($output !~ /finish marker/) {
    error_and_exit("Did not see finish marker indicating removed completed runner.\n$output");
  }

  return;
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

sub check_competitor_not_on_course {
  my($competitor_name, $competitor_id) = @_;

  %GET = qw(key UnitTestPlayground include_competitor_id 1);
  $GET{"event"} = $event_id;
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../OMeet/on_course.php";
  my($output);
  $output = qx($cmd);

  if ($output =~ /$competitor_name \($competitor_id\)/) {
    error_and_exit("Name and id - $competitor_name and $competitor_id - are found in on_course output.\n$output");
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
# Test 1 - Remove one of the 6 entrants
%TEST_INFO = qw(Testname Register6AndRemoveOne);

$competitor_1_id = register_one_entrant($COMPETITOR_1, "01-Yellow");
$competitor_2_id = register_one_entrant($COMPETITOR_2, "01-Yellow");
$competitor_3_id = register_one_entrant($COMPETITOR_3, "00-White");
$competitor_4_id = register_one_entrant($COMPETITOR_4, "00-White");
$competitor_5_id = register_one_entrant($COMPETITOR_5, "02-ScoreO");
$competitor_6_id = register_one_entrant($COMPETITOR_6, "02-ScoreO");

remove_one_entrant($competitor_3_id, $COMPETITOR_3);

check_results(0);
check_on_course(5);
check_competitor_on_course($COMPETITOR_1, $competitor_1_id);
check_competitor_not_on_course($COMPETITOR_3, $competitor_3_id);

success();



###########
# Test 2 - Competitor 1 starts the course and reaches two controls
# Competitor 2 starts the course
# Competitor 5 start the course and finds a control
# Remove competitor 5
# 3 people on course
# 0 results

# Competitor 1 starts and gets two controls
%TEST_INFO = qw(Testname TestThreeStartersAtRemoveAnother);
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
my($output) = check_on_course(5);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output !~ m#$COMPETITOR_1</td><td>[0-9:]+</td><td>2</td>#) ||
    ($no_newline_output !~ m#$COMPETITOR_5</td><td>[0-9:]+</td><td>304</td>#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}

check_competitor_on_course($COMPETITOR_5, $competitor_5_id);

remove_one_entrant($competitor_5_id, $COMPETITOR_5);

check_results(0);
my($output) = check_on_course(4);

my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output !~ m#$COMPETITOR_1</td><td>[0-9:]+</td><td>2</td>#) ||
    ($no_newline_output =~ m#$COMPETITOR_5</td><td>[0-9:]+</td><td>304</td>#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}
check_competitor_not_on_course($COMPETITOR_5, $competitor_5_id);

success();



###########
# Test 3 - Competitor_1 finishes
# Competitor 2 finds 3 controls
# Competitor 4 starts
# Competitor 6 starts
%TEST_INFO = qw(Testname OneFinisherTwoMoreStartersNoRemoves);

# Competitor 1 finds two more controls
%COOKIE = qw(key UnitTestPlayground course 01-Yellow);
$COOKIE{"competitor_id"} = $competitor_1_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash


$GET{"control"} = "206";
reach_control_successfully(2, \%GET, \%COOKIE, \%TEST_INFO);

$GET{"control"} = "208";
reach_control_successfully(3, \%GET, \%COOKIE, \%TEST_INFO);



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

if ($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1</a></td><td>[0-9 smh:]+</td>#) {
  error_and_exit("View result output for $COMPETITOR_1 wrong.\n$output");
}

my($output) = check_on_course(3);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;  # Easier to check in a regex without the newlines

if (($no_newline_output !~ m#$COMPETITOR_2</td><td>[0-9:]+</td><td>3</td>#) || ($no_newline_output =~ m#$COMPETITOR_3</td><td>[0-9:]+</td><td>1</td>#) ||
    ($no_newline_output =~ m#$COMPETITOR_5</td><td>[0-9:]+</td><td>305</td>#) || ($no_newline_output !~ m#$COMPETITOR_6</td><td>[0-9:]+</td><td>303</td>#) || 
    ($no_newline_output !~ m#$COMPETITOR_4</td><td>[0-9:]+</td><td>start</td>#) || ($no_newline_output =~ m#$COMPETITOR_1#)) {
  error_and_exit("On course output showing wrong controls.\n$output");
}

check_competitor_not_on_course($COMPETITOR_3, $competitor_3_id);
check_competitor_on_course($COMPETITOR_6, $competitor_6_id);
check_competitor_not_on_course($COMPETITOR_5, $competitor_5_id);

success();



###########
# Test 4 -
# Competitor 2 DNFs
# Competitor 4 finds 2 controls and DNFs
# Competitor 6 finds another control and finishes
%TEST_INFO = qw(Testname AllFinishWithTwoDNFsRemoveOneDNF);


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


# Competitor 6 finishes
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_6_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

finish_score_successfully(70, \%GET, \%COOKIE, \%TEST_INFO);


#########
# Validate results
my($output) = check_results(4);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if (($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_2_id">$COMPETITOR_2</a></td><td>DNF</td>#) ||
    ($no_newline_output =~ m#,$competitor_3_id">$COMPETITOR_3</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output =~ m#,$competitor_5_id">$COMPETITOR_5</a></td><td>[0-9 smh:]+</td><td>100</td>#) ||
    ($no_newline_output !~ m#,$competitor_6_id">$COMPETITOR_6</a></td><td>[0-9 smh:]+</td><td>70</td>#) ||
    ($no_newline_output !~ m#,$competitor_4_id">$COMPETITOR_4</a></td><td>DNF</td>#)) {
  error_and_exit("View result output wrong for 1 or more competitors.\n$output");
}

check_on_course(0);

remove_one_entrant($competitor_4_id, $COMPETITOR_4);

#########
# Validate results
my($output) = check_results(3);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if (($no_newline_output !~ m#,$competitor_1_id">$COMPETITOR_1</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output !~ m#,$competitor_2_id">$COMPETITOR_2</a></td><td>DNF</td>#) ||
    ($no_newline_output =~ m#,$competitor_3_id">$COMPETITOR_3</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output =~ m#,$competitor_5_id">$COMPETITOR_5</a></td><td>[0-9 smh:]+</td><td>100</td>#) ||
    ($no_newline_output !~ m#,$competitor_6_id">$COMPETITOR_6</a></td><td>[0-9 smh:]+</td><td>70</td>#) ||
    ($no_newline_output =~ m#,$competitor_4_id">$COMPETITOR_4</a></td><td>DNF</td>#)) {
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
#Test 6 - remove all entrants

%TEST_INFO = qw(Testname RemoveAllEntrants);

remove_all_entrants();

my($results_path) = get_base_path("UnitTestPlayground") . "/${event_id}/Results/*";
my(@results_files) = qx(ls -1 $results_path);
chomp(@results_files);

@results_files = grep(!/$event_id/, @results_files);
@results_files = grep(!/^$/, @results_files);

if (scalar(@results_files) != 0) {
  error_and_exit("Incorrectly found result files after removing all: " . join(", ", @results_files) . "\n");
}

my($removed_competitors_path) = get_base_path("UnitTestPlayground") . "/${event_id}/removed_competitors";
my(@removed_competitors_list) = qx(ls -1 $removed_competitors_path);
chomp(@removed_competitors_list);

@removed_competitors_list = grep(!/$event_id/, @removed_competitors_list);
@removed_competitors_list = grep(!/^$/, @removed_competitors_list);

if (scalar(@removed_competitors_list) != 6) {
  error_and_exit("Wrong number of removed competitors after removing all: " . join(", ", @removed_competitors_list) . "\n");
}

#########
# Validate results
my($output) = check_results(0);
my($no_newline_output) = $output;
$no_newline_output =~ s/\n//g;

if (($no_newline_output =~ m#,$competitor_1_id">$COMPETITOR_1</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output =~ m#,$competitor_2_id">$COMPETITOR_2</a></td><td>DNF</td>#) ||
    ($no_newline_output =~ m#,$competitor_3_id">$COMPETITOR_3</a></td><td>[0-9 smh:]+</td>#) ||
    ($no_newline_output =~ m#,$competitor_5_id">$COMPETITOR_5</a></td><td>[0-9 smh:]+</td><td>100</td>#) ||
    ($no_newline_output =~ m#,$competitor_6_id">$COMPETITOR_6</a></td><td>[0-9 smh:]+</td><td>70</td>#) ||
    ($no_newline_output =~ m#,$competitor_4_id">$COMPETITOR_4</a></td><td>DNF</td>#)) {
  error_and_exit("View result output wrong for 1 or more competitors.\n$output");
}

check_on_course(0);

success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
