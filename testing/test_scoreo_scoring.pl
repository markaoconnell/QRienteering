#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($ADD_INTER_CONTROL_DELAY) = 0;
my($COMPETITOR_NAME) = "Mark_OConnell_ScoreO_Testing";
my($competitor_id);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
create_key_file();
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
set_no_redirects_for_event("UnitTestingEvent", "UnitTestPlayground");


sub run_score_course {
  my($time_on_course, @controls_to_find) = @_;

  %GET = qw(key UnitTestPlayground event UnitTestingEvent course 02-ScoreO);
  $GET{"competitor_name"} = $COMPETITOR_NAME;
  %COOKIE = ();  # empty hash
  
  register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
  $competitor_id = $TEST_INFO{"competitor_id"};
  
  
  %COOKIE = qw(key UnitTestPlayground event UnitTestingEvent course 02-ScoreO);
  $COOKIE{"competitor_id"} = $competitor_id;
  %GET = ();  # empty hash
  
  start_successfully(\%GET, \%COOKIE, \%TEST_INFO);

  # Artificially adjust the start time on the course
  my($artificial_start_time) = time() - $time_on_course;
  my($start_file_path) = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"} . "/Competitors/${competitor_id}/controls_found/start";
  open(START_FILE, ">${start_file_path}");
  print START_FILE $artificial_start_time;
  close(START_FILE);
  
  
  #
  # Find the controls specified
  %COOKIE = qw(key UnitTestPlayground event UnitTestingEvent course 02-ScoreO);
  $COOKIE{"competitor_id"} = $competitor_id;

  my($this_control);
  my($controls_found_count) = 0;
  foreach $this_control (@controls_to_find) {
    $GET{"control"} = $this_control;

    if ($this_control =~ /^-/) {
      # Control not on the course
      hashes_to_artificial_file();
      my($cmd) = "php ../OMeet/reach_control.php";
      my($output);
      $output = qx($cmd);

      if ($output !~ /Found wrong control, control $this_control not on course ScoreO/){
        error_and_exit("Web page output wrong, correct control string not found.\n$output");
      }
    }
    else {
      reach_score_control_successfully($controls_found_count, \%GET, \%COOKIE, \%TEST_INFO);
      $controls_found_count++;

      # useful when testing duplicate control scans to make sure the timestamps differ
      sleep(1) if ($ADD_INTER_CONTROL_DELAY);
    }
  }
}


################3
# Test1: Find all the controls in time
# Finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFindAllScoreOControls);

run_score_course(120, qw(301 302 303 304 305));
finish_score_successfully(150, \%GET, \%COOKIE, \%TEST_INFO);

success();


################3
# Test2: Find all the controls, but 1 minute penalty
#
%TEST_INFO = qw(Testname TestFindAllScoreOControls1MinPenalty);

run_score_course(330, qw(301 302 303 304 305));
finish_score_successfully(149, \%GET, \%COOKIE, \%TEST_INFO);

success();


################3
# Test3: Find all the controls, but 6 minute penalty
#
%TEST_INFO = qw(Testname TestFindAllScoreOControls5MinPenalty);

run_score_course(630, qw(301 302 303 304 305));
finish_score_successfully(144, \%GET, \%COOKIE, \%TEST_INFO);

success();



################3
# Test4: Find some of the controls, no penalty
#
%TEST_INFO = qw(Testname TestFindThreeScoreOControls);

run_score_course(230, qw(301 303 305));
finish_score_successfully(90, \%GET, \%COOKIE, \%TEST_INFO);

success();


################3
# Test5: Find some of the controls, 2 minute penalty
#
%TEST_INFO = qw(Testname TestFindTwoScoreOControls2MinPenalty);

run_score_course(400, qw(302 304));
finish_score_successfully(58, \%GET, \%COOKIE, \%TEST_INFO);

success();


#################
# Test6: Find 1 control multiple times
#
%TEST_INFO = qw(Testname TestFindDuplicateScoreOControls);

$ADD_INTER_CONTROL_DELAY = 1;
run_score_course(140, qw(301 301 301 301));
finish_score_successfully(10, \%GET, \%COOKIE, \%TEST_INFO);

success();


#################
# Test7: Mix controls found and duplicated
#
%TEST_INFO = qw(Testname TestFindLotsDuplicateScoreOControls);

$ADD_INTER_CONTROL_DELAY = 1;
run_score_course(150, qw(301 302 303 301 304 302 303 305 301 302 303 304 305));
finish_score_successfully(150, \%GET, \%COOKIE, \%TEST_INFO);

success();


#################
# Test8: Check finding bad controls
#
%TEST_INFO = qw(Testname TestFindBadScoreOControls);

$ADD_INTER_CONTROL_DELAY = 0;
run_score_course(150, qw(301 302 303 -301 304 -302 -303 305 -301 -302 -303 -304 -305));
finish_score_successfully(150, \%GET, \%COOKIE, \%TEST_INFO);

success();



#################
# Test9: Check a negative score
# 22 minutes over, 22 point penalty
#
%TEST_INFO = qw(Testname TestNegativeScoreOResult);

$ADD_INTER_CONTROL_DELAY = 0;
run_score_course(1569, qw(301));
finish_score_successfully(-12, \%GET, \%COOKIE, \%TEST_INFO);

success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
