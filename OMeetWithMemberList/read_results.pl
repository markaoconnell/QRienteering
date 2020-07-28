#!/usr/bin/perl

use strict;
use MIME::Base64;


my(@result_files);

my(%results_by_stick) = qw();
my(%new_results_by_stick) = qw();

my($debug) = 0;
my($verbose) = 0;
my($url) = "http://www.mkoconnell.com/OMeet/not_there";

my($VIEW_RESULTS) = "OMeet/view_results.php";
my($FINISH_COURSE) = "OMeet/finish_course.php";
my($REGISTER_COURSE) = "OMeetRegistration/register.php";
my($MANAGE_EVENTS) = "OMeetMgmt/manage_events.php";


sub read_ini_file {
  my(%ini_file_contents);

  open(INI_FILE, "<./read_results.ini");
  while (<INI_FILE>) {
    chomp;
    s/^[ \t]*//;  # Remove leading whitespace
    my($key, $value) = split("[ \t=]+");
    $ini_file_contents{$key} = $value; 
    #print "Initialization: $key => $value.\n";
  }

  close(INI_FILE);
  return(%ini_file_contents);
}

sub get_event {
  my($event_key) = @_;

  my($cmd) = "curl -s \"$url/$MANAGE_EVENTS?key=$event_key&recent_event_timeout=12h\"";
  print "Running $cmd\n" if ($debug);
  my($output);
  $output = qx($cmd);
  print "Cmd output is $output\n" if ($debug);

  my(@event_matches) = ($output =~ m#(view_results.php?.*?>.*?<)/a>#g);

  if (scalar(@event_matches) == 0) {
    print "Found zero matching events.\n" if ($verbose);
    return("", "");
  }
  elsif (scalar(@event_matches) == 1) {
    $event_matches[0] =~ /(event-[0-9a-f]*).*>(.*)</;
    print "Found single matching event ($1) named $2.\n" if ($verbose);
    return($1, $2);
  }
  else {
    my(@event_ids) = map { /(event-[0-9a-f]*)/; $1 } @event_matches;
    my(@event_names) = map { />(.*)</; $1 } @event_matches;
    print "Please choose the event:\n";
    my($i);
    for ($i = 0; $i < scalar(@event_names); $i++) {
      printf "%2d: %s %s\n", $i + 1, $event_names[$i], ($verbose ? "(" . $event_ids[$i] . ")" : "");
    }

    my($input);
    while (1) {
      print "\nYour choice: ";
      $input = <STDIN>;
      chomp($input);
      if (($input !~ /^[0-9]+$/) || ($input <= 0) || ($input > scalar(@event_names))) {
        print "Your choice \"$input\" is not valid, please try again.\n";
      }
      else {
        last;
      }
    }
    
    return ($event_ids[$input - 1], $event_names[$input - 1]);
  }

  return ("", "");
}


# Need to handle a reused stick - use stick-raw_start as the key???
sub read_results {

  my($result_file);
  foreach $result_file (@result_files) {
    open(INFILE, "<$result_file");
    my(@result_lines) = <INFILE>;
    close(INFILE);

    #print "Read " . scalar(@result_lines) . " lines from $result_file.\n";
  
    chomp(@result_lines);

    my($result_line);
    foreach $result_line (@result_lines) {
      $result_line =~ s/\r//g;
      my($stick, $raw_start, $raw_clear, @controls) = split(";", $result_line);

      my($hash_key) = "${stick};" . get_timestamp($controls[0]);

      if (!defined($results_by_stick{$hash_key})) {
        $new_results_by_stick{$hash_key} = \@controls;
      }
    }
  }

}

sub get_timestamp {
  my($or_time) = @_;
  my($week, $day, $hour, $minute, $second) = split(":", $or_time);
  my($timestamp) = 0;

  $timestamp += ($hour * 3600) if ($hour ne "--");
  $timestamp += ($minute * 60) if ($minute ne "--");
  $timestamp += $second if ($second ne "--");

#  print "Converted ${or_time} to ${timestamp}.\n";

  return($timestamp);
}

#89898989;-:-:--:--:--;-:-:--:--:--;-:-:19:34:14;-:-:19:34:43;101;-:-:19:34:23;102;-:-:19:34:36;103;-:-:19:34:38;104;-:-:19:34:40;105;-:-:19:34:41;

my(%initializations) = read_ini_file();
my($event) = "";
my($event_name) = "";
my($event_key) = "";
if ($initializations{"key"} ne "") {
  $event_key = $initializations{"key"};
}


if ($initializations{"url"} ne "") {
  $url = $initializations{"url"};
}

my($or_path) = ".";
if ($initializations{"or_path"} ne "") {
  $or_path = $initializations{"or_path"};
}

while ($ARGV[0] =~ /^-/) {
  if ($ARGV[0] eq "-e") {
    $event = $ARGV[1];
    shift; shift;
  }
  elsif ($ARGV[0] eq "-k") {
    $event_key = $ARGV[1];
    shift; shift;
  }
  elsif ($ARGV[0] eq "-u") {
    $url = $ARGV[1];
    shift; shift;
  }
  elsif ($ARGV[0] eq "-d") {
    $debug = 1;
    shift;
  }
  elsif ($ARGV[0] eq "-v") {
    $verbose = 1;
    shift;
  }
  else {
    print "Usage: $0 -e <eventName> -k <eventKey> [ -u <url> ]\n";
    exit 1;
  }
}

if ($event_key eq "") {
  print "Usage: $0 -e <eventName> -k <eventKey> [ -u <url> ]\n\t-k option required on command line or .ini file (key).\n";
  exit 1;
}

if ($event eq "") {
  ($event, $event_name) = get_event($event_key);
  print "Processing results for event $event_name ($event).\n";
}

if (($event eq "")  || ($event_key eq "")) {
  print "Usage: $0 -e <eventName> -k <eventKey> [ -u <url> ]\n\t-e option required.\n";
  exit 1;
}

# Ensure that the event specified is valid
my($cmd) = "curl -s \"$url/$VIEW_RESULTS?event=$event&key=$event_key\"";
print "Running $cmd\n" if ($debug);
my($output);
$output = qx($cmd);
print $output if ($debug);
if (($output =~ /No such event found $event/) || ($output !~ /Show results for/)) {
  print "Event $event not found, please check if event $event and key $event_key are valid.\n";
  exit 1;
}
#print $output;


# Find the OR event to manage - it should be the last event
my($or_event_number) = 1;
while (-f "$or_path/$or_event_number/results.csv") {
  print "Found $or_path/$or_event_number/results.csv\n" if ($debug);
  $or_event_number++;
  if (($or_event_number % 200) == 0) {
    print "At or event $or_event_number, is everything correct?\n";
  }
}
if ($or_event_number > 1) {
  $or_event_number--;  # The final one that was present is the correct one
}
print "Using OR event $or_event_number\n" if ($verbose);

@result_files = ("$or_path/$or_event_number/results.csv", "$or_path/$or_event_number/resultlog.csv");

my($loop_count) = 0;
while (1) {
  $loop_count++;
  read_results();

  if (($loop_count % 20) == 0) {
    my($now) = qx(date);
    chomp($now);

    print "Awaiting new results at: ${now}\r";
  }
  print "Found new keys: " . join(",", keys(%new_results_by_stick)) . "\n" if ($verbose && scalar(keys(%new_results_by_stick) > 0));
  
  my($key);
  foreach $key (keys(%new_results_by_stick)) {
#    print "Found for $key: " . join(",", @{$new_results_by_stick{$key}}) . "\n";
    my(@controls) = @{$new_results_by_stick{$key}};

    my($start, $finish) = ($controls[0], $controls[1]);
    my($start_timestamp) = get_timestamp($start);
    my($finish_timestamp) = get_timestamp($finish);
    my($i);
    my(@qr_controls) = ();
    for ($i = 2; $i < @controls; $i += 2) {
      my($control, $or_time) = ($controls[$i], $controls[$i + 1]);
      my($control_timestamp) = get_timestamp($or_time);
      push(@qr_controls, "${control}:${control_timestamp}");
    }

    my($qr_result_string) = "${key}," . join(",", "start:${start_timestamp}","finish:${finish_timestamp}", @qr_controls);
    print "Got results for ${key}: ${qr_result_string}\n" if ($verbose);
    # Base64 encode for upload to the website
    #print "$qr_result_string\n";
    my($web_site_string) = encode_base64($qr_result_string);
    $web_site_string =~ s/\n//g;
    $web_site_string =~ s/=/%3D/g;
    $cmd = "curl -s \"$url/$FINISH_COURSE?event=$event&key=$event_key&si_stick_finish=$web_site_string\"";
    print "Running $cmd\n" if ($debug);
    $output = qx($cmd);
    #print "$cmd\n";
    print $output if ($debug);

    if ($output =~ /(Cannot find.*)/) {
      print "$1\n";
    }
  
    if ($output =~ /(Results for:.*)<p>/) {
      print "$1\n";
    }

    if ($output =~ /(Second scan.*)/) {
      print "$1\n";
    }

    $results_by_stick{$key} = $new_results_by_stick{$key};
  }

  %new_results_by_stick = ();

  sleep(3);
}
