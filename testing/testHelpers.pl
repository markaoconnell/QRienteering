#!/usr/bin/perl

use strict;
use MIME::Base64;

my($get_ref, $cookie_ref, $post_ref, $test_info_ref, $test_filename);

sub set_test_info {
  ($get_ref, $cookie_ref, $post_ref, $test_info_ref, $test_filename) = @_;
  print "\nRunning tests from $test_filename\n";
}

sub error_and_exit {
  my($error_string) = @_;
  print "ERROR: $error_string\n";
  my($entry);
  foreach $entry (sort(keys(%{$test_info_ref}))) {
    print "\tTESTINFO: $entry $test_info_ref->{$entry}\n";
  }
  print "\tFILENAME: $test_filename\n";
  exit(1);
}

sub success {
  if (defined($test_info_ref->{"Testname"})) {
    print "Test " . $test_info_ref->{"Testname"} . ": successful.\n";
  }
  else {
    print "Unknown test successful from $test_filename.\n";
  }
}

sub hashes_to_artificial_file {
  open(ARTIFICIAL_FILE, ">./artificial_input");
  my($entry);
  foreach $entry (keys(%{$get_ref})) {
    print ARTIFICIAL_FILE "GET $entry $get_ref->{$entry}\n";
  }
  foreach $entry (keys(%{$cookie_ref})) {
    print ARTIFICIAL_FILE "COOKIE $entry $cookie_ref->{$entry}\n";
  }
  foreach $entry (keys(%{$post_ref})) {
    print ARTIFICIAL_FILE "POST $entry $post_ref->{$entry}\n";
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

#print "Contents of $directory_path are: " . join(",", keys(%found_directory_contents)) . "\n";

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

sub get_score_course_properties {
  my($course_path) = @_;

  my(@file_contents) = file_get_contents("${course_path}/properties.txt");
  chomp(@file_contents);
  my(%props_hash);
  map { my($name,$value) = split(":"); $props_hash{$name} = $value; } @file_contents;

  return(%props_hash);
}

sub initialize_event {
  # Make the event for testing purposes
  %{$post_ref} = qw(submit true event_name UnitTesting
                    course_description White,201,202,203,204,205--newline--Yellow,202,204,206,208,210--newline--s:ScoreO:300:1,301:10,302:20,303:30,304:40,305:50);
#  mkdir("UnitTestingEvent");
#  mkdir("UnitTestingEvent/Competitors");
#  mkdir("UnitTestingEvent/Results");
#  mkdir("UnitTestingEvent/Courses");
#  open(NO_REDIRECTS, ">./UnitTestingEvent/no_redirects"); close(NO_REDIRECTS);
#  mkdir("UnitTestingEvent/Courses/01-White");
#  mkdir("UnitTestingEvent/Courses/02-Yellow");
#  open(WHITE, ">./UnitTestingEvent/Courses/01-White/controls.txt");
#  print WHITE "201\n202\n203\n204\n205";
#  close (WHITE);
#  open(YELLOW, ">./UnitTestingEvent/Courses/02-Yellow/controls.txt");
#  print YELLOW "202\n204\n206\n208\n210";
#  close(YELLOW);
}

sub set_no_redirects_for_event {
  my($event) = @_;

  open(NO_REDIRECTS, ">./${event}/no_redirects"); close(NO_REDIRECTS);
}

########################
# Turn a hash into the information for the registration script
sub hash_to_registration_info_string {
  my($info_hash_ref) = @_;

  my($info_return);
  $info_return = join(",", map { join(",", $_, encode_base64($info_hash_ref->{$_})) } keys(%{$info_hash_ref}));
  $info_return =~ s/\n//g;  # For some reason, newlines are being embedded at the end of the base64 encodes
  #print "String is $info_return\n";

  return($info_return);
}

1;
