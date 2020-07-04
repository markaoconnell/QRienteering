#!/usr/bin/perl

use strict;
use MIME::Base64;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my(%REGISTRATION_INFO);
my($COMPETITOR_FIRST_NAME) = "Mark";
my($COMPETITOR_LAST_NAME) = "OConnell_SiStick_Testing";
my($COMPETITOR_NAME) = "${COMPETITOR_FIRST_NAME}--space--${COMPETITOR_LAST_NAME}";
my($competitor_id);

##################
sub validate_file_present {
  my($filename) = @_;

  if (! -f "$filename") {
    error_and_exit("Expected file not found: $filename");
  }
}

###############
# Main program

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
create_key_file();
initialize_event();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");


###########
# Test 1 - register a new entrant successfully
# Test registration of a new entrant
%TEST_INFO = qw(Testname TestSuccessRegistrationMemberWithStick);
%GET = qw(key UnitTestPlayground course 00-White);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 5086148225 email_address karen@mkoconnell.com cell_phone 5083959473 car_info ToyotaCorolla is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$GET{"competitor_name"} = $COMPETITOR_NAME;
%COOKIE = ();  # empty hash

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

success();




###########
# Test 2 - start the course
# validate that the start entry is created
%TEST_INFO = qw(Testname TestFinishWithSiStick);
%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(5086148225;200 start:200 finish:800 201:210 202:300 203:440 204:600 205:700);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_id, "00-White", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/000210,201");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/000300,202");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/000440,203");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/000600,204");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/000700,205");

success();



###########
# Test 3 - register another entrant successfully
# Then DNF
%TEST_INFO = qw(Testname TestDNFWhenRunningWithStick);
%GET = qw(key UnitTestPlayground course 00-White);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name NEOC si_stick 3291200 email_address karen@mkoconnell.com cell_phone 5083959473 car_info ToyotaCorolla is_member yes);
$REGISTRATION_INFO{"first_name"} = $COMPETITOR_FIRST_NAME;
$REGISTRATION_INFO{"last_name"} = $COMPETITOR_LAST_NAME;
$GET{"competitor_name"} = $COMPETITOR_NAME;
%COOKIE = ();  # empty hash

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(3291200;400 start:400 finish:1600 201:510 202:1200);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_dnf($competitor_id, "00-White", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};
validate_file_present("${path}/Competitors/${competitor_id}/dnf");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/000510,201");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001200,202");

success();


###########
# Test 4 - register another entrant successfully
# Then complete the course, but with extra controls found
%TEST_INFO = qw(Testname TestExtraControlsWhenRunningWithStick);
%GET = qw(key UnitTestPlayground course 01-Yellow);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name CSU si_stick 4371408 email_address karen@mkoconnell.com cell_phone 7787878 car_info HondaOdyssey is_member no);
$REGISTRATION_INFO{"first_name"} = "Surtout";
$REGISTRATION_INFO{"last_name"} = "Burtout";
$GET{"competitor_name"} = "Surtout--space--Burtout";
%COOKIE = ();  # empty hash

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(4371408;800 start:800 finish:1484 202:910 301:985 204:1030 206:1200 208:1269 101:1337 210:1403);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_with_stick_successfully($competitor_id, "01-Yellow", \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};
validate_file_present("${path}/Competitors/${competitor_id}/extra");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/000910,202");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001030,204");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001200,206");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001269,208");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001403,210");

success();



###########
# Test 5 - register another entrant successfully, with some information missing
# Then complete a scoreO course
%TEST_INFO = qw(Testname TestSiStickOnScoreO);
%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"event"} = $event_id;
%REGISTRATION_INFO = qw(club_name QOC si_stick 1221 car_info ToyotaPriusGE7346 is_member no);
$REGISTRATION_INFO{"first_name"} = "Surtout";
$REGISTRATION_INFO{"last_name"} = "Burtout";
$REGISTRATION_INFO{"email_address"} = "";
$REGISTRATION_INFO{"cell_phone"} = "";
$GET{"competitor_name"} = "Surtout--space--Burtout";
%COOKIE = ();  # empty hash

register_member_successfully(\%GET, \%COOKIE, \%REGISTRATION_INFO, \%TEST_INFO);
$competitor_id = $TEST_INFO{"competitor_id"};

%GET = qw(key UnitTestPlayground);  # empty hash
$GET{"event"} = $event_id;
my(@si_results) = qw(1221;1600 start:1600 finish:2552 304:1734 302:1812 304:1919 301:2112 305:2332);
my($base_64_results) = encode_base64(join(",", @si_results));
$base_64_results =~ s/\n//g;  # it seems to add newlines sometimes
$GET{"si_stick_finish"} = $base_64_results;


finish_scoreO_with_stick_successfully($competitor_id, "02-ScoreO", 120 - 11, \%GET, \%COOKIE, \%TEST_INFO);
my($path) = get_base_path($GET{"key"}) . "/" . $GET{"event"};
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001734,304");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001812,302");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/001919,304");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/002112,301");
validate_file_present("${path}/Competitors/${competitor_id}/controls_found/002332,305");

success();




############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
