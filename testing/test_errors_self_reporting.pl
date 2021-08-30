#!/usr/bin/perl

use strict;

require "testHelpers.pl";
require "success_call_helpers.pl";

my(%GET, %TEST_INFO, %COOKIE, %POST);
my($cmd, $output, $output2, $competitor_id, $competitor_id2, $path, $time_now, $controls_found_path);
my(@file_contents_array);
my(@directory_contents);

my($COMPETITOR_NAME) = "Mark_OConnell_Bad_Finish";
my($ERROR_COMPETITOR_NAME) = "Mark_OConnell_Error";
my($COMPETITOR_NAME_2) = "Mark_OConnell_Bad_Finish_ScoreO";

set_test_info(\%GET, \%COOKIE, \%POST, \%TEST_INFO, $0);
initialize_event();
create_key_file();
create_event_successfully(\%GET, \%COOKIE, \%POST, \%TEST_INFO);
my($event_id) = $TEST_INFO{"event_id"};
set_no_redirects_for_event($event_id, "UnitTestPlayground");



###########
# Test 1 - Self report without entering a name
# Should return an error message
%TEST_INFO = qw(Testname TestSelfReportNoName);
%GET = qw(key UnitTestPlayground course 00-White);
$GET{"event"} = $event_id;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);

if ($output !~ /ERROR: Competitor name must be specified/) {
  error_and_exit("Web page output wrong, did not see error about no competitor name.\n$output");
}

#print $output;

success();



###########
# Test 2 - self report with an unknown event
# Should return an error message
%TEST_INFO = qw(Testname TestSelfReportNoEvent);
%GET = qw(key UnitTestPlayground event OldEvent course 00-White);
$GET{"competitor_name"} = $ERROR_COMPETITOR_NAME;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);

if ($output !~ /using an authorized link/){
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();


###########
# Test 3 - self_report with a bad course
# Should return an error message
%TEST_INFO = qw(Testname TestFinishGoodEventBadCourse);
%GET = qw(key UnitTestPlayground course 03-Orange);
$GET{"competitor_name"} = $ERROR_COMPETITOR_NAME;
$GET{"event"} = $event_id;
hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);

if ($output !~ /Course must be specified/){
  error_and_exit("Web page output wrong, bad event error not found.\n$output");
}

#print $output;

success();



###########
# Test 4 - Self report with a good time
%TEST_INFO = qw(Testname SelfReportWorkedOkay);

%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "32m45s";
$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "0";

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);


#print $output;
if ($output !~ /Results for: $COMPETITOR_NAME/) {
  error_and_exit("Did not find results string.\n$output");
}


$path = get_base_path($GET{"key"}) . "/" . $GET{"event"};

my($competitor_id);
my($ls_cmd);
$ls_cmd = "ls -1t ${path}/Competitors | head -n 1";
$competitor_id = qx($ls_cmd);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";

my($competitor_path) = "${path}/Competitors/$competitor_id";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found self_reported));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (! -d "${path}/Results/00-White") {
  error_and_exit("Results directory 00-White does not exist, should have been created.");
}

my(@results_array) = check_directory_contents("${path}/Results/00-White", ());
if (!grep(/$competitor_id/, @results_array)) {
  error_and_exit("Results file not found for $competitor_id, directory contents are: " . join("--", @results_array));
}

my($results_file) = sprintf("%04d,%06d,%s", 0, (32 * 60) + 45, $competitor_id);
if (! -f "${path}/Results/00-White/${results_file}") {
  error_and_exit("Did not find file ${results_file} when expected.");
}

success();


###########
# Test 5 - Self report with a good time and a DNF
%TEST_INFO = qw(Testname SelfReportWorkedOkayButDNF);

%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "32m45s";
#$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "0";

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);


#print $output;
if ($output !~ /Results for: $COMPETITOR_NAME/) {
  error_and_exit("Did not find results string.\n$output");
}


$path = get_base_path($GET{"key"}) . "/" . $GET{"event"};

my($competitor_id);
my($ls_cmd);
$ls_cmd = "ls -1t ${path}/Competitors | head -n 1";
$competitor_id = qx($ls_cmd);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";

my($competitor_path) = "${path}/Competitors/$competitor_id";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found self_reported dnf));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (! -d "${path}/Results/00-White") {
  error_and_exit("Results directory 00-White does not exist, should have been created.");
}

my(@results_array) = check_directory_contents("${path}/Results/00-White", ());
if (!grep(/$competitor_id/, @results_array)) {
  error_and_exit("Results file not found for $competitor_id, directory contents are: " . join("--", @results_array));
}

# For the results, there are 5 controls on White, a self-reported DNF is counted as finding
# none, so max_score (5) - my score (0 controls found) == 5
my($results_file) = sprintf("%04d,%06d,%s", 5, (32 * 60) + 45, $competitor_id);
if (! -f "${path}/Results/00-White/${results_file}") {
  error_and_exit("Did not find file ${results_file} when expected.");
}

success();





###########
# Test 6 - self report on a scoreO with no time penalty
# Try it with a ScoreO
%TEST_INFO = qw(Testname TestSelfReportScoreONoPenalty);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"competitor_name"} = $COMPETITOR_NAME_2;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "3m45s";
$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "150";  # Maximum possible score
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);

#print $output;
if ($output !~ /Results for: $COMPETITOR_NAME_2/) {
  error_and_exit("Did not find results string.\n$output");
}


$path = get_base_path($GET{"key"}) . "/" . $GET{"event"};

my($competitor_id);
my($ls_cmd);
$ls_cmd = "ls -1t ${path}/Competitors | head -n 1";
$competitor_id = qx($ls_cmd);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";

my($competitor_path) = "${path}/Competitors/$competitor_id";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found self_reported));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (! -d "${path}/Results/02-ScoreO") {
  error_and_exit("Results directory 02-ScoreO does not exist, should have been created.");
}

my(@results_array) = check_directory_contents("${path}/Results/02-ScoreO", ());
if (!grep(/$competitor_id/, @results_array)) {
  error_and_exit("Results file not found for $competitor_id, directory contents are: " . join("--", @results_array));
}

# For the results, there are 150 points on the scoreO, got 150
# none, so max_score (150) - my score (150) == 0
my($results_file) = sprintf("%04d,%06d,%s", 0, (3 * 60) + 45, $competitor_id);
if (! -f "${path}/Results/02-ScoreO/${results_file}") {
  error_and_exit("Did not find file ${results_file} when expected.");
}

success();


###########
# Test 7 - self report on a scoreO with a time penalty
# Try it with a ScoreO
%TEST_INFO = qw(Testname TestSelfReportScoreO5MinPenalty);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"competitor_name"} = $COMPETITOR_NAME_2;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "9m45s";
$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "130";  # Maximum possible score
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);

#print $output;
if ($output !~ /Results for: $COMPETITOR_NAME_2/) {
  error_and_exit("Did not find results string.\n$output");
}

$path = get_base_path($GET{"key"}) . "/" . $GET{"event"};

my($competitor_id);
my($ls_cmd);
$ls_cmd = "ls -1t ${path}/Competitors | head -n 1";
$competitor_id = qx($ls_cmd);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";

my($competitor_path) = "${path}/Competitors/$competitor_id";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found self_reported));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (! -d "${path}/Results/02-ScoreO") {
  error_and_exit("Results directory 02-ScoreO does not exist, should have been created.");
}

my(@results_array) = check_directory_contents("${path}/Results/02-ScoreO", ());
if (!grep(/$competitor_id/, @results_array)) {
  error_and_exit("Results file not found for $competitor_id, directory contents are: " . join("--", @results_array));
}

# For the results, there are 150 points on the scoreO, got 130
# none, so max_score (150) - (my score (130) - penalty (5 mins)) == 25
my($results_file) = sprintf("%04d,%06d,%s", 25, (9 * 60) + 45, $competitor_id);
if (! -f "${path}/Results/02-ScoreO/${results_file}") {
  error_and_exit("Did not find file ${results_file} when expected.");
}

success();


###########
# Test 8 - self report on a scoreO with too high a score
# Try it with a ScoreO
%TEST_INFO = qw(Testname TestSelfReportScoreOTooManyPointsReported);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"competitor_name"} = $ERROR_COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "9m45s";
$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "230";  # Maximum possible score
%COOKIE = ();  # empty hash

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);

#print $output;

if ($output =~ /Results for: $ERROR_COMPETITOR_NAME/) {
  error_and_exit("Found the results string but should have seen an error.\n$output");
}


if ($output !~ /larger than course maximum/) {
  error_and_exit("Course score too large (230 > 150) but no error reported.\n$output");
}


success();


###########
# Test 9 - Self report but with no time
%TEST_INFO = qw(Testname SelfReportNoTimeGiven);

%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "none";
$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "0";

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);


#print $output;
if ($output !~ /Results for: $COMPETITOR_NAME/) {
  error_and_exit("Did not find results string.\n$output");
}


$path = get_base_path($GET{"key"}) . "/" . $GET{"event"};

my($competitor_id);
my($ls_cmd);
$ls_cmd = "ls -1t ${path}/Competitors | head -n 1";
$competitor_id = qx($ls_cmd);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";

my($competitor_path) = "${path}/Competitors/$competitor_id";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found self_reported no_time));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (! -d "${path}/Results/00-White") {
  error_and_exit("Results directory 00-White does not exist, should have been created.");
}

my(@results_array) = check_directory_contents("${path}/Results/00-White", ());
if (!grep(/$competitor_id/, @results_array)) {
  error_and_exit("Results file not found for $competitor_id, directory contents are: " . join("--", @results_array));
}

# For the results, no time given is translated into two days (86400 * 2)
my($results_file) = sprintf("%04d,%06d,%s", 0, 86400 * 2, $competitor_id);
if (! -f "${path}/Results/00-White/${results_file}") {
  error_and_exit("Did not find file ${results_file} when expected.");
}

success();


###########
# Test 10 - Self report but with no time and a DNF
%TEST_INFO = qw(Testname SelfReportNoTimeGivenAndDNF);

%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "none";
#$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "0";

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);


#print $output;
if ($output !~ /Results for: $COMPETITOR_NAME/) {
  error_and_exit("Did not find results string.\n$output");
}


$path = get_base_path($GET{"key"}) . "/" . $GET{"event"};

my($competitor_id);
my($ls_cmd);
$ls_cmd = "ls -1t ${path}/Competitors | head -n 1";
$competitor_id = qx($ls_cmd);
chomp($competitor_id);
print "My competitor_id is $competitor_id\n";

my($competitor_path) = "${path}/Competitors/$competitor_id";
my($controls_found_path) = "$competitor_path/controls_found";
if (-f "$controls_found_path/finish") {
  error_and_exit("$controls_found_path/finish does exist.");
}

@directory_contents = check_directory_contents($competitor_path, qw(name course controls_found self_reported dnf no_time));
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $path than expected: " . join("--", @directory_contents));
}

@directory_contents = check_directory_contents($controls_found_path, qw());
if ($#directory_contents != -1) {
  error_and_exit("More files exist in $controls_found_path than expected: " . join("--", @directory_contents));
}


# if this is the first time this is called, the directory may not exist
if (! -d "${path}/Results/00-White") {
  error_and_exit("Results directory 00-White does not exist, should have been created.");
}

my(@results_array) = check_directory_contents("${path}/Results/00-White", ());
if (!grep(/$competitor_id/, @results_array)) {
  error_and_exit("Results file not found for $competitor_id, directory contents are: " . join("--", @results_array));
}

# For the results, no time given is translated into two days (86400 * 2)
# There are 5 controls on White, a self-reported DNF is counted as finding
# none, so max_score (5) - my score (0 controls found) == 5
my($results_file) = sprintf("%04d,%06d,%s", 5, (86400 * 2), $competitor_id);
if (! -f "${path}/Results/00-White/${results_file}") {
  error_and_exit("Did not find file ${results_file} when expected.");
}

success();


###########
# Test 11 - Self report but with a bad time given
%TEST_INFO = qw(Testname SelfReportBadTimeGiven);

%GET = qw(key UnitTestPlayground course 00-White);
$GET{"competitor_name"} = $ERROR_COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "typedTheWrongThing";
#$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "0";

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);


#print $output;
if ($output =~ /Results for: $ERROR_COMPETITOR_NAME/) {
  error_and_exit("Found the results string but should have seen an error.\n$output");
}

if ($output !~ /ERROR: Incorrectly formatted time/) {
  error_and_exit("Did not find the correct error string.\n$output");
}


success();


###########
# Test 12 - Self report on a scoreO with a badly formatted score
%TEST_INFO = qw(Testname SelfReportScoreOBadScore);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"competitor_name"} = $ERROR_COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "2m15s";
$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "Nuttin";

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);


#print $output;
if ($output =~ /Results for: $ERROR_COMPETITOR_NAME/) {
  error_and_exit("Found the results string but should have seen an error.\n$output");
}

if ($output !~ /appears to contain non-numeric characters/) {
  error_and_exit("Did not find the correct error string.\n$output");
}


success();



###########
# Test 13 - Self report on a scoreO with a negative score
%TEST_INFO = qw(Testname SelfReportScoreONegativeScore);

%GET = qw(key UnitTestPlayground course 02-ScoreO);
$GET{"competitor_name"} = $COMPETITOR_NAME;
$GET{"event"} = $event_id;
$GET{"reported_time"} = "2m15s";
$GET{"found_all"} = "true";
$GET{"scoreo_score"} = "-15";

hashes_to_artificial_file();
$cmd = "php ../OMeetRegistration/self_report_2.php";
$output = qx($cmd);


#print $output;
if ($output =~ /Results for: $COMPETITOR_NAME/) {
  error_and_exit("Found the results string but should have seen an error.\n$output");
}

if ($output !~ /appears to contain non-numeric characters/) {
  error_and_exit("Did not find the correct error string.\n$output");
}


success();



############
# Cleanup

my($rm_cmd) = "rm -rf " . get_base_path("UnitTestPlayground");
print "Executing $rm_cmd\n";
qx($rm_cmd);
remove_key_file();
qx(rm artificial_input);
