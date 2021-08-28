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
from base64 import b64encode
import urllib


# Initialize a few helpful constants
verbose = 0
debug = 0
testing_run = 0
url = "http://www.mkoconnell.com/OMeet/not_there"
club_name = "NEOC"

# URLs for the web site
VIEW_RESULTS = "OMeet/view_results.php"
FINISH_COURSE = "OMeet/finish_course.php"
REGISTER_COURSE = "OMeetRegistration/register.php"
REGISTER_COMPETITOR = "OMeetRegistration/register_competitor.php"
MANAGE_EVENTS = "OMeetMgmt/manage_events.php"
SI_LOOKUP = "OMeetWithMemberList/stick_lookup.php"

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

  try:
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
  except IOError:
    print("No file read_results.ini found (or not readable), continuing anyway")

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
def check_for_registered_si_stick(event_key, event, si_stick):
  output = make_url_call(SI_LOOKUP, "key={}&si_stick={}".format(event_key, si_stick))

  if debug or verbose:
    print "Got results from si lookup: {}".format(output)
  
  name = None
  member_id = None
  email = None
  
  match = re.search("^<p>Welcome (.*).$", output, re.MULTILINE)
  if (match != None):
    name = match.group(1)

  match = re.search("^<input type=hidden name=\"member_id\" value=\"([0-9]+)\"/>", output, re.MULTILINE)
  if (match != None):
    member_id = match.group(1)

  match = re.search("^<input type=hidden name=\"member_email\" value=\"([a-zA-Z_@0-9.-]+)\"/>", output, re.MULTILINE)
  if (match != None):
    email = match.group(1)

  return (name, member_id, email)

###############################################################
def get_course_list(event_key, event):
  output = make_url_call(VIEW_RESULTS, "key={}&event={}".format(event_key, event))
  
  if ((re.search("No such event found {}".format(event), output) != None) or (re.search(r"Show results for", output) == None)):
    return None

  if debug:
    print "Got results from view results: {}".format(output)
  
  results = re.findall("view_results.*course=([0-9]+-.*)\">(.*)</a>", output, re.MULTILINE)
  
  if verbose or debug:
    print "Courses found for {} are: {}".format(event, results)

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
  if not si_reader.poll_sicard():
    return({SI_STICK_KEY : None})

# some properties are now set
  card_number = si_reader.sicard
  card_type = si_reader.cardtype

# read out card data
  card_data = si_reader.read_sicard()

# beep
  si_reader.ack_sicard()
  
# Wait for the card to be removed from the reader
  while not si_reader.poll_sicard():
    time.sleep(1)

# Convert to the format expected by the rest of the program
# Check for old sticks which only use 12 hour time, which have some trouble if
# the event starts before noon and ends after noon
  start_timestamp = 0
  finish_timestamp  = 0
  if card_data['start'] != None:
    start_timestamp = get_24hour_timestamp(card_data['start'])

  if card_data['finish'] != None:
    finish_timestamp = get_24hour_timestamp(card_data['finish'])
	
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

if ("club_name" in initializations):
  club_name = initializations["club_name"]


try:
  opts, args = getopt.getopt(sys.argv[1:], "e:k:u:s:c:dvtrh")
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
  elif opt == "-c":
    club_name = arg
  elif opt == "-d":
    debug = 1
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
  print("Processing registrations for event {} ({}).".format(event_name, event))

if ((event == "") or (event_key == "")):
  usage()
  sys.exit(1)
  
## Ensure that the event specified is valid
course_list = get_course_list(event_key, event)
if len(course_list) == 0:
  print("Event {} does not appear to be valid (no courses found), please check if event {} and key {} are valid.".format(event, event, event_key))
  sys.exit(1)
printable_course_list = ", ".join(map(lambda course_tuple: course_tuple[1], course_list))

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
    sys.stdout.write("Awaiting new registration at: {:02d}:{:02d}:{:02d}\r".format(time_tuple.tm_hour, time_tuple.tm_min, time_tuple.tm_sec))
    sys.stdout.flush()

  if si_stick_entry[SI_STICK_KEY] != None:
    if verbose:
      print("\nFound new si card: {}".format(si_stick_entry[SI_STICK_KEY]))
    else:
      print("\n")

    results = check_for_registered_si_stick(event_key, event, si_stick_entry[SI_STICK_KEY])
	
    # If no member id, then this SI stick is not registered
    if results[1] == None:
      print ("No registered member for SI unit {}.".format(si_stick_entry[SI_STICK_KEY]))
      if verbose or debug:
        print ("Got results {}.".format(results))
    else:
      # Valid member id, see what course to run
      print ("Found member {}, please type prefix of desired course.".format(results[0]))
      course_prefix = raw_input(printable_course_list + ": ")
      course_prefix = course_prefix.strip()
      lowercase_course_prefix = course_prefix.lower()
      matches = []
      map (lambda course_tuple: matches.append(course_tuple) if course_tuple[1].lower().startswith(lowercase_course_prefix) else None, course_list)
      if len(matches) == 0:
        print ("No matching course found for {}, please retry.".format(course_prefix))
      elif len(matches) > 1:
        print ("Too many matches for {}: {}, please retry.".format(course_prefix, ", ".join(map(lambda ct: ct[1], matches))))
      else:
        registration_list = ["first_name", b64encode(results[0]),
                             "last_name", b64encode(""),
                             "club_name", b64encode(club_name),
                             "si_stick", b64encode(str(si_stick_entry[SI_STICK_KEY])),
                             "email_address", b64encode(results[2]),
                             "safety_info", b64encode("On file"),
                             "registration", b64encode("Optimized registration"),
                             "member_id", b64encode(results[1]),
                             "is_member", b64encode("yes") ]

        registration_params = "key={}&event={}&course={}&registration_info={}&competitor_name={}"\
                               .format(event_key, event, urllib.quote(matches[0][0]), ",".join(registration_list), urllib.quote(results[0]))
        if debug: print("Attempting to register {} with params {}.".format(results[0], registration_params))
        
        output = make_url_call(REGISTER_COMPETITOR, registration_params)
        if debug: print ("Results of web call {}.".format(output))
              
        match = re.search("<p>Registration complete:", output, re.MULTILINE)
        if (match != None):
          print ("{} successfully registered on {}.".format(results[0], matches[0][1]))

        match = re.search("ERROR.*$", output, re.MULTILINE)
        if match != None:
          print("Unsuccessful registration, please retry via a browser\nDetails: {}.".format(match.group(1)))
	  
 

  if testing_run:
    break   # While testing, no need to wait for more results

  time.sleep(1)
  loop_count += 1
