#!/usr/bin/perl

use strict;
use MIME::Base64;


my(@result_files) = qw(30/results.csv 30/resultslog.csv);

my(%results_by_stick) = qw();
my(%new_results_by_stick) = qw();



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

my($event) = "";
my($event_key) = "";
my($verbose) = 0;
my($debug) = 0;
my($url) = "http://www.mkoconnell.com/OMeet/";
my($VIEW_RESULTS) = "view_results.php";
my($FINISH_COURSE) = "finish_course.php";
my($REGISTER_COURSE) = "register.php";

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

if (($event eq "")  || ($event_key eq "")) {
  print "Usage: $0 -e <eventName> -k <eventKey> [ -u <url> ]\n\t-e and -k options required.\n";
  exit 1;
}

# Ensure that the event specified is valid
my($cmd) = "curl -s \"$url/$VIEW_RESULTS?event=$event&key=$event_key\"";
print "Running $cmd\n" if ($debug);
my($output);
$output = qx($cmd);
print $output if ($debug);
if (($output =~ /No such event found $event/) || ($output !~ /Show results for/) || ($output !~ /show_splits\?course/)) {
  print "Event $event not found, please check if event $event and key $event_key are valid.\n";
  exit 1;
}
#print $output;

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
