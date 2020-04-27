#!/usr/bin/perl

use strict;

my(%GET);
my(%COOKIE);
my(%TEST_INFO);
my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);

sub error_and_exit {
  my($error_string) = @_;
  print "ERROR: $error_string\n";
  my($entry);
  foreach $entry (sort(keys(%TEST_INFO))) {
    print "\tTESTINFO: $entry $TEST_INFO{$entry}\n";
  }
  exit(1);
}

sub success {
  if (defined($TEST_INFO{"Testname"})) {
    print "Test " . $TEST_INFO{"Testname"} . ": successful.\n";
  }
  else {
    print "Unknown test successful.\n";
  }
}

sub hashes_to_artificial_file {
  open(ARTIFICIAL_FILE, ">./artificial_input");
  my($entry);
  foreach $entry (keys(%GET)) {
    print ARTIFICIAL_FILE "GET $entry $GET{$entry}\n";
  }
  foreach $entry (keys(%COOKIE)) {
    print ARTIFICIAL_FILE "COOKIE $entry $COOKIE{$entry}\n";
  }
  close(ARTIFICIAL_FILE);
}

sub file_get_contents {
  my($file_to_read) = @_;

  open(FILE_TO_READ, "<$file_to_read");
  my(@file_contents) = <FILE_TO_READ>;
  close(FILE_TO_READ);

  return (@file_contents);
}

sub check_directory_contents {
  my($directory_path, @required_entries) = @_;
  my(%found_directory_contents);

  map { chomp($_); $found_directory_contents{$_} = 1; } qx(ls -1 $directory_path);

  my($required_entry);
  foreach $required_entry (@required_entries) {
    if (!defined($found_directory_contents{$required_entry})) {
      $found_directory_contents{"NOTFOUND:" . $required_entry} = 1;
    }
    else {
      delete($found_directory_contents{$required_entry});
    }
  }

  return(keys(%found_directory_contents));
}

# Make the event for testing purposes
mkdir("UnitTestingEvent");
mkdir("UnitTestingEvent/Competitors");
mkdir("UnitTestingEvent/Results");
mkdir("UnitTestingEvent/Courses");
open(NO_REDIRECTS, ">./UnitTestingEvent/no_redirects"); close(NO_REDIRECTS);
mkdir("UnitTestingEvent/Courses/01-White");
mkdir("UnitTestingEvent/Courses/02-Yellow");
open(WHITE, ">./UnitTestingEvent/Courses/01-White/controls.txt");
print WHITE "201\n202\n203\n204\n205";
close (WHITE);
open(YELLOW, ">./UnitTestingEvent/Courses/02-Yellow/controls.txt");
print YELLOW "202\n204\206\n208\n210";
close(YELLOW);

###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname TestSuccessRegistration);
%GET = qw(competitor_name Mark_OConnell_Testing event UnitTestingEvent course 01-White);
%COOKIE = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../register_competitor.php";
$output = qx($cmd);

if ($output !~ /Registration complete: Mark_OConnell_Testing on White/) {
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

if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne "Mark_OConnell_Testing") || ($course_file[0] ne "01-White")) {
  error_and_exit("File contents wrong, name_file: " . join(",", @name_file) . "\n\tcourse_file: " . join("," , @course_file));
}

success();




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestSuccessStart);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = ();  # empty hash
hashes_to_artificial_file();
$cmd = "php ../start_course.php";
$output = qx($cmd);

if ($output !~ /White course started for Mark_OConnell_Testing./) {
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

success();


###########
# Test 3 - find a control
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFind201);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = qw(control 201);
hashes_to_artificial_file();
$cmd = "php ../reach_control.php";
$output = qx($cmd);

if ($output !~ /Correct!  Reached 201, control #1 on White/) {
  error_and_exit("Web page output wrong, course start string not found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/0") {
  error_and_exit("$path/0 (found first control) does not exist.");
}

@directory_contents = check_directory_contents($path, qw(name course start 0));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}


@file_contents_array = file_get_contents("$path/0");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, $path/0: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
}

success();


###########
# Test 4 - finish the course
# Validate that the correct entry is created
%TEST_INFO = qw(Testname TestFinishEarlyDNF);
%COOKIE = qw(event UnitTestingEvent course 01-White);
$COOKIE{"competitor_id"} = $competitor_id;
%GET = (); # empty hash
hashes_to_artificial_file();
$cmd = "php ../finish_course.php";
$output = qx($cmd);

if ($output !~ /Not all controls found/) {
  error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
}

#print $output;

$path = "./UnitTestingEvent/Competitors/$competitor_id";
if (! -f "$path/finish") {
  error_and_exit("$path/finish does not exist.");
}

@directory_contents = check_directory_contents($path, qw(name course start 0 finish dnf));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join(",", @directory_contents));
}


@file_contents_array = file_get_contents("$path/finish");
$time_now = time();
if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
  error_and_exit("File contents wrong, $path/finish: " . join(",", @file_contents_array) . " vs time_now of $time_now.");
}

if (! -f "$path/dnf") {
  error_and_exit("Early finish but the DNF file does not exist.");
}

success();
