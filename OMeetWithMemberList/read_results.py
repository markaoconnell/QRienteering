#!/usr/bin/python

import sys, getopt
import re

#
#use strict;
#use MIME::Base64;
#
#
#my(@result_files);
#
#my(%results_by_stick) = qw();
#my(%new_results_by_stick) = qw();
#

# Initialize a few helpful constants
verbose = 0
debug = 0
testing_run = 0
url = "http://www.mkoconnell.com/OMeet/not_there"

#my($VIEW_RESULTS) = "OMeet/view_results.php";
#my($FINISH_COURSE) = "OMeet/finish_course.php";
#my($REGISTER_COURSE) = "OMeetRegistration/register.php";
#my($MANAGE_EVENTS) = "OMeetMgmt/manage_events.php";

# URLs for the web site
VIEW_RESULTS = "OMeet/view_results.php"
FINISH_COURSE = "OMeet/finish_course.php"
REGISTER_COURSE = "OMeetRegistration/register.php"
MANAGE_EVENTS = "OMeetMgmt/manage_events.php"

TWELVE_HOURS_IN_SECONDS = (12 * 3600)

def usage():
  print "Usage: " + sys.argv[0]
  print "Usage: " + sys.argv[0] + " [-e event] [-k key] [-u url_of_QR_web_site] [-dvrt]"
  print "\t-e:\tEvent identifier"
  print "\t-k:\tKey for the series (from the administrator)"
  print "\t-u:\tURL for the web site where the results are posted"
  print "\t-d:\tDebug - show extra debugging information (not normally useful)"
  print "\t-v:\tVerbose - show extra information about the workings of the program (sometimes useful)"
  print "\t-r:\tReplay a si stick - useful for a competitor who misregistered"
  print "\t-t:\tTesting run - only use in test environments"


#sub read_ini_file {
#  my(%ini_file_contents);
#
#  open(INI_FILE, "<./read_results.ini");
#  while (<INI_FILE>) {
#    chomp;
#    s/^[ \t]*//;  # Remove leading whitespace
#    s/#.*//;   # Remove comments
#    next if (/^$/);  # Skip blank lines (skip if was just a comment)
#    my($key, $value) = split("[ \t=]+");
#    $ini_file_contents{$key} = $value; 
#    #print "Initialization: $key => $value.\n";
#  }
#
#  close(INI_FILE);
#  return(%ini_file_contents);
#}

def read_ini_file():
  ini_file_contents = {}

  with open("./read_results.ini", "r") as INI_FILE:
    for file_line in INI_FILE:
      file_line = file_line.strip()
      #print("Found " + file_line + " in the ini file.")
      file_line = re.sub(r'#.*$', "", file_line)
      if (file_line == ""):
        continue  # Ignore empty lines (just a comment perhaps?)

      split_elements = re.split(r'[ \t=]+', file_line)
      if (len(split_elements) < 2):
        print("ERROR: Too few elements on line " + file_line + ", skipping it.")
        continue
      elif (len(split_elements) > 2):
        print("Extra elements on line " + file_line + ", ignoring the extras.")

      ini_file_contents[split_elements[0]] = split_elements[1]
      #print ("The value of " + split_elements[0] + " is " + split_elements[1] + "")

  return ini_file_contents


#sub get_event {
#  my($event_key) = @_;
#
#  # Check to see if this is a testing run
#
#  my($output);
#  $output = make_url_call($MANAGE_EVENTS, "key=$event_key&recent_event_timeout=12h");
#
#  my(@event_matches) = ($output =~ m#(view_results.php?.*?>.*?<)/a>#g);
#
#  if (scalar(@event_matches) == 0) {
#    print "Found zero matching events.\n" if ($verbose);
#    return("", "");
#  }
#  elsif (scalar(@event_matches) == 1) {
#    $event_matches[0] =~ /(event-[0-9a-f]*).*>Results for (.*)</;
#    print "Found single matching event ($1) named $2.\n" if ($verbose);
#    return($1, $2);
#  }
#  else {
#    my(@event_ids) = map { /(event-[0-9a-f]*)/; $1 } @event_matches;
#    my(@event_names) = map { />Results for (.*)</; $1 } @event_matches;
#    print "Please choose the event:\n";
#    my($i);
#    for ($i = 0; $i < scalar(@event_names); $i++) {
#      printf "%2d: %s %s\n", $i + 1, $event_names[$i], ($verbose ? "(" . $event_ids[$i] . ")" : "");
#    }
#
#    my($input);
#    while (1) {
#      print "\nYour choice: ";
#      $input = <STDIN>;
#      chomp($input);
#      if (($input !~ /^[0-9]+$/) || ($input <= 0) || ($input > scalar(@event_names))) {
#        print "Your choice \"$input\" is not valid, please try again.\n";
#      }
#      else {
#        last;
#      }
#    }
#    
#    return ($event_ids[$input - 1], $event_names[$input - 1]);
#  }
#
#  return ("", "");
#}
#
#
## Need to handle a reused stick - use stick-raw_start as the key???
#sub read_results {
#
#  my($result_file);
#  foreach $result_file (@result_files) {
##	print "Opening $result_file to read results.\n" if ($debug);
#    open(INFILE, "<$result_file");
#    my(@result_lines) = <INFILE>;
#    close(INFILE);
#
##    print "Read " . scalar(@result_lines) . " lines from $result_file.\n";
#  
#    chomp(@result_lines);
#
#    my($result_line);
#    foreach $result_line (@result_lines) {
#      $result_line =~ s/\r//g;
#      my($stick, $raw_start, $raw_clear, @controls) = split(";", $result_line);
#
#      my($hash_key) = "${stick};" . get_timestamp($controls[0]);
#
#      if (!defined($results_by_stick{$hash_key})) {
#        $new_results_by_stick{$hash_key} = \@controls;
#      }
#    }
#  }
#
#}
#
#sub get_timestamp {
#  my($or_time) = @_;
#  my($week, $day, $hour, $minute, $second) = split(":", $or_time);
#  my($timestamp) = 0;
#
#  $timestamp += ($hour * 3600) if ($hour ne "--");
#  $timestamp += ($minute * 60) if ($minute ne "--");
#  $timestamp += $second if ($second ne "--");
#
##  print "Converted ${or_time} to ${timestamp}.\n";
#
#  return($timestamp);
#}
#
#sub make_url_call {
#  my($php_script_to_call, $params) = @_;
#  my($cmd, $output);
#
#  if ($testing_run && (-f "../$php_script_to_call")) {
#    my(@param_pairs) = split("&", $params);
#    my(%GET) = map { split("="); } @param_pairs;
#
#    open(ARTIFICIAL_FILE, ">./artificial_input");
#    my($entry);
#    foreach $entry (keys(%GET)) {
#      print ARTIFICIAL_FILE "GET $entry $GET{$entry}\n";
#    }
#    close(ARTIFICIAL_FILE);
#
#    $cmd = "php ../$php_script_to_call";
#  }
#  else {
#    $cmd = "curl -s \"$url/$php_script_to_call?$params\"";
#  }
#
#  print "Running $cmd\n" if ($debug);
#  $output = qx($cmd);
#  print $output if ($debug);
#
#  return ($output);
#}
#
##89898989;-:-:--:--:--;-:-:--:--:--;-:-:19:34:14;-:-:19:34:43;101;-:-:19:34:23;102;-:-:19:34:36;103;-:-:19:34:38;104;-:-:19:34:40;105;-:-:19:34:41;
#
#$| = 1; # try using unbuffered IO so that we can see the output
#my(%initializations) = read_ini_file();
initializations = read_ini_file()

event = ""
event_name = ""
event_key = ""
or_path = ""

if ("key" in initializations):
  event_key = initializations["key"]

if ("url" in initializations):
  url = initializations["url"]

if ("debug" in initializations):
  debug = initializations["debug"]

if ("verbose" in initializations):
  verbose = initializations["verbose"]

if ("testing_run" in initializations):
  testing_run = initializations["testing_run"]

if ("or_path" in initializations):
  or_path = initialization["or_path"]

replay_si_stick = 0

try:
  opts, args = getopt.getopt(sys.argv[1:], "e:k:u:dvtrh")
except getopt.GetoptError:
  print "Parse error on command line."
  usage()
  sys.exit(2)
#print "Found program arguments: ", opts
for opt, arg in opts:
  if opt == "-h":
    usage()
    sys.exit()
  elif opt == "-e":
    event = arg
  elif opt == "-k":
    event_key = arg
  elif opt == "-u":
    url = arg
  elif opt == "-d":
    debug = 1
  elif opt == "-v":
    verbose = 1
  elif opt == "-t":
    testing_run = 1
  elif opt == "-r":
    replay_si_stick = 1

#if ($event_key eq "") {
#  print "Usage: $0 -e <eventName> -k <eventKey> [ -u <url> ]\n\t-k option required on command line or .ini file (key).\n";
#  exit 1;
#}
#
#if ($event eq "") {
#  ($event, $event_name) = get_event($event_key);
#  print "Processing results for event $event_name ($event).\n";
#}
#
#if (($event eq "")  || ($event_key eq "")) {
#  print "Usage: $0 -e <eventName> -k <eventKey> [ -u <url> ]\n\t-e option required.\n";
#  exit 1;
#}
#
## Ensure that the event specified is valid
#my($output);
#$output = make_url_call($VIEW_RESULTS, "event=$event&key=$event_key");
#if (($output =~ /No such event found $event/) || ($output !~ /Show results for/)) {
#  print "Event $event not found, please check if event $event and key $event_key are valid.\n";
#  exit 1;
#}
##print $output;
#
#
## Find the OR event to manage - it should be the last event
## The or_path variable should point to a valid directory
#if (! -d $or_path) {
#  print "ERROR: No such directory \"$or_path\", cannot read results from OR downloads.\n";
#  exit 1;
#}
#
#opendir(OR_DIR, "$or_path") || die "Cannot open OR directory $or_path";
#my(@or_dir_contents) = readdir(OR_DIR);
#close(OR_DIR);
#print join(",", @or_dir_contents) . ": elements found in \"$or_path\".\n" if ($debug);
#my(@or_events) = grep { /^[0-9]+$/ && -d "$or_path/$_" } @or_dir_contents;
#my(@sorted_or_events) = sort { $a <=> $b } @or_events;
#print join(",", @sorted_or_events) . ": sorted elements found in \"$or_path\".\n" if ($debug);
#my($or_event_number) = $sorted_or_events[$#sorted_or_events];
#print "Using OR event $or_event_number\n" if ($verbose);
#
#@result_files = ("$or_path/$or_event_number/results.csv", "$or_path/$or_event_number/resultslog.csv");
#
#my($si_stick_to_replay);
#my($si_stick_to_replay_found) = 0;
#if ($replay_si_stick) {
#  print "Replay which si stick?\n";
#  $si_stick_to_replay = <STDIN>;
#  chomp($si_stick_to_replay);
#}
#
#my($loop_count) = 0;
#while (1) {
#  read_results();
#
#  if (($loop_count % 20) == 0) {
#    my($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) =
#                                                localtime(time);
#
#    printf "Awaiting new results at: %02d:%02d:%02d\r", $hour, $min, $sec;
#  }
#  print "Found new keys: " . join(",", keys(%new_results_by_stick)) . "\n" if ($verbose && scalar(keys(%new_results_by_stick) > 0));
#  
#  my($key);
#  foreach $key (keys(%new_results_by_stick)) {
##    print "Found for $key: " . join(",", @{$new_results_by_stick{$key}}) . "\n";
#    my(@controls) = @{$new_results_by_stick{$key}};
#
#    my($start, $finish) = ($controls[0], $controls[1]);
#    my($start_timestamp) = get_timestamp($start);
#    my($finish_timestamp) = get_timestamp($finish);
#    # Some old si cards only store 12 hour time, which will wrap to 0 if the competitor
#    # starts before noon and finishes after noon.  Look for that and fix it.
#    my($old_si_stick_detected) = 0;
#    if (($finish_timestamp < $start_timestamp) && ($start_timestamp < $TWELVE_HOURS_IN_SECONDS)) {
#      $finish_timestamp += $TWELVE_HOURS_IN_SECONDS;
#      $old_si_stick_detected = 1;
#    }
#
#    my($i);
#    my(@qr_controls) = ();
#    for ($i = 2; $i < @controls; $i += 2) {
#      my($control, $or_time) = ($controls[$i], $controls[$i + 1]);
#      my($control_timestamp) = get_timestamp($or_time);
#      if ($old_si_stick_detected && ($control_timestamp < $start_timestamp)) {
#        $control_timestamp += $TWELVE_HOURS_IN_SECONDS;
#      }
#      push(@qr_controls, "${control}:${control_timestamp}");
#    }
#
#    my($qr_result_string) = "${key}," . join(",", "start:${start_timestamp}","finish:${finish_timestamp}", @qr_controls);
#    print "Got results for ${key}: ${qr_result_string}\n" if ($verbose);
#    # Base64 encode for upload to the website
#    #print "$qr_result_string\n";
#    my($web_site_string) = encode_base64($qr_result_string);
#    $web_site_string =~ s/\n//g;
#    $web_site_string =~ s/=/%3D/g;
#    my($si_stick, $start_time) = split(";", $key);
#    if (!$replay_si_stick || ($si_stick eq $si_stick_to_replay)) {
#      $output = make_url_call($FINISH_COURSE, "event=$event&key=$event_key&si_stick_finish=$web_site_string");
#  
#      print "\nResults for si_stick ${si_stick}:\n";
#      if ($output =~ /(Cannot find.*)/) {
#        print "$1\n";
#      }
#    
#      if ($output =~ /(Results for:.*)<p>/) {
#        print "$1\n";
#      }
#  
#      if ($output =~ /(Second scan.*)/) {
#        print "$1\n";
#      }
#  
#      $results_by_stick{$key} = $new_results_by_stick{$key};
#
#      $si_stick_to_replay_found = 1 if ($replay_si_stick);
#    }
#  }
#
#  %new_results_by_stick = ();
#
#  last if ($testing_run || $replay_si_stick);  # For testing, no need to wait for more results
#
#  sleep(3);
#  $loop_count++;
#}
#
#if ($replay_si_stick && !$si_stick_to_replay_found) {
#  print "\nERROR: No results found for SI stick $si_stick_to_replay.\n";
#}
