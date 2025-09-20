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

sub add_uploaded_file {
  my($upload_entry_name, $local_filename, $number_entries) = @_;
  open(ARTIFICIAL_FILE, ">>./artificial_input");
  print ARTIFICIAL_FILE "FILES ${upload_entry_name} size ${number_entries}\n";
  print ARTIFICIAL_FILE "FILES ${upload_entry_name} tmp_name ${local_filename}\n";
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

sub set_email_properties {
  my($key) = @_;
  my($email_props_path) = get_base_path($key) . "/email_properties.txt";
  open(EMAIL_PROPS_FILE, ">$email_props_path");
  print EMAIL_PROPS_FILE "from: markandkaren" . "@" . "mkoconnell.com\n";
  print EMAIL_PROPS_FILE "reply-to: markandkaren" . "@" . "mkoconnell.com\n";
  print EMAIL_PROPS_FILE "subject: Results of NEOC Orienteering meet\n";
  print EMAIL_PROPS_FILE "extra-info: <p>Learn more about NEOC at <a href=\"www.newenglandorienteering.org\">our website</a>.\n";
  print EMAIL_PROPS_FILE "use_php_mail_function: true";
  close(EMAIL_PROPS_FILE);
}

sub set_email_properties_from_hash {
  my($key, %props) = @_;
  my($email_props_path) = get_base_path($key) . "/email_properties.txt";
  open(EMAIL_PROPS_FILE, ">$email_props_path");
  my($this_key);
  foreach $this_key (keys(%props)) {
    print EMAIL_PROPS_FILE "${this_key}:" . $props{$this_key} . "\n";
  }
  close(EMAIL_PROPS_FILE);
}

sub remove_email_properties {
  my($key) = @_;
  my($email_props_path) = get_base_path($key) . "/email_properties.txt";
  unlink($email_props_path);
}

sub set_using_nre_classes {
  my($key, $event) = @_;
  open(NRE_CLASS_FILE, ">" . get_base_path($key) . "/${event}/using_nre_classes");
  close(NRE_CLASS_FILE);
}

sub set_nre_classes {
  my($key) = @_;
  my($nre_class_file) = get_base_path($key) . "/default_classes.csv";
  qx(cp default_classes.csv $nre_class_file);
}

sub remove_nre_classes {
  my($key) = @_;
  my($nre_class_file) = get_base_path($key) . "/default_classes.csv";
  unlink($nre_class_file);
}

sub set_untimed_controls {
  my($key, $event, %course_untimed_controls) = @_;
  my($untimed_entries_file) = get_base_path($key) . "/${event}/untimed_controls";
  open(UNTIMED_FILE, ">$untimed_entries_file");
  my($this_key);
  foreach $this_key (keys(%course_untimed_controls)) {
    print UNTIMED_FILE "${this_key};" . $course_untimed_controls{$this_key} . "\n";
  }
  close(UNTIMED_FILE);
}

sub remove_untimed_controls {
  my($key, $event) = @_;
  my($untimed_entries_file) = get_base_path($key) . "/${event}/untimed_controls";
  unlink($untimed_entries_file);
}

sub set_default_timezone {
  my($key, $default_timezone) = @_;
  my($timezone_file) = get_base_path($key) . "/timezone.txt";
  # print "Creating file ${timezone_file} with contents ${default_timezone}.\n";
  open(TIMEZONE_FILE, ">$timezone_file");
  print TIMEZONE_FILE "$default_timezone";
  close (TIMEZONE_FILE);
}

sub set_club_name {
  my($key, $club_name) = @_;
  my(%member_props);
  %member_props = get_member_properties();
  $member_props{"club_name"} = $club_name;
  set_member_properties($key, %member_props);
}

sub unset_club_name {
  my($key) = @_;
  my(%member_props);
  %member_props = get_member_properties();
  delete $member_props{"club_name"};
  set_member_properties($key, %member_props);
}

sub get_member_properties {
  my($key) = @_;
  my($member_properties_path) = get_base_path($key) . "/member_properties.txt";

  open(MEMBER_PROPS_FILE, "<$member_properties_path");
  my(@property_lines);
  @property_lines = <MEMBER_PROPS_FILE>;
  close(MEMBER_PROPS_FILE);

  my(%properties);
  chomp(@property_lines);
  map { my($prop_key, $prop_value) = split(" : "); $properties{$prop_key} = $prop_value; } @property_lines;

  return(%properties);
}

sub set_member_properties {
  my($key, %properties) = @_;
  my($member_properties_path) = get_base_path($key) . "/member_properties.txt";
  open(MEMBER_PROPS_FILE, ">$member_properties_path");
  my($prop_key);
  foreach $prop_key (keys(%properties)) {
    print MEMBER_PROPS_FILE "${prop_key} : $properties{$prop_key}\n";
  }
  close(MEMBER_PROPS_FILE);
}

sub remove_member_properties {
  my($key) = @_;
  my($member_props_path) = get_base_path($key) . "/member_properties.txt";
  unlink($member_props_path);
}


my(%keys);
my(%xlt_keys);
sub create_key_file {
  $keys{"UnitTestPlayground"} = "TestingDirectory";
  $keys{"UnitTestAlternate"} = "NewDirectory_foo";
  $xlt_keys{"UnitTestXlt"} = "UnitTestPlayground";
  $xlt_keys{"UnitTestAltXlt"} = "UnitTestAlternate";
  #$xlt_keys{"UnitTestPlayground"} = "UnitTestKeyDoesNotExist";
  $xlt_keys{"UnitTestBad"} = "no_way_no_how";

  open(KEY_FILE, ">../keys");
  my($element);
  foreach $element (keys(%keys)) {
    print KEY_FILE join(",", $element, $keys{$element}, "no_password") . "\n";
  }
  foreach $element (keys(%xlt_keys)) {
    print KEY_FILE join(",", "XLT:" . $element, $xlt_keys{$element}) . "\n";
  }
  close(KEY_FILE);

}

sub remove_key_file {
  if ( -f "../keys") {
    unlink("../keys");
  }
  %keys = ();  # now an empty hash
}

# This too should be based on the key and the keyfile - leave it alone for now
sub get_base_path {
  my($key) = @_; 
  return("../OMeetData/" . $keys{$key});
}

sub initialize_event {
  # Make the event for testing purposes
  %{$post_ref} = qw(submit true event_name UnitTesting key UnitTestPlayground
                    course_description White,201,202,203,204,205--newline--Yellow,202,204,206,208,210--newline--s:ScoreO:300:1,301:10,302:20,303:30,304:40,305:50--newline--Butterfly,401,402,403,401,404,405,401,406,407--newline--s:GetEmAll:0:10,301:1,302:1,303:1,304:1,305:1--newline--Green,101--newline--Red,101--newline--Brown,101--newline--c:MotalaTestCourse,Butterfly,Green,Brown,Red--newline--d:201:Boulder--newline--d:202:Path and path junction--newline--d:203:Hill top--newline--d:204:Ruin southwest side--newline--d:205:Cliff 1m foot);
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

# This is not flexible, this should be based on the key file, but it is ok for the moment
sub set_no_redirects_for_event {
  my($event, $key) = @_;

  my($path) = get_base_path($key);

  open(NO_REDIRECTS, ">${path}/${event}/no_redirects"); close(NO_REDIRECTS);
}

#####################
# 
sub set_xlation_for_control {
  my($event, $key, $control, $xlation) = @_;

  my($path) = get_base_path($key);

  if (! -d "${path}/${event}/xlations") {
    mkdir("${path}/${event}/xlations");
  }

  open(XLATION_FILE, ">${path}/${event}/xlations/${control}"); print XLATION_FILE $xlation; close(XLATION_FILE);
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

########################
# Decode a registration info string back to the original hash
sub registration_info_string_to_hash {
  my($registration_info_string) = @_;

  my(%registration_hash);
  my(@registration_pieces) = split(",", $registration_info_string);
  my($i);

  for ($i = 0; $i < @registration_pieces; $i += 2) {
    $registration_hash{$registration_pieces[$i]} = decode_base64($registration_pieces[$i + 1]);
  }

  return(%registration_hash);
}

########################
# Create the classification information string
sub values_to_classification_info {
  my($birth_year, $gender, $preset_class) = @_;

  # For some reason, there is a newline at the end of the encoded string - strip them out
  my($classification_info) = "BY:" . encode_base64($birth_year) . ",G:" . encode_base64("GenderId:" . $gender) . ",CLASS:" . encode_base64($preset_class);
  $classification_info =~ s/\n//g;
  return($classification_info);
}

########################
# Parse a time field and return it in seconds
sub convert_to_seconds {
  my($time_string) = @_;
  my($time_in_seconds) = 0;

#  print "Converting time_string of $time_string.\n";

  if ($time_string =~ /^[0-9]+$/) {
    return ($time_string);
  }

  $time_string =~ s/^\s+//g;
  if ($time_string =~ /(^[0-9]+)h/) {
#    print "Found hours in $time_string, $1\n";
    $time_in_seconds += $1 * 3600;
    $time_string =~ s/^[0-9]+h//;
  }

  $time_string =~ s/^\s+//g;
  if ($time_string =~ /(^[0-9]+)m/) {
#    print "Found minutes in $time_string, $1\n";
    $time_in_seconds += $1 * 60;
    $time_string =~ s/^[0-9]+m//;
  }

  $time_string =~ s/^\s+//g;
  if ($time_string =~ /(^[0-9]+)s/) {
#    print "Found seconds in $time_string, $1\n";
    $time_in_seconds += $1;
    $time_string =~ s/^[0-9]+s//;
  }

  $time_string =~ s/^\s+//g;
  if ($time_string ne "") {
    return -1;
  }

  return ($time_in_seconds);
}

sub get_competitor_mass_start_time {
  my($key, $event_id, $competitor) = @_;
  my($mass_start_time) = file_get_contents(get_base_path($key) . "/${event_id}/Competitors/${competitor}/mass_si_stick_start");
  return($mass_start_time);
}

sub get_stick_xlation {
  my($key, $event_id, $stick) = @_;
  my($pathname) = get_base_path($key) . "/${event_id}/StickXlations/${stick}";

  if (-f $pathname) {
    return(file_get_contents($pathname));
  }
  else {
    return ("");
  }
}

1;
