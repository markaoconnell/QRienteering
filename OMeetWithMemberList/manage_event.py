import tkinter as tk
import tkinter.ttk as ttk
from threading import Thread
import time
import sys, getopt
import os
import os.path
import subprocess
import re
import base64
from datetime import datetime
import urllib.parse

progress_label = None
mode_label = None
mode_button = None
download_mode = True
exit_all_threads = False
discovered_courses = [ ]
open_frames = []
root = None
status_frame = None
scrollable_status_frame = None



# Initialize a few helpful constants
verbose = 0
debug = 0
testing_run = 0
continuous_testing = 0
url = "http://www.mkoconnell.com/OMeet/not_there"
fake_offline_event = "offline_downloads"
use_fake_read_results = True
use_real_sireader = False

if (not 'NO_SI_READER_IMPORT' in os.environ) and use_real_sireader:
  from sireader import SIReader, SIReaderReadout, SIReaderControl, SIReaderException

# URLs for the web site
VIEW_RESULTS = "OMeet/view_results.php"
FINISH_COURSE = "OMeet/finish_course.php"
REGISTER_COURSE = "OMeetRegistration/register.php"
MANAGE_EVENTS = "OMeetMgmt/manage_events.php"
REGISTER_COMPETITOR = "OMeetRegistration/register_competitor.php"
SI_LOOKUP = "OMeetWithMemberList/stick_lookup.php"

TWELVE_HOURS_IN_SECONDS = (12 * 3600)

# Keys for the si_stick dict (should really convert this to a named tuple)
SI_STICK_KEY = r"si_stick"
SI_START_KEY = r"start_timestamp"
SI_FINISH_KEY = r"finish_timestamp"
SI_CONTROLS_KEY = r"qr_controls"

def usage():
  print("Usage: " + sys.argv[0])
  print("Usage: " + sys.argv[0] + " [-e event] [-k key] [-u url_of_QR_web_site] [-s serial port for si download station] [-djvrt]")
  print("\t-e:\tEvent identifier")
  print("\t-k:\tKey for the series (from the administrator)")
  print("\t-u:\tURL for the web site where the results are posted")
  print("\t-s:\tSerial port where the si download station should be accessed")
  print("\t-j:\tRun in local mode - save si stick information to a local file for later replay (rarely useful)")
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
    print("No read_results.ini file found (or not readable), continuing anyway.");

  return ini_file_contents


############################################################################
def get_event(event_key):
  output = make_url_call(MANAGE_EVENTS, "key=" + event_key + "&recent_event_timeout=12h")
  #output = make_url_call(MANAGE_EVENTS, "key=" + event_key + "&recent_event_timeout=120d")
  #print (f"Call to manage_events returned {output}")
  event_matches_list = re.findall(r"####,[A-Z]*_EVENT,.*", output)

  if (debug):
    print("Found " + str(len(event_matches_list)) + " events from the website.")

  if (len(event_matches_list) == 0):
    if (verbose or debug):
      print("No currently open (actively ongoing) events found.")
    return("", "")
  elif (len(event_matches_list) == 1):
    elements = event_matches_list[0].split(",")
    #match = re.search(r"(event-[0-9a-f]+).*>Results for (.*?)<", event_matches_list[0])
    if (verbose or debug):
      print("Found single matching event (" + elements[2] + ") named " + elements[3] + ".")
    root.after(1, lambda: have_event(None, [ elements ], 0))
  else:
     #event_ids = map(lambda event_possible_match: re.search(r"(event-[0-9a-f]+)", event_possible_match).group(1), event_matches_list)
     #event_names = map(lambda event_possible_match: re.search(r">Results for (.*?)<", event_possible_match).group(1), event_matches_list)
     event_ids = list(map(lambda event_comment: event_comment.split(","), event_matches_list))

     if debug:
       event_strings = map(lambda event_from_site: f"{event_from_site[2]} -> " + base64.standard_b64decode(event_from_site[3]).decode("utf-8"), event_ids)
       print("\n".join(event_strings))


     choice_frame = tk.Frame(root)
     choice_prompt = tk.Label(choice_frame, text="Please choose an event:")
     choice_prompt.pack(side=tk.TOP)
     chosen_event = tk.IntVar(choice_frame, -1)
     for index, possible_event in enumerate(event_ids):
        #print(f"Choice {index} is {possible_event[2]}\n")
        this_choice = tk.Radiobutton(choice_frame, text=base64.standard_b64decode(possible_event[3]).decode("utf-8"), value=index, var=chosen_event)
        this_choice.pack(side=tk.TOP)

     choice_button = tk.Button(choice_frame, text="Use chosen event", command=lambda: have_event(choice_frame, event_ids, chosen_event.get()))
     choice_button.pack(side=tk.TOP)
     choice_frame.pack(side=tk.TOP)


##############################################################
def have_event(choice_frame, event_list, chosen_event):
    global event, event_key
    if choice_frame != None:
        choice_frame.pack_forget()
        choice_frame.destroy()
    if chosen_event != -1:
        event_name = base64.standard_b64decode(event_list[chosen_event][3]).decode("utf-8")
        root.title(f"QRienteering download station for: {event_name}")
        mode_label.configure(text="Validating event and associated courses")
        event = event_list[chosen_event][2]
        root.after(1, lambda: get_courses(event, event_key))
    else:
        root.title("QRienteering download station - no event selected")
        error_label = tk.Label(root, text = "No event selected, exiting").pack(side=tk.TOP)
        mode_label.configure(text="Error - no event selected")
        root.after(10000, lambda: sys.exit(1))


###############################################################
def get_courses(event, event_key):
    global discovered_courses
    output = make_url_call(VIEW_RESULTS, "event={}&key={}".format(event, event_key))
    if (re.search("####,", output) == None):
        print(f"Event {event} not found, please check if event {event} and key {event_key} are valid.")
        sys.exit(1)

    if re.search(f"####,Event,{event},", output) == None:
        print(f"Event {event} not found, please check if event {event} and key {event_key} are valid.")
        sys.exit(1)

    match = re.search("(####,CourseList,.*)", output)
    if match == None:
        print(f"Cannot find course list for {event}, is it a valid event?")
        sys.exit(1)

    courses = match.group(1).split(",")[2:]
    discovered_courses = list(map(lambda entry: (entry.lstrip("0123456789-"), entry), courses))

    mode_label.configure(text="In Download mode")
    mode_button.configure(state=tk.NORMAL)

    print ("\n".join(map(lambda s: s[0] + " -> " + s[1], discovered_courses)))
    print ("\n")

    create_status_frame()
    root.after(1, start_sireader_thread)


###############################################################
def upload_results(stick, event_key, event, qr_result_string):
  web_site_string = base64.standard_b64encode(qr_result_string.encode("utf-8")).decode("utf-8")
  web_site_string = re.sub("\n", "", web_site_string)
  web_site_string = re.sub(r"=", "%3D", web_site_string)

  output = make_url_call(FINISH_COURSE, "event={}&key={}&si_stick_finish={}".format(event, event_key, web_site_string))

  name = "Unknown"
  match = re.search(r"####,RESULT,(.*)", output)
  if match != None:
      finish_entries = match.group(1).split(",")
      name = base64.standard_b64decode(finish_entries[0]).decode("utf-8")
      course = finish_entries[1]
      time_taken = int(finish_entries[2])

      output_string = ""
      if time_taken > 3600:
          hours = time_taken // 3600
          time_taken %= 3600
          output_string += f"{hours:02d}h:"
      if (time_taken > 60) or (output_string != ""):
          minutes = time_taken // 60
          time_taken %= 60
          output_string += f"{minutes:02d}m:"
      output_string += f"{time_taken:02d}s"
      upload_status_string = f"{name} finished {course} in {output_string} - "
  else:
      upload_status_string = f"Error, upload of {stick} failed - "

  error_list = re.findall(r"####,ERROR,.*", output)
  if (len(error_list) == 0):
      is_error = False
      upload_status_string += "OK"
  else:
      error_list = list(map(lambda entry: entry.split(",")[2], error_list))
      is_error = True
      upload_status_string += "\n" + "\n".join(error_list)

  return(name, upload_status_string, is_error)

def upload_initial_results(stick, event, event_key, qr_result_string):
  result_tuple = upload_results(stick, event, event_key, qr_result_string)
  make_status(status_frame, result_tuple[0], stick, result_tuple[1], result_tuple[2], qr_result_string)


###############################################################
def replay_stick_results(event_key, event, stick_to_replay):
# logline format is:
#   2108369;1200,start:1200,finish:1600,101:1210,102:1260,104:1350,106:1480,110:1568
# So the si stick matches at the start of the line, adding the semicolon to eliminate partial matches
  found_stick = False
  stick_at_log_start = "{};".format(stick_to_replay)
  processing_offline_results = (stick_to_replay == "offline")
  
  # read from either the event log or the offline log
  if processing_offline_results:
    logfile_name = fake_offline_event + "-results.log"
  else:
    logfile_name = "{}-results.log".format(event)

  with open(logfile_name, "r") as LOGFILE:
    for result_line in LOGFILE:
      if debug:
        print ("Checking {}.".format(result_line))

      if (processing_offline_results or result_line.startswith(stick_at_log_start)):
        if debug and not processing_offline_results:
          print("Found match with {}.".format(stick_at_log_start))

        found_stick = True
        web_results = upload_results(stick_to_replay, event_key, event, result_line.strip())  # Remove the trailing newline
        print ("\n".join(web_results))


  if not found_stick:
    print("ERROR: No results found for SI stick {}.".format(stick_to_replay))

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
      print (f"Cannot find si download station, reason: {sire}")

  return si

#################################################################
def read_results(si_reader):
# wait for a card to be inserted into the reader
  try:
    if not si_reader.poll_sicard():
      return({SI_STICK_KEY : None})
  except SIReaderException as sire:
    si_reader.ack_sicard()
    print (f"Bad card download, error {sire}.")
    return ({SI_STICK_KEY : None})

# some properties are now set
  card_number = si_reader.sicard
  card_type = si_reader.cardtype

# read out card data
  no_data = False
  try:
    card_data = si_reader.read_sicard()
  except SIReaderException as sire:
    print (f"Bad card ({card_number}) download, error {sire}.")
    no_data = True
    return({SI_STICK_KEY : None})

# beep
  si_reader.ack_sicard()
  
# Wait for the card to be removed from the reader
  while not si_reader.poll_sicard():
    time.sleep(1)

  if no_data:
    print (f"No data found on stick {card_number}.")
    return({SI_STICK_KEY : None})
  

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
    print (f"No finish timestamp on stick {card_number} - please scan finish and then download.")
	
  array_of_punches = []
  if ((finish_timestamp < start_timestamp) and (finish_timestamp < TWELVE_HOURS_IN_SECONDS)):
    # Anomaly detected!  Adjust any timestamp less than the start forward by 12 hours
    # First convert the tuples of datetime objects to just a value in seconds
    # Then adjust the appropriate entries (those less than the start timestamp) by 12 hours
    # Then format it as : separated string items
    if (finish_timestamp != 0): finish_timestamp += TWELVE_HOURS_IN_SECONDS
    orig_punches = []
    new_punches = []
    orig_punches = map(lambda punch: (punch[0], get_24hour_timestamp(punch[1])), card_data['punches'])
    new_punches = map(lambda punch: (punch[0], punch[1] + TWELVE_HOURS_IN_SECONDS if (punch[1] < start_timestamp) else punch[1]), orig_punches)
    array_of_punches = map(lambda punch: "{}:{}".format(str(punch[0]), str(punch[1])), new_punches)
    if verbose: print (f"Adjusting some times for {card_number} by twelve hours.")
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
    print("Command output is: " + output.decode("utf-8"))

  # convert to character representation before returning it
  return output.decode("utf-8")

###################################################################
class ScrollableFrame(ttk.Frame):
    def __init__(self, container, *args, **kwargs):
        super().__init__(container, *args, **kwargs)
        self.canvas = tk.Canvas(self)
        scrollbar = ttk.Scrollbar(self, orient="vertical", command=self.canvas.yview)
        self.scrollable_frame = ttk.Frame(self.canvas)

        self.scrollable_frame.bind(
            "<Configure>",
            lambda e: self.canvas.configure(
                scrollregion=self.canvas.bbox("all")
            )
        )


        self.canvas_frame = self.canvas.create_window((0, 0), window=self.scrollable_frame, anchor="nw")
        self.canvas.bind("<Configure>", lambda event: self.canvas.itemconfig(self.canvas_frame, width=event.width))
        #self.canvas.bind("<Configure>", self.InnerFrameResize)

        self.canvas.configure(yscrollcommand=scrollbar.set)

        scrollbar.pack(side="left", fill="y")
        self.canvas.pack(side="left", fill="both", expand=True)

    #def InnerFrameResize(self, event):
        #self.canvas.itemconfig(self.canvas_frame, width = event.width)

def switch_mode():
    global download_mode
    if download_mode:
        mode_label["text"] = "In Register mode"
        mode_button["text"] = "Switch to download mode"
    else:
        mode_label["text"] = "In Download mode"
        mode_button["text"] = "Switch to register mode"

    download_mode = not download_mode
    return

def make_status(enclosing_frame, name, stick, message, is_error, qr_result_string):
    root.after(1, lambda: make_status_on_mainloop(enclosing_frame, name, stick, message,  is_error, qr_result_string))
    return

def make_status_on_mainloop(enclosing_frame, name, stick, message, is_error, qr_result_string):
    result_frame = tk.LabelFrame(enclosing_frame)
    button_frame = tk.Frame(result_frame)
    label_frame = tk.Frame(result_frame)
    stick_label = tk.Label(label_frame, text=stick, borderwidth=2, relief=tk.SUNKEN)
    stick_status = tk.Label(label_frame, text=message)
    if is_error:
        stick_status["fg"] = "red"
    else:
        stick_status["fg"] = "green"
    stick_ack = tk.Button(button_frame, text="Close notification", command=result_frame.destroy)
    stick_register = tk.Button(button_frame, text="Register for new course")
    stick_replay = tk.Button(button_frame, text="Retry upload")
    buttons_to_disable = [stick_ack, stick_register, stick_replay]
    stick_replay.configure(command=lambda: replay_stick(stick, stick_status, buttons_to_disable, qr_result_string))
    stick_register.configure(command=lambda: registration_window(name, stick, stick_status, buttons_to_disable))
    if name == None:
        stick_register.configure(state = tk.DISABLED)
        buttons_to_disable = [ stick_ack, stick_replay ]
    stick_label.pack(side=tk.LEFT)
    stick_status.pack(side=tk.LEFT, fill=tk.X)
    stick_replay.pack(side=tk.LEFT)
    stick_register.pack(side=tk.LEFT, padx=5)
    stick_ack.pack(side=tk.RIGHT, padx=5)
    label_frame.pack(side=tk.TOP, fill=tk.X)
    button_frame.pack(side=tk.TOP, fill=tk.X)

    # Display the registration window before we actually display the status frame
    if not download_mode and name != None:
        for button in buttons_to_disable:
            button.configure(state=tk.DISABLED)
        registration_window(name, stick, stick_status, buttons_to_disable)

    result_frame.pack(side=tk.TOP, fill=tk.X, pady=5)

def registration_window(name, stick, stick_status_widget, buttons_to_disable):
    global open_frames

    for button in buttons_to_disable:
        button.configure(state=tk.DISABLED)

    registration_frame = tk.Tk()
    open_frames.append(registration_frame)
    registration_frame.geometry("300x300")
    registration_frame.title("Register entrant")

    choices_frame = tk.Frame(registration_frame)
    button_frame = tk.Frame(registration_frame)
    chosen_course = tk.StringVar(registration_frame, "unselected")
    registration_string = ""
    if name != None:
        registration_string = f"Register {name} ({stick})"
    else:
        registration_string = f"Register {stick} (name currently unknown)"
    info_label = tk.Label(choices_frame, text=registration_string)
    info_label.pack(side=tk.TOP)

    for course in discovered_courses:
        radio_button = tk.Radiobutton(choices_frame, text=course[0], value=course[1], variable=chosen_course)
        radio_button.pack(side=tk.TOP)

    ok_button = tk.Button(button_frame, text="Register for course", command=lambda: register_for_course(name, stick, stick_status_widget, buttons_to_disable, chosen_course, registration_frame))
    cancel_button = tk.Button(button_frame, text="Cancel", command=lambda: kill_registration_window(registration_frame, buttons_to_disable))

    ok_button.pack(side=tk.LEFT)
    cancel_button.pack(side=tk.LEFT)

    choices_frame.pack(side=tk.TOP)
    button_frame.pack(side=tk.TOP)

    registration_frame.protocol("WM_DELETE_WINDOW", lambda: kill_registration_window(registration_frame, buttons_to_disable))
    return


def register_for_course(name, stick, stick_status_widget, buttons_to_disable, chosen_course, enclosing_frame):
    global open_frames

    if name != None:
        message = f"Attempting to register {name} ({stick}) on " 
    else:
        message = f"Attempting to register member with SI unit {stick} on "

    stick_status_widget["text"] = message + chosen_course.get().lstrip("0123456789-")
    enclosing_frame.destroy()
    open_frames.remove(enclosing_frame)

    registration_thread = Thread(target=register_by_si_unit, args=(name, stick, stick_status_widget, buttons_to_disable, chosen_course.get()))
    registration_thread.start()
    return



#######################################################################################
def lookup_si_unit(stick):

  if exit_all_threads: return
  output = make_url_call(SI_LOOKUP, "key={}&si_stick={}".format(event_key, stick))
  if exit_all_threads: return

  if debug or verbose:
    print ("Got results from si lookup: {}".format(output))
  
  member_match = re.search(r"####,MEMBER_ENTRY,(.*)", output)
  if member_match == None:
      return None

  member_elements = member_match.group(1).split(",")
  found_name = base64.standard_b64decode(member_elements[0]).decode("utf-8")
  found_id = member_elements[1]
  found_email = member_elements[2]
  club_name = member_elements[3]

  return({ "name" : found_name, "id" : found_id, "email_address" : found_email, "club_name" : club_name })

#######################################################################################
def register_by_si_unit(name, stick, stick_status_widget, buttons_to_disable, chosen_course):

  if exit_all_threads: return
  output = make_url_call(SI_LOOKUP, "key={}&si_stick={}".format(event_key, stick))
  if exit_all_threads: return

  if debug or verbose:
    print ("Got results from si lookup: {}".format(output))
  
  member_match = re.search(r"####,MEMBER_ENTRY,(.*)", output)
  if member_match == None:
      message = f"No member found for si unit {stick}, cannot register"
      root.after(1, lambda: update_status_window(message, stick_status_widget, buttons_to_disable, True))
      return

  member_elements = member_match.group(1).split(",")
  found_name = base64.standard_b64decode(member_elements[0]).decode("utf-8")
  found_id = member_elements[1]
  found_email = member_elements[2]
  club_name = member_elements[3]

  if (name != None) and (found_name != name):
      message = f"SI unit {stick} registered to {found_name} but current user is {name}, registration canceled"
      root.after(1, lambda: update_status_window(message, stick_status_widget, buttons_to_disable, True))
      return
  
  registration_list = ["first_name", base64.standard_b64encode(found_name.encode("utf-8")).decode("utf-8"),
                             "last_name", base64.standard_b64encode("".encode("utf-8")).decode("utf-8"),
                             "club_name", base64.standard_b64encode(club_name.encode("utf-8")).decode("utf-8"),
                             "si_stick", base64.standard_b64encode(str(stick).encode("utf-8")).decode("utf-8"),
                             "email_address", base64.standard_b64encode(found_email.encode("utf-8")).decode("utf-8"),
                             "safety_info", base64.standard_b64encode("On file".encode("utf-8")).decode("utf-8"),
                             "registration", base64.standard_b64encode("Optimized registration".encode("utf-8")).decode("utf-8"),
                             "member_id", base64.standard_b64encode(found_id.encode("utf-8")).decode("utf-8"),
                             "is_member", base64.standard_b64encode("yes".encode("utf-8")).decode("utf-8") ]

  quoted_course = urllib.parse.quote(chosen_course.encode("utf-8"))
  quoted_name = urllib.parse.quote(found_name.encode("utf-8"))
  registration_params = "key={}&event={}&course={}&registration_info={}&competitor_name={}"\
                               .format(event_key, event, quoted_course, ",".join(registration_list), quoted_name)
  if debug: print("Attempting to register {} with params {}.".format(found_name, registration_params))
        
  if exit_all_threads: return
  output = make_url_call(REGISTER_COMPETITOR, registration_params)
  if exit_all_threads: return
  if debug: print ("Results of web call {}.".format(output))
              
  registration_result = re.search(r"####,RESULT,(.*)", output)
  errors = re.findall(r"####,ERROR,(.*)", output)

  output_message = ""
  if registration_result == None:
      output_message = "Registration failed: "
      error_found = True
  else:
      output_message = registration_result.group(1) + " "
      error_found = False

  if (len(errors) > 0):
      output_message += "\n".join(errors)
      error_found = True

  root.after(1, lambda: update_status_window(output_message, stick_status_widget, buttons_to_disable, error_found))

  return

#####################################################################

def update_status_window(message, stick_status_widget, buttons_to_disable, is_error):
    stick_status_widget.configure(text=message, fg = "red" if is_error else "green")
    for button in buttons_to_disable:
        button.configure(state=tk.NORMAL)
    return



#####################################################################
def kill_registration_window(registration_window, buttons_to_disable):
    global open_frames
    registration_window.destroy()
    open_frames.remove(registration_window)
    for button in buttons_to_disable:
        button.configure(state=tk.NORMAL)


def replay_stick(stick, stick_status_widget, buttons_to_disable, qr_result_string):
    stick_status_widget["text"] = f"Replaying SI results for {stick}"
    for button in buttons_to_disable:
        button.configure(state=tk.DISABLED)
    replay_thread = Thread(target=replay_stick_thread, args=(stick, stick_status_widget, buttons_to_disable, qr_result_string))
    replay_thread.start()
    return

def replay_stick_thread(stick, stick_status_widget, buttons_to_enable, qr_result_string):
    interruptible_sleep(10)

    if exit_all_threads: return
    result_tuple = upload_results(stick, event_key, event, qr_result_string)
    if exit_all_threads: return

    stick_status_widget["text"] = result_tuple[1]
    stick_status_widget["fg"] = "red" if result_tuple[2] else "green"

    for button in buttons_to_enable:
      button.configure(state=tk.NORMAL)

    return

def interruptible_sleep(time_to_sleep):
    i = 0
    while (i < time_to_sleep):
        if exit_all_threads: return
        time.sleep(1)
        i += 1
    return

def slowly_add_status():
    interruptible_sleep(1)
    if exit_all_threads: return
    make_status(status_frame, "2108369", "Mark OConnell, 1h40m21s on Red, course complete", False)

    interruptible_sleep(5)
    if exit_all_threads: return
    make_status(status_frame, "5083959473", "Karen Yeowell, 1h25m21s on Red, course complete", False)

    interruptible_sleep(10)
    if exit_all_threads: return
    make_status(status_frame, "USD", "John OConnell, 2h25m21s on Red, DNF", True)

    interruptible_sleep(15)
    if exit_all_threads: return
    make_status(status_frame, "314159", "Billy OConnell, 1h40m21s on White, course complete", False)

    interruptible_sleep(15)
    if exit_all_threads: return
    make_status(status_frame, "271828", "Lydia OConnell, 25m21s on Red, course complete", False)

    interruptible_sleep(15)
    if exit_all_threads: return
    make_status(status_frame, "141421", "Janet Berrill, 1h15m31s on Yellow, DNF", True)

    interruptible_sleep(20)
    if exit_all_threads: return
    make_status(status_frame, "me-myself", "Judith Berrill, 1h45m31s on Yellow, course complete", False)

    interruptible_sleep(15)
    if exit_all_threads: return
    make_status(status_frame, "678234", "The sun also rises, 1h19m58s on Orange, course complete", False)

    interruptible_sleep(10)
    if exit_all_threads: return
    make_status(status_frame, "349821", "The Beatles, 1h05m01s on Brown, course complete", False)

    return

def kill_all_windows():
    global exit_all_threads, root

    exit_all_threads = True
    for open_window in open_frames:
        open_window.destroy()
    root.destroy()

def create_status_frame():
    global status_frame
    scrollable_status_frame = ScrollableFrame(root)
    scrollable_status_frame.pack(fill=tk.BOTH, side=tk.TOP, pady=10, expand = True)
    status_frame = scrollable_status_frame.scrollable_frame


###############################################################

fake_entries = []
fake_entries.append({SI_STICK_KEY : 5086148225, SI_START_KEY : 1200, SI_FINISH_KEY : 1600, SI_CONTROLS_KEY : ["101:1210", "102:1260", "104:1350", "106:1480", "110:1568"]})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : 5083959473, SI_START_KEY : 1200, SI_FINISH_KEY : 1600, SI_CONTROLS_KEY : ["101:1210", "102:1260", "104:1350", "106:1480", "110:1568"]})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
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
fake_entries.append({SI_STICK_KEY : 2108369, SI_START_KEY : 1200, SI_FINISH_KEY : 1600, SI_CONTROLS_KEY : ["101:1210", "102:1260", "104:1350", "106:1480", "110:1568"]})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
fake_entries.append({SI_STICK_KEY : None})
def read_fake_results():
  if len(fake_entries) > 0:
    return fake_entries.pop()
  else:
    sys.exit(0)
    return {SI_STICK_KEY : None}

def start_sireader_thread():
    sireader_thread = Thread(target=sireader_main)
    sireader_thread.start()

def sireader_main():
    run_offline = False
    if exit_all_threads: return
    if not testing_run and use_real_sireader:
      si_reader = get_sireader(serial_port, verbose)
      if (si_reader == None):
        progress_label.configure(text="ERROR: Cannot find si download station, is it plugged in?")
        if (serial_port != ""):
          print (f"\tAttempted to read from {serial_port}")
        sys.exit(1)
    else:
      si_reader = None
    
    loop_count = 0
    while True:
      if exit_all_threads: return
      if not testing_run and use_real_sireader:
        si_stick_entry = read_results(si_reader)
      elif use_fake_read_results:
        si_stick_entry = read_fake_results()
      else:
        si_stick_entry = { SI_STICK_KEY : None }
    
      if ((loop_count % 60) == 0):
        time_tuple = time.localtime(None)
        progress_label.configure(text="Awaiting new results at: {:02d}:{:02d}:{:02d}".format(time_tuple.tm_hour, time_tuple.tm_min, time_tuple.tm_sec))
    
      if si_stick_entry[SI_STICK_KEY] != None:
        if verbose:
          print(f"\nFound new key: {si_stick_entry[SI_STICK_KEY]}")
        else:
          print("\n")
    
        upload_entry_list = [ "{:d};{:d}".format(si_stick_entry[SI_STICK_KEY], si_stick_entry[SI_START_KEY]) ]
        upload_entry_list.append("start:{:d}".format(si_stick_entry[SI_START_KEY]))
        upload_entry_list.append("finish:{:d}".format(si_stick_entry[SI_FINISH_KEY]))
        upload_entry_list.extend(si_stick_entry[SI_CONTROLS_KEY])
        qr_result_string = ",".join(upload_entry_list)
        if verbose:
          print (f"Got results {qr_result_string} for si_stick {si_stick_entry[SI_STICK_KEY]}.")
    
        with open("{}-results.log".format(event), "a") as LOGFILE:
          LOGFILE.write(qr_result_string + "\n")
    
        # If the finish is 0, then the finish wasn't scanned - we've logged it but don't upload the result.
        # By editing the log file and replaying the SI stick, we can adjust the result afterwards if necessary.
        # Though the easiest is to have the competitor go and scan finish and then download again.
        if (si_stick_entry[SI_FINISH_KEY] != 0):
          if not run_offline:
            if exit_all_threads: return
            if download_mode:
                upload_initial_results(si_stick_entry[SI_STICK_KEY], event_key, event, qr_result_string)
            else:
                member_info_dict = lookup_si_unit(si_stick_entry[SI_STICK_KEY])
                if (member_info_dict != None):
                    make_status(status_frame, member_info_dict["name"], si_stick_entry[SI_STICK_KEY], f"Looking up member for unit {si_stick_entry[SI_STICK_KEY]}", False, qr_result_string)
                else:
                    make_status(status_frame, None, si_stick_entry[SI_STICK_KEY], f"Could not find member for SI unit {si_stick_entry[SI_STICK_KEY]}", True, qr_result_string)
            if exit_all_threads: return
          else:
            total_time = si_stick_entry[SI_FINISH_KEY] - si_stick_entry[SI_START_KEY]
            hours = total_time / 3600
            minutes = (total_time - (hours * 3600)) / 60
            seconds = (total_time - (hours * 3600) - (minutes * 60))
            print (f"Downloaded results for si_stick {si_stick_entry[SI_STICK_KEY]}, time was {hours}h:{minutes}m:{seconds}s ({total_time}).")
            sys.stdout.flush()
        else:
          print ("Splits downloaded but no finish time found - punch finish and download again.")
          sys.stdout.flush()
    
      if testing_run and not continuous_testing:
        break   # While testing, no need to wait for more results
    
      time.sleep(1)
      loop_count += 1


#########################################################
# Main program

root = tk.Tk()
root.geometry("750x500");
root.title("QRienteering SI download station")
mode_frame = tk.Frame(root, highlightbackground="blue", highlightthickness=5)
mode_frame.pack(fill=tk.X, side=tk.TOP)

mode_label = tk.Label(mode_frame, text="Starting up, finding event")
mode_label.pack(side=tk.LEFT) 
exit_button = tk.Button(mode_frame, text="Exit", command=kill_all_windows)
exit_button.pack(side=tk.RIGHT)
mode_button = tk.Button(mode_frame, text="Switch to register mode", command=switch_mode, state=tk.DISABLED)
mode_button.pack(side=tk.RIGHT, padx=5)
progress_label = tk.Label(mode_frame, text="Starting up")
progress_label.pack(side=tk.BOTTOM, pady=5)

root.protocol("WM_DELETE_WINDOW", kill_all_windows)



##89898989;-:-:--:--:--;-:-:--:--:--;-:-:19:34:14;-:-:19:34:43;101;-:-:19:34:23;102;-:-:19:34:36;103;-:-:19:34:38;104;-:-:19:34:40;105;-:-:19:34:41;
initializations = read_ini_file()

event = ""
event_name = ""
event_key = ""
serial_port = ""
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
  opts, args = getopt.getopt(sys.argv[1:], "e:k:u:s:fcdvtrh")
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
  elif opt == "-f":
    use_fake_read_results = True
  elif opt == "-c":
    continuous_testing = True
  elif opt == "-d":
    debug = 1
  elif opt == "-v":
    verbose = 1
  elif opt == "-t":
    testing_run = 1
  elif opt == "-r":
    replay_si_stick = 1
  else:
    print (f"ERROR: Unknown option {opt}.")
    usage()
    sys.exit(1)

if debug:
  print("Debug is enabled.")

if verbose:
  print("Verbose is enabled.")

if (event_key == ""):
  usage()
  sys.exit(1)

#status_reader_thread = Thread(target=slowly_add_status)
#status_reader_thread.start()

if event != "":
    root.after(1000, lambda: have_event(None, event, None))
else:
    root.after(1000, lambda: get_event(event_key))

root.mainloop()
exit_all_threads = True

sys.exit(1)

#run_offline = False
#if ((event == "") or (event_key == "")):
#  answer = raw_input("No event found - type yes to run in offline mode? ")
#  answer = answer.strip().lower()
#  run_offline = (answer == "yes")
#  if run_offline:
#    event = fake_offline_event
#    if os.path.exists(fake_offline_event + "-results.log"):
#      # If the offline log was last modified on a different day, then move it to a backup, thereby starting a new one
#      # otherwise just append to it, assuming it is from the same event
#      dlm_tuple = time.localtime(os.path.getmtime(fake_offline_event + "-results.log"))
#      today_tuple = time.localtime(None)
#      if not ((today_tuple.tm_year == dlm_tuple.tm_year) and (today_tuple.tm_mon == dlm_tuple.tm_mon)
#                 and (today_tuple.tm_mday == dlm_tuple.tm_mday)):
#        retry_count = 0
#        while retry_count < 1000:
#          backup_file = "{}-{}-{:02d}-{:02d}-{}-results.log".format(fake_offline_event, dlm_tuple.tm_year, dlm_tuple.tm_mon, dlm_tuple.tm_mday, retry_count)
#          if not os.path.exists(backup_file):
#            if verbose or debug: print (f"Backing up existing results file as {backup_file}")
#            os.rename(fake_offline_event + "-results.log", backup_file)
#            break
#          retry_count += 1
#      else:
#        if verbose or debug: print (f"Appending results to {fake_offline_event}-results.log")
#  else:
#    usage()
#    sys.exit(1)
#  
### Ensure that the event specified is valid
#if not run_offline:
#  output = make_url_call(VIEW_RESULTS, "event={}&key={}".format(event, event_key))
#  if ((re.search("No such event found {}".format(event), output) != None) or (re.search(r"Show results for", output) == None)):
#    print(f"Event {event} not found, please check if event {event} and key {event_key} are valid.")
#    sys.exit(1)
#

#if (replay_si_stick):
#  si_stick_to_replay = raw_input("Enter the si stick to replay, or offline to reply offline downloads: ")
#  si_stick_to_replay = si_stick_to_replay.strip()
#  if (si_stick_to_replay == ""):
#    print ("ERROR: Must enter si stick when using the -r option, re-run program to try again.")
#    sys.exit(1)
#  else:
#    replay_stick_results(event_key, event, si_stick_to_replay)
#    sys.exit(0)
#

#if not testing_run and use_real_si_reader:
#  si_reader = get_sireader(serial_port, verbose)
#  if (si_reader == None):
#    print ("ERROR: Cannot find si download station, is it plugged in?")
#    if (serial_port != ""):
#      print (f"\tAttempted to read from {serial_port}")
#    sys.exit(1)
#else:
#  si_reader = None
#
#loop_count = 0
#while True:
#  if not testing_run and use_real_si_reader:
#    si_stick_entry = read_results(si_reader)
#  elif use_fake_read_results:
#    si_stick_entry = read_fake_results()
#  else:
#    si_stick_entry = { SI_STICK_KEY : None }
#
#  if ((loop_count % 60) == 0):
#    time_tuple = time.localtime(None)
#    sys.stdout.write("Awaiting new results at: {:02d}:{:02d}:{:02d}\r".format(time_tuple.tm_hour, time_tuple.tm_min, time_tuple.tm_sec))
#    sys.stdout.flush()
#
#  if si_stick_entry[SI_STICK_KEY] != None:
#    if verbose:
#      print(f"\nFound new key: {si_stick_entry[SI_STICK_KEY]}")
#    else:
#      print("\n")
#
#    upload_entry_list = [ "{:d};{:d}".format(si_stick_entry[SI_STICK_KEY], si_stick_entry[SI_START_KEY]) ]
#    upload_entry_list.append("start:{:d}".format(si_stick_entry[SI_START_KEY]))
#    upload_entry_list.append("finish:{:d}".format(si_stick_entry[SI_FINISH_KEY]))
#    upload_entry_list.extend(si_stick_entry[SI_CONTROLS_KEY])
#    qr_result_string = ",".join(upload_entry_list)
#    if verbose:
#      print (f"Got results {qr_result_string} for si_stick {si_stick_entry[SI_STICK_KEY]}.")
#
#    with open("{}-results.log".format(event), "a") as LOGFILE:
#      LOGFILE.write(qr_result_string + "\n")
#
#    # If the finish is 0, then the finish wasn't scanned - we've logged it but don't upload the result.
#    # By editing the log file and replaying the SI stick, we can adjust the result afterwards if necessary.
#    # Though the easiest is to have the competitor go and scan finish and then download again.
#    if (si_stick_entry[SI_FINISH_KEY] != 0):
#      if not run_offline:
#        results = upload_results(event_key, event, qr_result_string)
#        print ("\n".join(results))
#        sys.stdout.flush()
#      else:
#        total_time = si_stick_entry[SI_FINISH_KEY] - si_stick_entry[SI_START_KEY]
#        hours = total_time / 3600
#        minutes = (total_time - (hours * 3600)) / 60
#        seconds = (total_time - (hours * 3600) - (minutes * 60))
#        print (f"Downloaded results for si_stick {si_stick_entry[SI_STICK_KEY]}, time was {hours}h:{minutes}m:{seconds}s ({total_time}).")
#        sys.stdout.flush()
#    else:
#      print ("Splits downloaded but no finish time found - punch finish and download again.")
#      sys.stdout.flush()
#
#  if testing_run and not continuous_testing:
#    break   # While testing, no need to wait for more results
#
#  time.sleep(1)
#  loop_count += 1
#
#
#
