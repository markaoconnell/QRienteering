#!/usr/bin/python

import sys, getopt
import os
import subprocess
import re
import time
import base64
if not 'NO_SI_READER_IMPORT' in os.environ:
  from sireader import SIReader, SIReaderReadout, SIReaderControl, SIReaderException
from datetime import datetime

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

# URLs for the web site
VIEW_RESULTS = "OMeet/view_results.php"
FINISH_COURSE = "OMeet/finish_course.php"
REGISTER_COURSE = "OMeetRegistration/register.php"
MANAGE_EVENTS = "OMeetMgmt/manage_events.php"

TWELVE_HOURS_IN_SECONDS = (12 * 3600)

# Keys for the si_stick dict (should really convert this to a named tuple)
SI_STICK_KEY = r"si_stick"
SI_START_KEY = r"start_timestamp"
SI_FINISH_KEY = r"finish_timestamp"
SI_CONTROLS_KEY = r"qr_controls"

def usage():
  print("Usage: " + sys.argv[0])
  print("Usage: " + sys.argv[0] + " [-e event] [-k key] [-u url_of_QR_web_site] [-s serial port for si download station] [-dvrt]")
  print("\t-e:\tEvent identifier")
  print("\t-k:\tKey for the series (from the administrator)")
  print("\t-u:\tURL for the web site where the results are posted")
  print("\t-s:\tSerial port where the si download station should be accessed")
  print("\t-d:\tDebug - show extra debugging information (not normally useful)")
  print("\t-v:\tVerbose - show extra information about the workings of the program (sometimes useful)")
  print("\t-r:\tReplay a si stick - useful for a competitor who misregistered")
  print("\t-t:\tTesting run - only use in test environments")


############################################################
def string_to_boolean(string_to_convert):
  if (isinstance(string_to_convert, str)):
    return  not ((string_to_convert == "0") or (string_to_convert.lower() == "false") or (string_to_convert.lower() == "no"))
  elif (isinstance(string_to_convert, int)):
    return string_to_convert != 0
  elif (isinstance(string_to_convert, (list, tuple, dict))):
    return len(string_to_convert) != 0
  else:
    return False


############################################################
def read_ini_file():
  ini_file_contents = {}

  with open("./read_results.ini", "r") as INI_FILE:
    for file_line in INI_FILE:
      file_line = file_line.strip()
      if (debug):
        print("Found " + file_line + " in the ini file.")
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
      if (verbose or debug):
        print ("The value of " + split_elements[0] + " is " + split_elements[1] + "")

  return ini_file_contents


############################################################################
def get_event(event_key):
  output = make_url_call(MANAGE_EVENTS, "key=" + event_key + "&recent_event_timeout=12h")
  #output = make_url_call(MANAGE_EVENTS, "key=" + event_key + "&recent_event_timeout=120d")
  event_matches_list = re.findall(r"view_results.php\?.*</a>", output)

  if (debug):
    print("Found " + str(len(event_matches_list)) + " events from the website.")

  if (len(event_matches_list) == 0):
    if (verbose or debug):
      print("No currently open (actively ongoing) events found.")
    return("", "")
  elif (len(event_matches_list) == 1):
    match = re.search(r"(event-[0-9a-f]+).*>Results for (.*?)<", event_matches_list[0])
    if (match):
      if (verbose or debug):
        print("Found single matching event (" + match.group(1) + ") named " + match.group(2) + ".")
      return match.group(1,2)
    else:
      if (verbose or debug):
        print("No currently open (actively ongoing) events found.")

      if (debug):
        print("ERROR: Found single event match " + event_matches_list[0] + " but cannot determine event or readable name.")

      return ("","")
  else:
     event_ids = map(lambda event_possible_match: re.search(r"(event-[0-9a-f]+)", event_possible_match).group(1), event_matches_list)
     event_names = map(lambda event_possible_match: re.search(r">Results for (.*?)<", event_possible_match).group(1), event_matches_list)

     if debug:
       print(event_ids)
       print(event_names)

     print("Please choose the event: ")
     while True:
       for i in range(len(event_ids)):
         print("{:2d}: {:s} {:s}".format(i + 1, event_names[i], ("(" + event_ids[i] + ")") if verbose else ""))

       user_input = raw_input("Your choice: ")
       if (debug):
         print("Read {} from keyboard, type is {}.".format(user_input, type(user_input)))
       user_input = user_input.strip()

       try:
         user_input_as_num = int(user_input)
       except ValueError:
         user_input_as_num = 0

       if ((re.match("^[0-9]+$", user_input) == None) or (user_input_as_num == 0) or (user_input_as_num > len(event_ids))):
         print("\n\nYour choice \"{}\" is not valid, please try again.".format(user_input))
       else:
         break

     return(event_ids[user_input_as_num - 1], event_names[user_input_as_num - 1])

  return ("","")


###############################################################
def upload_results(event_key, event, qr_result_string):
  web_site_string = base64.standard_b64encode(qr_result_string)
  web_site_string = re.sub("\n", "", web_site_string)
  web_site_string = re.sub(r"=", "%3D", web_site_string)

  output = make_url_call(FINISH_COURSE, "event={}&key={}&si_stick_finish={}".format(event, event_key, web_site_string))

  results = []
  match = re.search("(Cannot find.*)", output)
  if (match != None):
    results.append(match.group(1))

  match = re.search("(Results for:.*)", output)
  if (match != None):
    results.append(match.group(1))

  match = re.search("(Second scan.*)", output)
  if (match != None):
    results.append(match.group(1))

  return results

###############################################################
def replay_stick_results(event_key, event, stick_to_replay):
# logline format is:
#   2108369;1200,start:1200,finish:1600,101:1210,102:1260,104:1350,106:1480,110:1568
# So the si stick matches at the start of the line, adding the semicolon to eliminate partial matches
  found_stick = False
  stick_at_log_start = "{};".format(stick_to_replay)
  with open("{}-results.log".format(event), "r") as LOGFILE:
    for result_line in LOGFILE:
      if debug:
        print ("Checking {}.".format(result_line))

      if (result_line.startswith(stick_at_log_start)):
        if debug:
          print("Found match with {}.".format(stick_at_log_start))

        found_stick = True
        web_results = upload_results(event_key, event, result_line.strip())  # Remove the trailing newline
        print "\n".join(web_results)


  if not found_stick:
    print("ERROR: No results found for SI stick {}.".format(stick_to_replay))




###############################################################

fake_entries = []
fake_entries.append({SI_STICK_KEY : 2108369, SI_START_KEY : 200, SI_FINISH_KEY : 600, SI_CONTROLS_KEY : ["101:210", "102:260", "104:350", "106:480", "110:568"]})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : 2108369, SI_START_KEY : 1200, SI_FINISH_KEY : 1600, SI_CONTROLS_KEY : ["101:1210", "102:1260", "104:1350", "106:1480", "110:1568"]})
def read_fake_results():
  if len(fake_entries) > 0:
    return fake_entries.pop()
  else:
    return {SI_STICK_KEY : None}

###############################################################
def get_24hour_timestamp(punch_time):
# Take a datetime object, from reading the si card, and convert to seconds since midnight
  #print "Datetime object looks like: {}".format(dir(punch_time))
  #return (datetime.timestamp(punch_time))
  return ((punch_time.hour * 3600) + (punch_time.minute * 60) + punch_time.second)
  

###############################################################
def get_sireader(serial_port_name, verbose):
#SIReader only supports the so called "Extended Protocol" mode. If your
#base station is not in this mode you have to change the protocol mode
#first::
#
#  # change to extended protocol mode
#  si.set_extended_protocol()
#
#To use a SportIdent base station for card readout::


# connect to base station, the station is automatically detected,
# if this does not work, give the path to the port as an argument
# see the pyserial documentation for further information.
  try:
    if (serial_port_name != ""):
      si = SIReaderReadout(port=serial_port_name)
    else:
      si = SIReaderReadout()
  except SIReaderException as sire:
    si = None
    if verbose:
      print "Cannot find si download station, reason: {}".format(sire)

  return si

#################################################################
def read_results(si_reader):
# wait for a card to be inserted into the reader
  try:
    if not si_reader.poll_sicard():
      return({SI_STICK_KEY : None})
  except SIReaderException as sire:
    si_reader.ack_sicard()
    print "Bad card download, error {}.".format(sire)
    return ({SI_STICK_KEY : None})

# some properties are now set
  card_number = si_reader.sicard
  card_type = si_reader.cardtype

# read out card data
  try:
    card_data = si_reader.read_sicard()
  except SIReaderException as sire:
    print "Bad card ({}) download, error {}.".format(card_number, sire)
    return({SI_STICK_KEY : None})

# beep
  si_reader.ack_sicard()
  
# Wait for the card to be removed from the reader
  while not si_reader.poll_sicard():
    time.sleep(1)

# Convert to the format expected by the rest of the program
# Check for old sticks which only use 12 hour time, which have some trouble if
# the event starts before noon and ends after noon
  if card_data['start'] != None:
    start_timestamp = get_24hour_timestamp(card_data['start'])
  else:
     start_timestamp = 0

  if card_data['finish'] != None:
    finish_timestamp = get_24hour_timestamp(card_data['finish'])
  else:
    finish_timestamp = 0
	
  array_of_punches = []
  if ((finish_timestamp < start_timestamp) and (start_timestamp < TWELVE_HOURS_IN_SECONDS)):
    # Anomaly detected!  Adjust any timestamp less than the start forward by 12 hours
    # First convert the tuples of datetime objects to just a value in seconds
    # Then adjust the appropriate entries (those less than the start timestamp) by 12 hours
    # Then format it as : separated string items
    #
    # Update: I think the sireader code already handles this situation, so I don't think this case
    # will ever occur.  Leaving this in, just in case though.
    finish_timestamp += TWELVE_HOURS_IN_SECONDS
    orig_punches = []
    new_punches = []
    orig_punches = map(lambda punch: (punch[0], get_24hour_timestamp(punch[1])), card_data['punches'])
    new_punches = map(lambda punch: (punch[0], punch[1] + TWELVE_HOURS_IN_SECONDS if (punch[1] < start_timestamp) else punch[1]), orig_punches)
    array_of_punches = map(lambda punch: "{}:{}".format(str(punch[0]), str(punch[1])), new_punches)
    print "Old card detected and handled!"
  else:
    array_of_punches = map(lambda punch: "{}:{}".format(str(punch[0]), str(get_24hour_timestamp(punch[1]))), card_data['punches'])

  #print "Here is the array of punches {}.".format(array_of_punches)

  entry_to_return = {SI_STICK_KEY : card_number, SI_START_KEY : start_timestamp, SI_FINISH_KEY : finish_timestamp, SI_CONTROLS_KEY : array_of_punches}
  
  return(entry_to_return)



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

##################################################################
def make_url_call(php_script_to_call, params):
  if (testing_run and os.path.isfile("../" + php_script_to_call)):
    param_pair_list = re.split("&", params)
    param_kv_list = map(lambda param_pair: re.split("=", param_pair), param_pair_list)
    artificial_get_line_list = map(lambda param_kv: "GET {} {}".format(param_kv[0], param_kv[1]), param_kv_list)
    artificial_get_file_content = "\n".join(artificial_get_line_list)
    with open("./artificial_input", "w") as output_file:
      output_file.write(artificial_get_file_content)
    cmd = "php ../{}".format(php_script_to_call)
  else:
    cmd = "curl -s \"{}/{}?{}\"".format(url, php_script_to_call, params)

  if (debug):
    print("Running " + cmd)

  try:
    output = subprocess.check_output(cmd, shell=True)
  except subprocess.CalledProcessError as cpe:
    output = cpe.output

  if (debug):
    print("Command output is: " + output)

  return output

######################################################


##89898989;-:-:--:--:--;-:-:--:--:--;-:-:19:34:14;-:-:19:34:43;101;-:-:19:34:23;102;-:-:19:34:36;103;-:-:19:34:38;104;-:-:19:34:40;105;-:-:19:34:41;
initializations = read_ini_file()

event = ""
event_name = ""
event_key = ""
serial_port = ""
download_only = False
event_found = False

if ("key" in initializations):
  event_key = initializations["key"]

if ("url" in initializations):
  url = initializations["url"]

if ("debug" in initializations) and not debug:
  debug = string_to_boolean(initializations["debug"])

if ("verbose" in initializations) and not verbose:
  verbose = string_to_boolean(initializations["verbose"])

if ("testing_run" in initializations):
  testing_run = string_to_boolean(initializations["testing_run"])

if ("serial_port" in initializations):
  serial_port = initializations["serial_port"]

replay_si_stick = 0

try:
  opts, args = getopt.getopt(sys.argv[1:], "e:k:u:s:dvjtrh")
except getopt.GetoptError:
  print("Parse error on command line.")
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
  elif opt == "-s":
    serial_port = arg
  elif opt == "-d":
    debug = 1
  elif opt == "-j":
    download_only = True
  elif opt == "-v":
    verbose = 1
  elif opt == "-t":
    testing_run = 1
  elif opt == "-r":
    replay_si_stick = 1
  else:
    print ("ERROR: Unknown option {}.".format(opt))
    usage()
    sys.exit(1)

if debug:
  print("Debug is enabled.")

if verbose:
  print("Verbose is enabled.")

if (event_key == ""):
  usage()
  sys.exit(1)

if (event == ""):
  result_tuple = get_event(event_key)
  event_name = result_tuple[1]
  event = result_tuple[0]
  print("Processing results for event {} ({}).".format(event_name, event))

if ((event == "") or (event_key == "")):
  if download_only:
    event="download_only"
    event_found = False
  else:
    usage()
    sys.exit(1)
else:
  event_found = True
  
## Ensure that the event specified is valid
if event_found:
  output = make_url_call(VIEW_RESULTS, "event={}&key={}".format(event, event_key))
  if ((re.search("No such event found {}".format(event), output) != None) or (re.search(r"Show results for", output) == None)):
    print("Event {} not found, please check if event {} and key {} are valid.".format(event, event, event_key))
    sys.exit(1)


if (replay_si_stick):
  si_stick_to_replay = raw_input("Enter the si stick to replay: ")
  si_stick_to_replay = si_stick_to_replay.strip()
  if (si_stick_to_replay == ""):
    print "ERROR: Must enter si stick when using the -r option, re-run program to try again."
    sys.exit(1)
  else:
    replay_stick_results(event_key, event, si_stick_to_replay)
    sys.exit(0)


if not testing_run:
  si_reader = get_sireader(serial_port, verbose)
  if (si_reader == None):
    print "ERROR: Cannot find si download station, is it plugged in?"
    if (serial_port != ""):
      print "\tAttempted to read from {}".format(serial_port)
    sys.exit(1)
else:
  si_reader = None

loop_count = 0
while True:
  if not testing_run:
    si_stick_entry = read_results(si_reader)
  else:
    si_stick_entry = { SI_STICK_KEY : None }

  if ((loop_count % 60) == 0):
    time_tuple = time.localtime(None)
    sys.stdout.write("Awaiting new results at: {:02d}:{:02d}:{:02d}\r".format(time_tuple.tm_hour, time_tuple.tm_min, time_tuple.tm_sec))
    sys.stdout.flush()

  if si_stick_entry[SI_STICK_KEY] != None:
    if verbose:
      print("\nFound new key: {}".format(si_stick_entry[SI_STICK_KEY]))
    else:
      print("\n")

    upload_entry_list = [ "{:d};{:d}".format(si_stick_entry[SI_STICK_KEY], si_stick_entry[SI_START_KEY]) ]
    upload_entry_list.append("start:{:d}".format(si_stick_entry[SI_START_KEY]))
    upload_entry_list.append("finish:{:d}".format(si_stick_entry[SI_FINISH_KEY]))
    upload_entry_list.extend(si_stick_entry[SI_CONTROLS_KEY])
    qr_result_string = ",".join(upload_entry_list)
    if verbose:
      print "Got results {} for si_stick {}.".format(qr_result_string, si_stick_entry[SI_STICK_KEY])

    with open("{}-results.log".format(event), "a") as LOGFILE:
      LOGFILE.write(qr_result_string + "\n")

    if event_found:
      results = upload_results(event_key, event, qr_result_string)
      print "\n".join(results)
    else:
      total_time = si_stick_entry[SI_FINISH_KEY] - si_stick_entry[SI_START_KEY]
      hours = total_time / 3600
      minutes = (total_time - (hours * 3600)) / 60
      seconds = (total_time - (hours * 3600) - (minutes * 60))
      print "Downloaded results for si_stick {}, time was {}h:{}m:{}s ({}).".format(si_stick_entry[SI_STICK_KEY], hours, minutes, seconds, total_time)

  if testing_run:
    break   # While testing, no need to wait for more results

  time.sleep(1)
  loop_count += 1



#while (1) {
#
#  
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
