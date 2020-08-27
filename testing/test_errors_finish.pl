#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output, $output2, $competitor_id, $competitor_id2, $path, $time_now, $controls_found_path);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_Bad_Finish";
my($COMPETITOR_NAME_2) = "Mark_OConnell_Bad_Finish_ScoreO";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");



###########
# Test 1 - finish the course without registering
# Should return an error message
%TEST_INFO = qw(Testname TestFinishNoRegistration);
%COOKIE = ();
%GET = qw(key UnitTestPlayground);  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/finish_course.php";
$output = qx($cmd);

if ($output !~ /probably not registered for a course/) {
  error_and_exit("Web page output wrong, should receive not registered output.\n$output");
}

#print $output;

success();



###########
# Test 2 - finish with an unknown event
# Should return an error message
%TEST_INFO = qw(Testname TestFinishOldEvent);
%COOKIE = qw(key UnitTestPlayground event OldEvent course 00-White);
$COOKIE{"competitor_id"} = "moc";
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/finish_course.php";
$output = qx($cmd);

if ($output !~ /appears to be no longer appears valid/){
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();


###########
# Test 3 - finish with a bad course
# Should return an error message
%TEST_INFO = qw(Testname TestFinishGoodEventBadCourse);
%COOKIE = qw(key UnitTestPlayground course 03-Orange);
$COOKIE{"competitor_id"} = "moc";
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/finish_course.php";
$output = qx($cmd);

if ($output !~ /appears to be no longer appears valid/){
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();



###########
# Test 4 - finish after registering but not starting
# First register, then call finish without calling start
%TEST_INFO = qw(Testname FinishWithoutStart);

%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};


# Now finish the course (should not work, as we haven't started)
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/finish_course.php";
$output = qx($cmd);

if (($output !~ /Course White not yet started/) || ($output !~ /Please scan the start QR code to start a course/)) {
  error_and_exit("Web page output wrong, not started errors not found.\n$output");
}

#print $output;

$path = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"};
my($competitor_path) = "${path}/Competitors/$competitor_id";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (-d "${path}/Results/00-White") {
  my(@results_array) = check_directory_contents("${path}/Results/00-White", ());
  if (grep(/$competitor_id/, @results_array)) {
    error_and_exit("Results file found for $competitor_id, directory contents are: " . join("--", @results_array));
  }
}




###########
# Test 5 - finish the course twice
# Call start (already registered in prior test), then finish twice
# This will be a dnf, but I just want to make sure the second finish is handled
%TEST_INFO = qw(Testname FinishTwice);

%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);


# Now finish the course
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);



# Now finish the course a second time
%COOKIE = qw(key UnitTestPlayground course 00-White);
$COOKIE{"competitor_id"} = $competitor_id;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$output = finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);


if (($output !~ /course complete.*DNF.*, time taken/) || ($output !~ /Results on White/) ||
    ($output !~ /$COMPETITOR_NAME/) || ($output !~ /DNF/) || ($output !~ /Second scan of finish/)) {
  error_and_exit("Web page output wrong, no second finish scan message found.\n$output");
}

success();


###########
# Test 6 - finish after registering but not starting
# First register, then call finish without calling start
# Try it with a ScoreO
%TEST_INFO = qw(Testname FinishWithoutStartScoreO);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"competitor_name"} = $COMPETITOR_NAME_2;
$GET{"event"} = $event_id;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id2 = $TEST_INFO{"competitor_id"};


# Now finish the course (should not work, as we haven't started)
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../OMeet/finish_course.php";
$output = qx($cmd);

if (($output !~ /Course ScoreO not yet started/) || ($output !~ /Please scan the start QR code to start a course/)) {
  error_and_exit("Web page output wrong, not started errors not found.\n$output");
}

#print $output;

$path = get_base_path($COOKIE{"key"}) . "/" . $COOKIE{"event"};
$competitor_path = "${path}/Competitors/$competitor_id2";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (-d "${path}/Results/02-ScoreO") {
  my(@results_array) = check_directory_contents("${path}/Results/02-ScoreO", ());
  if (grep(/$competitor_id2/, @results_array)) {
    error_and_exit("Results file found for $competitor_id2, directory contents are: " . join("--", @results_array));
  }
}




###########
# Test 7 - finish the course twice on a ScoreO
# Call start (already registered in prior test), then finish twice
%TEST_INFO = qw(Testname FinishTwiceScoreO);

%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);


# Now finish the course
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

finish_score_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);



# Now finish the course a second time
%COOKIE = qw(key UnitTestPlayground course 02-ScoreO);
$COOKIE{"competitor_id"} = $competitor_id2;
$COOKIE{"event"} = $event_id;
%GET = ();  # empty hash

$output = finish_score_successfully(0, \%GET, \%COOKIE, \%TEST_INFO);


if (($output !~ /course complete.*, time taken/) || ($output !~ /Results on ScoreO/) ||
    ($output !~ /$COMPETITOR_NAME_2/) || ($output =~ /DNF/) || ($output !~ /Second scan of finish/)) {
  error_and_exit("Web page output wrong, no second finish scan message found.\n$output");
}

success();


############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
