#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE);
my($cmd, $output, $output2, $competitor_id, $path, $time_now);
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
hashes_to_artificial_file();
$cmd = "php ../register_competitor.php";
$output = qx($cmd);

if ($output !~ /Registration complete: $COMPETITOR_NAME on White/) {
  error_and_exit("Web page output wrong, registration complete string not found.\n$output");
}

#print $output;

my($competitor_id);
$competitor_id = qx(ls -1t ./UnitTestingEvent/Competitors | head -n 1);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";
if (! -d "./UnitTestingEvent/Competitors/$competitor_id") {
  error_and_exit("Directory ./UnitTestingEvent/Competitors/$competitor_id not found.");
}

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if ((! -f "$path/name") || (! -f "$path/course")) {
  error_and_exit("One of $path/name or $path/course does not exist.");
}

@directory_contents = check_directory_contents($path, qw(name course));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}

my(@name_file) = file_get_contents("$path/name");
my(@course_file) = file_get_contents("$path/course");

if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne $COMPETITOR_NAME) || ($course_file[0] ne "01-White")) {
  error_and_exit("File contents wrong, name_file: " . join(",", @name_file) . "\n\tcourse_file: " . join("," , @course_file));
}

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
if (-f "$path/finish") {
  error_and_exit("$path/finish does exist.");
}

@directory_contents = check_directory_contents($path, qw(name course));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
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
hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /White course started for $COMPETITOR_NAME./) {
  error_and_exit("Web page output wrong, course start string not found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/start") {
  error_and_exit("$path/start does not exist.");
}

@directory_contents = check_directory_contents($path, qw(name course start));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}


@file_contents_array = file_get_contents("$path/start");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, start_time_file: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
}


# Now finish the course
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if (($output !~ /Course complete, time taken/) || ($output !~ /Results on White/) ||
    ($output !~ /$COMPETITOR_NAME/) || ($output !~ /DNF/) || ($output =~ /Second scan of finish/)) {
  error_and_exit("Web page output wrong, DNF entry not found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/finish") {
  error_and_exit("$path/finish does not exist.");
}

@directory_contents = check_directory_contents($path, qw(name course start finish dnf));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}


@file_contents_array = file_get_contents("$path/finish");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, $path/finish: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
}

my(@start_time_array) = file_get_contents("$path/start");
my($results_file) = sprintf("%06d,%s", (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);


my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/01-White", $results_file);
if (grep(/NOTFOUND:$results_file/, @results_array)) {
  error_and_exit("No results file ($results_file) found, contents are: " . join(",", @results_array));
}



# Now finish the course a second time
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if (($output !~ /Course complete, time taken/) || ($output !~ /Results on White/) ||
    ($output !~ /$COMPETITOR_NAME/) || ($output !~ /DNF/) || ($output !~ /Second scan of finish/)) {
  error_and_exit("Web page output wrong, no second finish scan message found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/finish") {
  error_and_exit("$path/finish does not exist.");
}

@directory_contents = check_directory_contents($path, qw(name course start finish dnf));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}


@file_contents_array = file_get_contents("$path/finish");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, $path/finish: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
}

my(@start_time_array) = file_get_contents("$path/start");
my($results_file) = sprintf("%06d,%s", (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);


my(@results_array) = check_directory_contents("./UnitTestingEvent/Results/01-White", $results_file);
if (grep(/NOTFOUND:$results_file/, @results_array)) {
  error_and_exit("No results file ($results_file) found, contents are: " . join(",", @results_array));
}

success();


############
# Cleanup

qx(rm -rf UnitTestingEvent);
qx(rm artificial_input);
