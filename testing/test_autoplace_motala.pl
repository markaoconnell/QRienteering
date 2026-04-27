#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST, %REGISTRATION_INFO);
my($COMPETITOR_1) = "Mark_OConnell--space-----space--2";
my($COMPETITOR_2) = "Karen_Yeowell--space--+2";   # +3 HTML encoded
my($COMPETITOR_3) = "LydBid--space--(2)";       # (2) HTML encoded
my($COMPETITOR_4) = "JohnnyJohnCon--space--3";
my($COMPETITOR_5) = "LinaNowak";
my($COMPETITOR_6) = "RoxyAndTheGemstoneKitties";
my($COMPETITOR_1_RE) = "Mark_OConnell - 2";
my($COMPETITOR_2_RE) = "Karen_Yeowell \\+2";   # +3 HTML encoded
my($COMPETITOR_3_RE) = "LydBid \\(2\\)";       # (2) HTML encoded
my($COMPETITOR_4_RE) = "JohnnyJohnCon 3";
my($COMPETITOR_5_RE) = "LinaNowak";
my($COMPETITOR_6_RE) = "RoxyAndTheGemstoneKitties";
my($competitor_1_id, $competitor_2_id, $competitor_3_id, $competitor_4_id, $competitor_5_id, $competitor_6_id, $competitor_7_id, $competitor_8_id);

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
create_key_file();
initialize_event();
$POST{"course_description"} .= "--newline--l:F1S1,401,103,104--newline--l:F1S2,401,102,104--newline--l:F1S3,401,102,103--newline--l:F2S1,501,103,104--newline--l:F2S2,501,102,104--newline--l:F2S3,501,102,103--newline--l:F1All,401,102,103,104--newline--l:F2All,501,102,103,104--newline--c:AutoMotala,F1S1,F1S2,F1S3,F2S1,F2S2,F2S3,F1All,F2All";
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

sub check_results {
  return(check_results_inner("UnitTestPlayground", @_));
}

sub check_results_xlt {
  return(check_results_inner("UnitTestXlt", @_));
}

sub check_results_inner {
  my($key, $expected_table_rows) = @_;

  $expected_table_rows *= 2;  # For a Motala for this event, all results should appear normally AND as part of the motala

  %GET = qw(key UnitTestPlayground);  # This clears the GET array, and then the key is overridden
  $GET{"key"} = $key;
  #$GET{"event"} = $event_id;   # Should only be one event during this test...
                                #  Specifying the event disables the key translation
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

  if ($output !~ /\#\#\#\#,CourseList,00-White,01-Yellow,02-ScoreO,03-Butterfly,04-GetEmAll,05-Green,06-Red,07-Brown,17-AutoMotala/) {

    error_and_exit("Did not find expected course list in results output.\n$output");
  }

  return ($output);
}

sub check_on_course {
  return(check_on_course_inner("UnitTestPlayground", @_));
}

sub check_on_course_xlt {
  return(check_on_course_inner("UnitTestXlt", @_));
}

sub check_on_course_inner {
  my($key, $expected_table_rows) = @_;

  %GET = qw(key UnitTestPlayground);
  $GET{"key"} = $key;
  #$GET{"event"} = $event_id;   # There should only be one event during this test...
                                #  Specifying the event disables the key translation
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
  return(check_competitor_on_course_inner("UnitTestPlayground", @_));
}

sub check_competitor_on_course_xlt {
  return(check_competitor_on_course_inner("UnitTestXlt", @_));
}

sub check_competitor_on_course_inner {
  my($key, $competitor_name, $competitor_id) = @_;

  %GET = qw(key UnitTestPlayground include_competitor_id 1);
  $GET{"key"} = $key;
  #$GET{"event"} = $event_id;     # See comments earlier - there should only be one event for this test to work
  %COOKIE = ();
  hashes_to_artificial_file();

  my($cmd) = "php ../OMeet/on_course.php";
  my($output);
  $output = qx($cmd);

  $competitor_name =~ s/--space--/ /g;
  my($competitor_name_for_match) = $competitor_name;
  $competitor_name_for_match =~ s/\+/\\+/g;
  $competitor_name_for_match =~ s/\(/\\(/g;
  $competitor_name_for_match =~ s/\)/\\)/g;

  if ($output !~ /$competitor_name_for_match \($competitor_id\)/) {
    error_and_exit("Name and id - $competitor_name and $competitor_id - not found in on_course output.\n$output");
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
# Test 1 - Confirm autoplacement can be set
# Confirm current course status
%TEST_INFO = qw(Testname SetAutoPlacement);
my(@subcourse_list) = qw(09-F1S1 10-F1S2 11-F1S3 12-F2S1 13-F2S2 14-F2S3 15-F1All 16-F2All);
my($autoplace_course) = "17-AutoMotala";
my($event_course_path) = get_base_path("UnitTestPlayground") . "/${event_id}/Courses/";
my($output);

# Confirm that the autoplace course is not open for registration while the subcourses are
if (! -f "${event_course_path}/${autoplace_course}/no_registrations") {
    error_and_exit("File ${event_course_path}/${autoplace_course}/no_registrations does not exist when it should.");
}

my($subcourse);
foreach $subcourse (@subcourse_list) {
  if (-f "${event_course_path}/${subcourse}/no_registrations") {
      error_and_exit("File ${event_course_path}/${subcourse}/no_registrations exist when it should not.");
  }
}

%GET = qw(key UnitTestPlayground submit true autoplace:17-AutoMotala 1);
$GET{"event"} = ${event_id};
foreach $subcourse (@subcourse_list) {
  $GET{"disable:${subcourse}"} = 1;
}

my($cmd) = "php ../OMeetMgmt/manage_course_registration.php";
hashes_to_artificial_file();
$output = qx($cmd);

# Confirm that the autoplace course is now open for registration while the subcourses aren't
if (-f "${event_course_path}/${autoplace_course}/no_registrations") {
    error_and_exit("File ${event_course_path}/${autoplace_course}/no_registrations exist when it should not.");
}

my($subcourse);
foreach $subcourse (@subcourse_list) {
  if (! -f "${event_course_path}/${subcourse}/no_registrations") {
      error_and_exit("File ${event_course_path}/${subcourse}/no_registrations does not exist when it should.");
  }
}


###########
# Test 2 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname Register6AndCheckOnCourse);
%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 5086148225 email_address mark:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Mokey";
$REGISTRATION_INFO{"last_name"} = "Okey";
$GET{"competitor_name"} = "Mokey Okey";
%COOKIE = ();  # empty hash


register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_1_id = $TEST_INFO{"competitor_id"};

check_results(0);
check_on_course(1);
check_competitor_on_course("Mokey Okey", $competitor_1_id);

success();


###########
# Test 3 - a competitor finishes (with an SI unit), confirm that a course was chosen

%TEST_INFO = qw(Testname OneMotalaFinisher);
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(5086148225;200 start:200 finish:800 401:210 103:300 104:440);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_1_id, "5086148225", "09-F1S1", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};

success();


###########
# Test 4 - register and finish a few more on different variants of the course
# Test registration of a new entrant
%TEST_INFO = qw(Testname RegisterAndFinishMore);
%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 5083959473 email_address m:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Liddy";
$REGISTRATION_INFO{"last_name"} = "Biddy";
$GET{"competitor_name"} = "Liddy Biddy";
%COOKIE = ();  # empty hash


register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_2_id = $TEST_INFO{"competitor_id"};

check_competitor_on_course("Liddy Biddy", $competitor_2_id);

%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 141421 email_address k:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Tumpy";
$REGISTRATION_INFO{"last_name"} = "OStuffy";
$GET{"competitor_name"} = "Tumpy OStuffy";

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_3_id = $TEST_INFO{"competitor_id"};



%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(141421;200 start:200 finish:800 501:310 102:350 104:640);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_3_id, "141421", "13-F2S2", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};

@si_results = qw(5083959473;200 start:200 finish:800 501:310 102:350 103:640);
$base_64_results = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;

finish_with_stick_successfully($competitor_2_id, "5083959473", "14-F2S3", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};

success();

###########
# Test 5 - Test extra controls punched (DNF) and true DNF
%TEST_INFO = qw(Testname RegisterAndFinishVariousMistakes);
%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 271828 email_address z:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Bad";
$REGISTRATION_INFO{"last_name"} = "Bunny";
$GET{"competitor_name"} = "Bad Bunny";
%COOKIE = ();  # empty hash


register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_4_id = $TEST_INFO{"competitor_id"};

check_competitor_on_course("Bad Bunny", $competitor_4_id);

%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 314159 email_address a:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Peter";
$REGISTRATION_INFO{"last_name"} = "OToole";
$GET{"competitor_name"} = "Peter OToole";

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_5_id = $TEST_INFO{"competitor_id"};

%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(271828;200 start:200 finish:800 401:310 102:350 103:525 104:680);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_4_id, "271828", "15-F1All", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};


%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(314159;200 start:200 finish:800 150:289 102:350);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_dnf($competitor_5_id, "314159", "09-F1S1", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};

success();



###########
# Test 6 - Test an autoplace course with a mass start
%TEST_INFO = qw(Testname MassStartAutoPlaceCourse);
%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 144144 email_address z:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Twelve";
$REGISTRATION_INFO{"last_name"} = "Toes";
$GET{"competitor_name"} = "Twelve Toes";
%COOKIE = ();  # empty hash


register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_5_id = $TEST_INFO{"competitor_id"};

check_competitor_on_course("Twelve Toes", $competitor_5_id);

%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 169169 email_address a:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Thirteen";
$REGISTRATION_INFO{"last_name"} = "InASuit";
$GET{"competitor_name"} = "Thirteen InASuit";

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_6_id = $TEST_INFO{"competitor_id"};
check_competitor_on_course("Thirteen InASuit", $competitor_6_id);


%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 196196 email_address z:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Fourteen";
$REGISTRATION_INFO{"last_name"} = "Forty";
$GET{"competitor_name"} = "Fourteen Forty";
%COOKIE = ();  # empty hash

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_7_id = $TEST_INFO{"competitor_id"};

check_competitor_on_course("Fourteen Forty", $competitor_7_id);

%GET = qw(key UnitTestPlayground course 17-AutoMotala);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 225225 email_address a:@mkoconnell.com cell_phone 5086148225 car_info Rav4 is_member no);
$REGISTRATION_INFO{"first_name"} = "Fifteen";
$REGISTRATION_INFO{"last_name"} = "FiftyFive";
$GET{"competitor_name"} = "Fifteen FiftyFive";

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_8_id = $TEST_INFO{"competitor_id"};
check_competitor_on_course("Fifteen FiftyFive", $competitor_8_id);

%GET = qw(key UnitTestPlayground courses_to_start 17-AutoMotala universal_start no si_stick_time 750);
$GET{"event"} = ${event_id};
my($cmd) = "php ../OMeetMgmt/mass_start_courses.php";
hashes_to_artificial_file();
my($output);
$output = qx($cmd);

if (($output !~ /STARTED,Twelve Toes,17-AutoMotala/) || ($output !~ /STARTED,Thirteen InASuit,17-AutoMotala/) ||
	($output !~ /STARTED,Fourteen Forty,17-AutoMotala/) || ($output !~ /STARTED,Fifteen FiftyFive,17-AutoMotala/)) {
  error_and_exit("Did not see expected mass start results.\n$output");
}




%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(169169;0 start:0 finish:1800 401:910 102:1050 104:1400);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_6_id, "169169", "10-F1S2", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};


%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(196196;0 start:0 finish:2100 501:1050 102:1400 104:1800);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_7_id, "196196", "13-F2S2", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};


%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(225225;0 start:0 finish:2500 501:1050 102:1400 103:2300);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_8_id, "225225", "14-F2S3", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};

check_results(8);

success();


############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
