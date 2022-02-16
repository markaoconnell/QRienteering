#!/usr/bin/perl

use strict;

require "testHelpers.pl";

my($cmd, $output, $output2, $competitor_id, $path, $time_now);
my(@file_contents_array);
my(@directory_contents);


###########
sub reach_control_ok {
  my($duplicate, $score_course, $control_num_on_course, $get_ref, $cookie_ref, $test_info_ref) = @_;

  $competitor_id = $cookie_ref->{"competitor_id"};
  $test_info_ref->{"subroutine"} = "reach_control";

  $path = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Competitors/$competitor_id";
  my($extra_file_present) = ( -f "${path}/extra" );
  my($course) = $cookie_ref->{"course"};
  $course =~ s/^[0-9]+-//;

  hashes_to_artificial_file();
  $cmd = "php ../OMeet/reach_control.php";
  $output = qx($cmd);

  my($control) = $get_ref->{"control"};
  if ($control eq "") {
    # When testing with passing the control via mumble, also pass the control
    # via the test info rather than base64 decoding the mumble and getting it
    # from there.
    $control = $test_info_ref->{"control"};
  }
  my($control_num_for_print_string) = $control_num_on_course + 1;
  
  if ($score_course) {
    if ($output !~ /Reached $control on $course, earned [0-9]+ points/) {
      error_and_exit("Web page output wrong, correct control string not found for score course (duplicate=$duplicate).\n$output");
    }
  }
  elsif ($duplicate) {
    if ($output !~ /Control $control correct but already scanned.<p>Control #$control_num_for_print_string/) {
      error_and_exit("Web page output wrong, correct control string not found for duplicate control.\n$output");
    }
  }
  else {
    if ($output !~ /Correct!  Reached $control, control #$control_num_for_print_string/) {
      error_and_exit("Web page output wrong, correct control string not found.\n$output");
    }
  }
  
  #print $output;
  
  my(@controls_found) = check_directory_contents("$path/controls_found", qw(start));

  if (grep (/NOTFOUND/, @controls_found) || grep (!/^[0-9]+,[0-9a-f]+$/)) {
    error_and_exit("$path/controls_found holds incorrect items, " .
                   "\n\tFound: " . join("--", @controls_found));
  }

  if ($#controls_found != $control_num_on_course) {
    my(%dedupe_hash);
    map { my($timestamp, $control) = split(",", $_); $dedupe_hash{$control} = 1; } @controls_found;
    my(@deduped_controls_found) = keys(%dedupe_hash);
 
    # For a ScoreO course, adding a duplicate control can sometimes show up as an extra entry, so check the de-duped version
    if (($#deduped_controls_found != $control_num_on_course) || !$score_course) {
      error_and_exit("$path/controls_found hold wrong number of controls, found $#controls_found, expected $control_num_on_course, " .
                     "\n\tFound: " . join("--", @controls_found));
    }
  }

  my(@sorted_controls_found) = sort { $a cmp $b } @controls_found;
  my($time_at_control);
  if ($sorted_controls_found[$#sorted_controls_found] !~ m#^([0-9]+),$control$#) {
    error_and_exit("Last control found: " . $sorted_controls_found[$#sorted_controls_found] . " does not match expected control: $control.\n");
  }
  $time_at_control = $1;
  
  # If the extra file was there already, it is ok if it is there now, it just shouldn't be added
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) ||
      (grep(/extra/, @directory_contents) && !$extra_file_present) || grep(/dnf/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  $time_now = time();
  if (($time_now - $time_at_control) > 5) {
    error_and_exit("Control file wrong, " . $sorted_controls_found[$#sorted_controls_found] . " has time $time_at_control vs time_now of $time_now.");
  }

  delete($test_info_ref->{"subroutine"});
}

###########
sub reach_control_with_skip {
  my($control_num_on_course, $get_ref, $cookie_ref, $test_info_ref) = @_;

  $competitor_id = $cookie_ref->{"competitor_id"};
  $test_info_ref->{"subroutine"} = "reach_control";

  $path = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Competitors/$competitor_id";
  my($course) = $cookie_ref->{"course"};
  $course =~ s/^[0-9]+-//;

  hashes_to_artificial_file();
  $cmd = "php ../OMeet/reach_control.php";
  $output = qx($cmd);

  my($control) = $get_ref->{"control"};
  if ($control eq "") {
    # When testing with passing the control via mumble, also pass the control
    # via the test info rather than base64 decoding the mumble and getting it
    # from there.
    $control = $test_info_ref->{"control"};
  }
  my($control_num_for_print_string) = $control_num_on_course + 1;
  
  if ($output !~ /Correct!  Reached $control, control #$control_num_for_print_string/) {
    error_and_exit("Web page output wrong, correct control string not found.\n$output");
  }
  
  #print $output;
  
  my(@controls_found) = check_directory_contents("$path/controls_found", qw(start));

  if (grep (/NOTFOUND/, @controls_found) || grep (!/^[0-9]+,[0-9a-f]+$/)) {
    error_and_exit("$path/controls_found holds incorrect items, " .
                   "\n\tFound: " . join("--", @controls_found));
  }

  my(@skipped_controls);
  @skipped_controls = split(",", $cookie_ref->{"${competitor_id}_skipped_controls"});
  #print "Found " . join("--", @skipped_controls) . " skipped controls from cookie.\n";

  # controls_found is prior controls found, plus the number of skipped controls, plus this one (which goes into extra)
  if (($#controls_found + scalar(@skipped_controls) + 1) != $control_num_on_course) {
    my(%dedupe_hash);
    map { my($timestamp, $control) = split(",", $_); $dedupe_hash{$control} = 1; } @controls_found;
    my(@deduped_controls_found) = keys(%dedupe_hash);
 
    # For a ScoreO course, adding a duplicate control can sometimes show up as an extra entry, so check the de-duped version
    if ($#deduped_controls_found != $control_num_on_course) {
      error_and_exit("$path/controls_found hold wrong number of controls, found $#controls_found + " . scalar(@skipped_controls) .
                     " + 1, expected $control_num_on_course,\n\tFound: " . join("--", @controls_found));
    }
  }

  my(@extra_controls_array);
  @extra_controls_array = file_get_contents("$path/extra");

  my($time_at_control);
  if ($extra_controls_array[$#extra_controls_array] !~ m#^([0-9]+),$control$#) {
    error_and_exit("Last control found: " . $extra_controls_array[$#extra_controls_array] . " does not match expected control: $control.\n");
  }
  $time_at_control = $1;
  
  # With a skipped control, extra should be there
  @directory_contents = check_directory_contents($path, qw(name course controls_found extra));
  if (grep(/NOTFOUND/, @directory_contents) || grep(/finish/, @directory_contents) || grep(/dnf/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  $time_now = time();
  if (($time_now - $time_at_control) > 5) {
    error_and_exit("Control file wrong, " . $extra_controls_array[$#extra_controls_array] . " has time $time_at_control vs time_now of $time_now.");
  }

  delete($test_info_ref->{"subroutine"});
}

############
sub reach_control_successfully {
  my($control_num_on_course, $get_ref, $cookie_ref, $test_info_ref) = @_;
  reach_control_ok(0, 0, $control_num_on_course, $get_ref, $cookie_ref, $test_info_ref);
}

############
sub reach_control_again {
  my($control_num_on_course, $get_ref, $cookie_ref, $test_info_ref) = @_;
  reach_control_ok(1, 0, $control_num_on_course, $get_ref, $cookie_ref, $test_info_ref);
}


############
sub reach_score_control_successfully {
  my($control_num_on_course, $get_ref, $cookie_ref, $test_info_ref) = @_;
  reach_control_ok(0, 1, $control_num_on_course, $get_ref, $cookie_ref, $test_info_ref);
}

############
sub reach_score_control_again {
  my($control_num_on_course, $get_ref, $cookie_ref, $test_info_ref) = @_;
  reach_control_ok(1, 1, $control_num_on_course, $get_ref, $cookie_ref, $test_info_ref);
}


###########
# Successfully register a new competitor
sub register_successfully {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "register_successfully";
  $cookie_ref->{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
  hashes_to_artificial_file();
  $cmd = "php ../OMeetRegistration/register_competitor.php";
  $output = qx($cmd);

  my($course) = $get_ref->{"course"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;
  my($competitor_name) = $get_ref->{"competitor_name"};

  if ($output !~ /Registration complete: $competitor_name on ${readable_course_name}/) {
    error_and_exit("Web page output wrong, registration complete string not found.\n$output");
  }
  
  if ($output !~ /\#\#\#\#,RESULT,Registered $competitor_name on ${readable_course_name}/) {
    error_and_exit("Did not see parseable registration result:\n$output");
  }

  #print $output;
  
  my($competitor_id);
  my($ls_cmd);
  my($event_path);
  $event_path = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"};
  $ls_cmd = "ls -1t ${event_path}/Competitors | head -n 1";
  #$competitor_id = qx(ls -1t ./UnitTestingEvent/Competitors | head -n 1);
  $competitor_id = qx($ls_cmd);
  chomp($competitor_id);
  print "My competitor_id is $competitor_id\n";
  if (! -d "${event_path}/Competitors/$competitor_id") {
    error_and_exit("Directory ${event_path}/Competitors/$competitor_id not found.");
  }
  
  $path = "${event_path}/Competitors/$competitor_id";
  if ((! -f "$path/name") || (! -f "$path/course")) {
    error_and_exit("One of $path/name or $path/course does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("$path/controls_found", qw());
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path/controls_found than expected: " . join("--", @directory_contents));
  }
  
  my(@name_file) = file_get_contents("$path/name");
  my(@course_file) = file_get_contents("$path/course");
  
  if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne $competitor_name) || ($course_file[0] ne $course)) {
    error_and_exit("File contents wrong, name_file: " . join("--", @name_file) . "\n\tcourse_file: " . join("--" , @course_file));
  }

  delete($test_info_ref->{"subroutine"});
  $test_info_ref->{"competitor_id"} = $competitor_id;
}


###########
# Successfully register a new competitor
sub register_member_successfully {
  my($get_ref, $cookie_ref, $registration_info_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "register_member_successfully";
  my($raw_registration_info) = hash_to_registration_info_string($registration_info_ref);
  $get_ref->{"registration_info"} = $raw_registration_info;
  $cookie_ref->{"testing_cookie_support"} = "can--space--this--space--be--space--read?";
  hashes_to_artificial_file();
  $cmd = "php ../OMeetRegistration/register_competitor.php";
  $output = qx($cmd);

  my($course) = $get_ref->{"course"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;
  my($competitor_name) = $get_ref->{"competitor_name"};
  $competitor_name =~ s/--space--/ /g;

  if ($output !~ /Registration complete: $competitor_name on ${readable_course_name}/) {
    error_and_exit("Web page output wrong, registration complete string not found.\n$output");
  }

  if ($output !~ /\#\#\#\#,RESULT,Registered $competitor_name on ${readable_course_name}/) {
    error_and_exit("Did not see parseable registration result:\n$output");
  }
  
  #print $output;
  
  my($competitor_id);
  my($ls_cmd);
  my($event_path);
  $event_path = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"};
  $ls_cmd = "ls -1t ${event_path}/Competitors | head -n 1";
  #$competitor_id = qx(ls -1t ./UnitTestingEvent/Competitors | head -n 1);
  $competitor_id = qx($ls_cmd);

  chomp($competitor_id);
  print "My competitor_id is $competitor_id\n";
  if (! -d "${event_path}/Competitors/$competitor_id") {
    error_and_exit("Directory ${event_path}/Competitors/$competitor_id not found.");
  }
  
  $path = "${event_path}/Competitors/$competitor_id";
  if ((! -f "$path/name") || (! -f "$path/course")) {
    error_and_exit("One of $path/name or $path/course does not exist.");
  }
  
  if ($registration_info_ref->{"si_stick"} ne "") {
    @directory_contents = check_directory_contents($path, qw(name course controls_found registration_info si_stick));
  }
  else {
    @directory_contents = check_directory_contents($path, qw(name course controls_found registration_info));
  }
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("$path/controls_found", qw());
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path/controls_found than expected: " . join("--", @directory_contents));
  }
  
  my(@name_file) = file_get_contents("$path/name");
  my(@course_file) = file_get_contents("$path/course");
  
  if (($#name_file != 0) || ($#course_file != 0) || ($name_file[0] ne $competitor_name) || ($course_file[0] ne $course)) {
    error_and_exit("File contents wrong, name_file: " . join("--", @name_file) . "\n\tcourse_file: " . join("--" , @course_file));
  }

  my(@registration_info_contents) = file_get_contents("$path/registration_info");
  if ((scalar(@registration_info_contents) != 1) || ($registration_info_contents[0] ne $raw_registration_info)) {
    error_and_exit("File contents wrong, registration_info: " . join("--", @registration_info_contents));
  }

  if ($registration_info_ref->{"si_stick"} ne "") {
    my(@stick_file_contents) = file_get_contents("$path/si_stick");
    if ((scalar(@stick_file_contents) != 1) || ($stick_file_contents[0] ne $registration_info_ref->{"si_stick"})) {
      error_and_exit("File contents wrong, stick_file_contents: " . join("--", @stick_file_contents));
    }
  }

  delete($test_info_ref->{"subroutine"});
  $test_info_ref->{"competitor_id"} = $competitor_id;
}




###########
# Success start the course
sub start_successfully {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "start_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/start_course.php";
  $output = qx($cmd);
  
  my($competitor_name);
  my($course) = $cookie_ref->{"course"};
  my($competitor_id) = $cookie_ref->{"competitor_id"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;
  
  if ($output !~ /${readable_course_name} course started for/) {
    error_and_exit("Web page output wrong, course start string not found.\n$output");
  }
  
  #print $output;
  
  $path = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Competitors/$competitor_id";
  if (! -f "$path/controls_found/start") {
    error_and_exit("$path/controls_found/start does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("$path/controls_found", qw(start));
  if ($#directory_contents != -1) {
    error_and_exit("More files exist in $path/controls_found than expected: " . join("--", @directory_contents));
  }
  
  
  @file_contents_array = file_get_contents("$path/controls_found/start");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, start_time_file: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  }

  delete($test_info_ref->{"subroutine"});
}




###########
# Finish the course successfully
sub finish_successfully {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/finish_course.php";
  $output = qx($cmd);
  
  my($course) = $cookie_ref->{"course"};
  my($competitor_id) = $cookie_ref->{"competitor_id"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output =~ /ERROR: DNF status/) || ($output !~ /course complete.*, time taken/) || ($output !~ /Results on ${readable_course_name}/)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }

  if ($output !~ /\#\#\#\#,RESULT,.*,${readable_course_name},[0-9]+/) {
    error_and_exit("Did not see parseable finish entry:\n$output");
  }
  
  #print $output;
  
  $path = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Competitors/$competitor_id";
  my($controls_found_path) = "$path/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  # The only other files in the directory should be the numeric files for the controls found
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  # The points for the course based should be equal to the number of controls for a linear course
  my($number_controls_on_course) = scalar(@directory_contents);
  
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $controls_found_path/finish: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  }
  
  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", 0, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my($results_dir) = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Results/${course}";
  my(@results_array) = check_directory_contents($results_dir, $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});
}


###########
# Finish the course successfully
sub finish_with_stick_successfully {
  my($competitor_id, $course, $get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_with_stick_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/finish_course.php";
  $output = qx($cmd);
  
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output =~ /ERROR: DNF status/) || ($output !~ /course complete.*, time taken/) || ($output !~ /Results on ${readable_course_name}/)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }
  
  if ($output !~ /\#\#\#\#,RESULT,.*,${readable_course_name},[0-9]+/) {
    error_and_exit("Did not see parseable finish entry:\n$output");
  }
  
  #print $output;

  if ($output =~ /(Mail:.*)/) {
    print "Found $1\n";
  }
  
  $path = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Competitors/$competitor_id";
  my($controls_found_path) = "$path/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  # The only other files in the directory should be the numeric files for the controls found
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");
  #$time_now = time();
  #if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    #error_and_exit("File contents wrong, $controls_found_path/finish: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  #}
  
  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", 0, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my($results_dir) = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Results/${course}";
  my(@results_array) = check_directory_contents($results_dir, $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});
}


###########
# Finish the course successfully
sub finish_score_successfully {
  my($expected_points, $get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_score_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/finish_course.php";
  $output = qx($cmd);
  
  my($course) = $cookie_ref->{"course"};
  my($competitor_id) = $cookie_ref->{"competitor_id"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output =~ /ERROR: DNF status/) || ($output !~ /course complete.*, time taken/) || ($output !~ /Results on ${readable_course_name}/) ||
      ($output !~ m#<td>$expected_points</td>#)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }
  
  if ($output !~ /\#\#\#\#,RESULT,.*,${readable_course_name},[0-9]+/) {
    error_and_exit("Did not see parseable finish entry:\n$output");
  }
  
  #print $output;
  
  $path = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Competitors/$competitor_id";
  my($controls_found_path) = "$path/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  # The only other files in the directory should be the numeric files for the controls found
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $controls_found_path/finish: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  }

  my(%props_hash) = get_score_course_properties(get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Courses/${course}");
  
  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", $props_hash{"max"} - $expected_points, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my($results_dir) = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Results/${course}";
  my(@results_array) = check_directory_contents($results_dir, $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});

  return($output);
}


###########
# Finish the course successfully
sub finish_scoreO_with_stick_successfully {
  my($competitor_id, $course, $expected_points, $get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_scoreO_with_stick_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/finish_course.php";
  $output = qx($cmd);
  
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output =~ /ERROR: DNF status/) || ($output !~ /course complete.*, time taken/) || ($output !~ /Results on ${readable_course_name}/) ||
      ($output !~ m#<td>$expected_points</td>#)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }
  
  if ($output !~ /\#\#\#\#,RESULT,.*,${readable_course_name},[0-9]+/) {
    error_and_exit("Did not see parseable finish entry:\n$output");
  }
  
  #print $output;
  
  if ($output =~ /(Mail:.*)/) {
    print "Found $1\n";
  }
  
  $path = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Competitors/$competitor_id";
  my($controls_found_path) = "$path/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  # The only other files in the directory should be the numeric files for the controls found
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");

  my(%props_hash) = get_score_course_properties(get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Courses/${course}");
  
  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", $props_hash{"max"} - $expected_points, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my($results_dir) = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Results/${course}";
  my(@results_array) = check_directory_contents($results_dir, $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});

  return($output);
}



###########
# Finish the course with a DNF
sub finish_with_dnf {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_with_dnf";
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/finish_course.php";
  $output = qx($cmd);
  
  my($course) = $cookie_ref->{"course"};
  my($competitor_id) = $cookie_ref->{"competitor_id"};
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output !~ /DNF/) || ($output !~ /course complete.*DNF.*, time taken/) || ($output !~ /Results on ${readable_course_name}/)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }

  if ($output !~ /\#\#\#\#,RESULT,.*,${readable_course_name},[0-9]+/) {
    error_and_exit("Did not see parseable finish entry:\n$output");
  }

  if ($output !~ /\#\#\#\#,ERROR,DNF/) {
    error_and_exit("Did not see parseable DNF error\n$output");
  }
  
  
  #print $output;
  
  $path = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Competitors/$competitor_id";
  my($controls_found_path) = "${path}/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found dnf));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  my($number_controls_found_on_course) = scalar(@directory_contents);

  @file_contents_array = file_get_contents(get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Courses/${course}/controls.txt");
  my($number_controls_on_course) = scalar(@file_contents_array);
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");
  $time_now = time();
  if (($#file_contents_array != 0) || (($time_now - $file_contents_array[0]) > 5)) {
    error_and_exit("File contents wrong, $controls_found_path/finish: " . join("--", @file_contents_array) . " vs time_now of $time_now.");
  }
  
  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", $number_controls_on_course - $number_controls_found_on_course, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my($results_dir) = get_base_path($cookie_ref->{"key"}) . "/" . $cookie_ref->{"event"} . "/Results/${course}";
  my(@results_array) = check_directory_contents($results_dir, $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});

  return ($output);
}

###########
# Finish the course with a DNF
sub finish_with_stick_dnf {
  my($competitor_id, $course, $get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "finish_with_stick_dnf";
  hashes_to_artificial_file();
  $cmd = "php ../OMeet/finish_course.php";
  $output = qx($cmd);
  
  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;

  if (($output !~ /DNF/) || ($output !~ /course complete.*DNF.*, time taken/) || ($output !~ /Results on ${readable_course_name}/)) {
    error_and_exit("Web page output wrong, not all controls entry not found.\n$output");
  }
  
  if ($output !~ /\#\#\#\#,RESULT,.*,${readable_course_name},[0-9]+/) {
    error_and_exit("Did not see parseable finish entry:\n$output");
  }

  if ($output !~ /\#\#\#\#,ERROR,DNF/) {
    error_and_exit("Did not see parseable DNF error\n$output");
  }
  
  #print $output;
  
  if ($output =~ /(Mail:.*)/) {
    print "Found $1\n";
  }
  
  $path = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Competitors/$competitor_id";
  my($controls_found_path) = "$path/controls_found";
  if (! -f "$controls_found_path/finish") {
    error_and_exit("$controls_found_path/finish does not exist.");
  }
  
  @directory_contents = check_directory_contents($path, qw(name course controls_found dnf));
  if (grep(/NOTFOUND/, @directory_contents)) {
    error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
  }
  
  # The only other files in the directory should be the numeric files for the controls found
  @directory_contents = check_directory_contents($controls_found_path, qw(start finish));
  if (grep(!/^[0-9]+,[0-9a-f]+$/, @directory_contents)) {
    error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
  }
  
  my($number_controls_found_on_course) = scalar(@directory_contents);

  @file_contents_array = file_get_contents(get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Courses/${course}/controls.txt");
  my($number_controls_on_course) = scalar(@file_contents_array);
  
  @file_contents_array = file_get_contents("$controls_found_path/finish");

  my(@start_time_array) = file_get_contents("$controls_found_path/start");
  my($results_file) = sprintf("%04d,%06d,%s", $number_controls_on_course - $number_controls_found_on_course, (int($file_contents_array[0]) - int($start_time_array[0])), $competitor_id);
  
  
  my($results_dir) = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Results/${course}";
  my(@results_array) = check_directory_contents($results_dir, $results_file);
  if (grep(/NOTFOUND:$results_file/, @results_array)) {
    error_and_exit("No results file ($results_file) found, contents are: " . join("--", @results_array));
  }
  
  delete($test_info_ref->{"subroutine"});
}

###########
# Use the web interface to create an event
sub create_event_successfully {
  my($get_ref, $cookie_ref, $post_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "create_event_successfully";
  hashes_to_artificial_file();
  $cmd = "php ../OMeetMgmt/create_event.php";
  $output = qx($cmd);
  
  if ($output !~ /Created event successfully/) {
    error_and_exit("Web page output wrong, no message about successful event creation.\n$output");
  }


  my($ls_cmd);
  my($event_id);
  $ls_cmd = "ls -1t " . get_base_path($post_ref->{"key"}) . " | head -n 1"; 
  $event_id = qx($ls_cmd);
  chomp($event_id);
  print "New event id is $event_id\n";

  my($event_path) = get_base_path($post_ref->{"key"}) . "/${event_id}";
  # Validate proper directories exist
  if (! -d $event_path) {
    error_and_exit("Proper directory for $event_path not found.\n");
  }

  if (! -f "$event_path/description") {
    error_and_exit("Description file for $event_path not found.\n");
  }

  my($event_description) = file_get_contents("${event_path}/description");
  if ($event_description ne $post_ref->{"event_name"}) {
    error_and_exit("Event description \"${event_description}\" does match input of \"" . $post_ref->{"event_name"} . "\"\n");
  }

  my($number_courses);
  $number_courses = () = $post_ref->{"course_description"} =~ m/--newline--[^-]/g;
  if ($post_ref->{"course_description"} =~ m/^--/) {
    # The regexp will miss the case of the first line being a comment and not a course
    $number_courses--;
  }
  $number_courses++;   # There is normally one fewer newline than the number of courses
  
  @directory_contents = check_directory_contents($event_path, qw(description Competitors Results Courses));
  if (scalar(@directory_contents) != 0) {
    error_and_exit("More files exist in $event_path than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("${event_path}/Competitors", qw());
  if (scalar(@directory_contents) != 0) {
    error_and_exit("More files exist in ${event_path}/Competitors than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("${event_path}/Results", qw());
  if (scalar(@directory_contents) != $number_courses) {
    error_and_exit("Different number of files exist in ${event_path}/Results than expected: " . join("--", @directory_contents));
  }
  
  @directory_contents = check_directory_contents("${event_path}/Courses", qw());
  if (scalar(@directory_contents) != $number_courses) {
    error_and_exit("Different number of files exist in ${event_path}/Courses than expected: " . join("--", @directory_contents));
  }

  # Validate that the course properties are set correctly
  my($course_description) = $post_ref->{"course_description"};
  $course_description =~ s/--newline--/\n/g;
  my(@courses) = split("\n", $course_description);
  my($this_course, $i);
  $i = 0;
  foreach $this_course (@courses) {
    next if (($this_course =~ /^--/) || ($this_course eq ""));

    my($course_name_field) = split(",", $this_course);
    my(@course_elements) = split(":", $course_name_field);
    my($course_name);
    if (($course_elements[0] eq "l") || ($course_elements[0] eq "s")) {
      $course_name = sprintf("%02d-%s", $i, $course_elements[1]);
    }
    else {
      $course_name = sprintf("%02d-%s", $i, $course_elements[0]);
    }

    if ($course_name_field !~ /^s:/) {
      if ( -f "${event_path}/Courses/${course_name}/properties.txt") {
        error_and_exit("Found ${event_path}/Courses/${course_name}/properties.txt unexpectedly.");
      }

      if (! -d "${event_path}/Courses/${course_name}") {
        error_and_exit("Course directory ${event_path}/Courses/${course_name} does not exist when it should.");
      }
    }
    else {
      if (! -f "${event_path}/Courses/${course_name}/properties.txt") {
        error_and_exit("Did not find ${event_path}/Courses/${course_name}/properties.txt when it should be there.");
      }
      my(%props_hash) = get_score_course_properties("${event_path}/Courses/${course_name}");
      if ($course_elements[3] ne $props_hash{"penalty"}) {
        error_and_exit("Properties mismatch: " . $props_hash{"penalty"} . " derived, $course_elements[2] supplied.\n");
      }

      my($parse_result) = convert_to_seconds($course_elements[2]);
      if ($parse_result != $props_hash{"limit"}) {
        error_and_exit("Properties mismatch: time limit of $course_elements[2] ($parse_result) does not match value in seconds " . $props_hash{"limit"} . ".\n");
      }
    }

    $i++;
  }
  
  
  delete($test_info_ref->{"subroutine"});
  $test_info_ref->{"event_id"} = $event_id;

  return ($output);
}


###########
# Use the web interface to create an event
sub create_event_fail {
  my($expected_error_msg, $get_ref, $cookie_ref, $post_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "create_event_fail";
  hashes_to_artificial_file();
  $cmd = "php ../OMeetMgmt/create_event.php";
  $output = qx($cmd);
  
  if ($output =~ /Created event successfully/) {
    error_and_exit("Web page output wrong, found a message about successful event creation unexpectedly.\n$output");
  }

  if ($output !~ /$expected_error_msg/) {
    error_and_exit("Web page output wrong, expected error message not found.\n$output");
  }

  my($event) = get_base_path($post_ref->{"key"}) . "/" . $post_ref->{"event_name"} . "Event";

  # Validate proper directories exist
  if ( -d $event) {
    error_and_exit("Proper directory for $event was created unexpectedly.\n");
  }

  
  delete($test_info_ref->{"subroutine"});

  return ($output);
}



###########
# Remove the course from the list of available courses
sub remove_course {
  my($get_ref, $cookie_ref, $test_info_ref) = @_;

  $test_info_ref->{"subroutine"} = "remove_course";
  $get_ref->{"submit"} = "yes";
  hashes_to_artificial_file();
  $cmd = "php ../OMeetMgmt/remove_course_from_event.php";
  my($output);
  $output = qx($cmd);
  
  my($course) = "";
  my($candidate_course_in_get);
  foreach $candidate_course_in_get (keys(%{$get_ref})) {
    if ($candidate_course_in_get =~ /^remove:(.*)/) {
      $course = $1;
#      print "Found course to remove ${course}\n";
      last;
    }
  }

  my($readable_course_name) = $course;
  $readable_course_name =~ s/^[0-9]+-//;
  
  if ($output !~ /Course ${readable_course_name} is no longer valid/) {
    error_and_exit("Web page output wrong, course removal string not found.\n$output");
  }
  
  #print $output;
  
  my($remove_marker_file);
  $remove_marker_file = get_base_path($get_ref->{"key"}) . "/" . $get_ref->{"event"} . "/Courses/" . $course . "/removed";
  if (! -f "$remove_marker_file") {
    error_and_exit("${remove_marker_file} does not exist.");
  }
  
  delete($test_info_ref->{"subroutine"});
}



1;
