#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($cmd, $output, $output2, $competitor_id, $path, $time_now, $controls_found_path);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_Bad_Finish";

initialize_event();
set_test_info(\%GET, \%COOKIE, \%TEST_INFO, $0);


###########
# Test 1 - finish the course without registering
# Should return an error message
%TEST_INFO = qw(Testname TestFinishNoRegistration);
%COOKIE = ();
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
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
%COOKIE = qw(event OldEvent course 01-White);
$COOKIE{"competitor_id"} = "moc";
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
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
%COOKIE = qw(event UnitTestingEvent course 03-Orange);
$COOKIE{"competitor_id"} = "moc";
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
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

%GET = qw(event UnitTestingEvent course 01-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
%COOKIE = ();  # empty hash

register_successfully(\%GET, \%COOKIE, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};


# Now finish the course (should not work, as we haven't started)
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if (($output !~ /Course White not yet started/) || ($output !~ /Please scan the start QR code to start a course/)) {
  error_and_exit("Web page output wrong, not started errors not found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
my($controls_found_path) = "$path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($path, qw(name course controls_found));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join(",", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (-d "./UnitTestingEvent/Results/01-White") {
  my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/01-White", ());
  if (grep(/$competitor_id/, @results_array)) {
    error_and_exit("Results file found for $competitor_id, directory contents are: " . join(",", @results_array));
  }
}




###########
# Test 4 - finish the course twice
# Call start (already registered in prior test), then finish twice
# This will be a dnf, but I just want to make sure the second finish is handled
%TEST_INFO = qw(Testname FinishTwice);

%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash

start_successfully(\%GET, \%COOKIE, \%TEST_INFO);


# Now finish the course
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash

finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);



# Now finish the course a second time
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash

$output = finish_with_dnf(\%GET, \%COOKIE, \%TEST_INFO);


if (($output !~ /Course complete, time taken/) || ($output !~ /Results on White/) ||
    ($output !~ /$COMPETITOR_NAME/) || ($output !~ /DNF/) || ($output !~ /Second scan of finish/)) {
  error_and_exit("Web page output wrong, no second finish scan message found.\n$output");
}

success();


############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);