import tkinter as tk
import tkinter.ttk as ttk
import tkinter.font as font
from threading import Thread
import time
import sys, getopt
import os
import os.path
import subprocess
import re
import base64
from datetime import datetime
from collections import Counter
import urllib.parse
from status_widget import status_widget, offline_status_widget, mass_start_status_widget
from user_info import stick_info, found_user_info
from registration_flow import register_user
from mass_start_flow import mass_start_flow
from si_reader import si_processor, real_si_reader, fake_si_reader
from url_caller import url_caller, UrlTimeoutException
from font_size_change_flow import change_font_size_flow

progress_label = None
mode_label = None
mode_button = None
exit_all_threads = False
discovered_courses = [ ]
long_running_classes = []
root = None
status_frame = None
scrollable_status_frame = None
web_site_timeout = 10
change_font_size_frame = None



# Initialize a few helpful constants
verbose = 0
debug = 0
testing_run = 0
continuous_testing = 0
url = "http://www.mkoconnell.com/OMeet/not_there"
use_fake_read_results = False
run_offline = False


MISSED_FINISH_PUNCH_SPLIT = 600
MISSED_FINISH_PUNCH_MESSAGE = "No finish punch detected, recorded finish split of 10m"

# Modes for what to do with the info from the SI unit
DOWNLOAD_MODE = 1
REGISTER_MODE = 2
MASS_START_MODE = 3
current_mode = DOWNLOAD_MODE

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
def get_event(current_event_key):

  possible_event_list = url_caller.get_event_list(current_event_key)

  if (len(possible_event_list) == 0):
    if (verbose or debug):
      print("No currently open (actively ongoing) events found.")
    no_event_frame = tk.Frame(root)
    no_event_label = tk.Label(no_event_frame, text="No events found, suspect:\npossible incorrect configuration\nno internet connectivity", fg="red", font=myFont)
    no_event_button = tk.Button(no_event_frame, text="Run in offline mode", command=lambda: run_in_offline_mode(no_event_frame), font=myFont)
    no_event_label.pack(side=tk.TOP)
    no_event_button.pack(side=tk.TOP)
    no_event_frame.pack(side=tk.TOP)
    return
  elif (len(possible_event_list) == 1):
    elements = possible_event_list[0].split(",")
    #match = re.search(r"(event-[0-9a-f]+).*>Results for (.*?)<", event_matches_list[0])
    if (verbose or debug):
      print("Found single matching event (" + elements[2] + ") named " + elements[3] + ".")
    root.after(1, lambda: have_event(None, [ elements ], 0))
  else:
     #event_ids = map(lambda event_possible_match: re.search(r"(event-[0-9a-f]+)", event_possible_match).group(1), event_matches_list)
     #event_names = map(lambda event_possible_match: re.search(r">Results for (.*?)<", event_possible_match).group(1), event_matches_list)
     event_ids = list(map(lambda event_comment: event_comment.split(","), possible_event_list))

     if debug:
       event_strings = map(lambda event_from_site: f"{event_from_site[2]} -> " + base64.standard_b64decode(event_from_site[3]).decode("utf-8"), event_ids)
       print("\n".join(event_strings))


     choice_frame = tk.Frame(root)
     choice_prompt = tk.Label(choice_frame, text="Please choose an event:", font=myFont)
     choice_prompt.pack(side=tk.TOP)
     chosen_event = tk.IntVar(choice_frame, -1)
     for index, possible_event in enumerate(event_ids):
        #print(f"Choice {index} is {possible_event[2]}\n")
        this_choice = tk.Radiobutton(choice_frame, text=base64.standard_b64decode(possible_event[3]).decode("utf-8"), value=index, var=chosen_event, font=myFont)
        this_choice.pack(anchor=tk.W, side=tk.TOP)

     choice_button = tk.Button(choice_frame, text="Use chosen event", command=lambda: have_event(choice_frame, event_ids, chosen_event.get()), font=myFont)
     choice_button.pack(side=tk.TOP)
     choice_frame.pack(side=tk.TOP)


##############################################################
def have_event(choice_frame, event_list, chosen_event):
    global event, event_key, event_allows_preregistration
    if choice_frame != None:
        choice_frame.pack_forget()
        choice_frame.destroy()
    if chosen_event != -1:
        event_name = base64.standard_b64decode(event_list[chosen_event][3]).decode("utf-8")
        root.title(f"QRienteering download station for: {event_name}")
        mode_label.configure(text="Validating event and associated courses")
        event = event_list[chosen_event][2]
        event_allows_preregistration = (event_list[chosen_event][4] == "Preregistration")
        root.after(1, lambda: get_courses(event))
    else:
        root.title("QRienteering download station - no event selected")
        error_label = tk.Label(root, text = "No event selected, exiting").pack(side=tk.TOP)
        mode_label.configure(text="Error - no event selected")
        root.after(10000, lambda: sys.exit(1))


###############################################################
def get_courses(event):
    global discovered_courses
    discovered_courses = url_caller.get_courses(event, url_caller.get_xlated_key())
        
    if discovered_courses == None:
        print(f"Cannot find course list for {event}, is it a valid event?")
        mode_label.configure(text="Connectivity error")
    else:
        mode_label.configure(text="In Download mode")
        mode_button.configure(state=tk.NORMAL)

    if verbose: print ("\n".join(map(lambda s: s[0] + " -> " + s[1], discovered_courses)) + "\n")

    create_status_frame()
    root.after(1, sireader_main)

###############################################################
def run_in_offline_mode(enclosing_frame):
    global run_offline, event
    run_offline = True
    time_tuple = time.localtime(None)
    event = f"event-offline-{time_tuple.tm_year:04d}-{time_tuple.tm_mon:02d}-{time_tuple.tm_mday:02d}"
    enclosing_frame.destroy()
    create_status_frame()
    root.title(f"QRienteering download station: offline mode")
    mode_label.configure(text="In Download mode")
    root.after(1, sireader_main)


###############################################################
def upload_initial_results(user_info, event):
  result_tuple = url_caller.upload_results(user_info, url_caller.get_xlated_key(), event)

  # result_tuple[2] specifies if the upload call timed out or not
  # Handle it a little specially in the case of a timeout
  if result_tuple[2]:
      extra_status = "\nConnectivity error - please validate internet connectivity and site status\n"
      extra_status += "If finishing - use the download button to retry\n"
      extra_status += "If registering - use the register button to retry"
      new_result_tuple = (result_tuple[0] + extra_status, result_tuple[1])
      result_tuple = new_result_tuple
  elif user_info.get_lookup_info() == None:
     # If the username is still None, then there was no registered entry found
     # See if the person is a member and could be quickly registered
      try:
          possible_member_info = url_caller.lookup_si_unit(user_info.stick_number, event, event_allows_preregistration)
          if possible_member_info != None:
              user_info.add_lookup_info(possible_member_info)
              extra_status = f"\nIdentified member {user_info.get_lookup_info().name}\n"
              extra_status += "If finishing - use the register button to register for a course, then download the results\n"
              extra_status += "If registering for a new course - use the register button, then clear and check"
              new_result_tuple = (result_tuple[0] + extra_status, result_tuple[1])
              result_tuple = new_result_tuple
          else:
              extra_status = "\nIf finishing - register via a SmartPhone, then download again\n"
              extra_status += "If registering for a new course - register via a SmartPhone, then clear and check"
              new_result_tuple = (result_tuple[0] + extra_status, result_tuple[1])
              result_tuple = new_result_tuple
      except UrlTimeoutException:
          extra_status = "\nConnectivity error - please validate internet connectivity and site status"
          new_result_tuple = (result_tuple[0] + extra_status, result_tuple[1])
          result_tuple = new_result_tuple


  user_info.get_widget().set_can_register(user_info.get_lookup_info() != None)
  user_info.get_widget().set_can_replay(True)
  user_info.get_widget().show_as_error(result_tuple[1])
  user_info.get_widget().update(result_tuple[0])


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

def switch_to_mass_start_mode():
    global current_mode
    mode_label["text"] = "Reading mass start time from SI unit"
    mode_button["text"] = "Switch to download mode"
    current_mode = MASS_START_MODE

    return

def switch_mode():
    global current_mode
    if current_mode == DOWNLOAD_MODE:
        mode_label["text"] = "In Register mode"
        mode_button["text"] = "Switch to download mode"
        current_mode = REGISTER_MODE
    elif (current_mode == REGISTER_MODE) or (current_mode == MASS_START_MODE):
        mode_label["text"] = "In Download mode"
        mode_button["text"] = "Switch to register mode"
        current_mode = DOWNLOAD_MODE

    return




#####################################################################
def set_new_font_size(font_size_change_window):
    global font_size
    font_size = font_size_change_window.get_new_font_size()
    myFont.config(size = font_size_change_window.get_new_font_size())
    remove_long_running_class(font_size_change_window)



#####################################################################


def replay_stick(user_info):
    user_info.get_widget().update(f"Replaying SI results for {user_info.stick_number}")
    user_info.get_widget().disable_buttons()

    replay_thread = Thread(target=replay_stick_thread, args=(user_info,))
    replay_thread.start()
    return

def replay_stick_thread(user_info):
    if exit_all_threads: return
    result_tuple = url_caller.upload_results(user_info, event_key, event)
    if exit_all_threads: return

    if user_info.get_missed_finish():
        user_info.get_widget().show_as_error(True)
        user_info.get_widget().update(result_tuple[0] + "\n" + MISSED_FINISH_PUNCH_MESSAGE)
    else:
        user_info.get_widget().show_as_error(result_tuple[1])
        user_info.get_widget().update(result_tuple[0])

    return

def mass_start_window(user_info, start_seconds, event_key, event):
    mass_start_courses = mass_start_flow(user_info, url_caller, discovered_courses, myFont)
    add_long_running_class(mass_start_courses)
    mass_start_courses.add_completion_callback(remove_long_running_class)
    mass_start_courses.create_mass_start_window(start_seconds, event_key, event)
    return

def registration_window(user_info):
    registration_flow = register_user(user_info, url_caller, discovered_courses, myFont)
    add_long_running_class(registration_flow)
    registration_flow.add_completion_callback(remove_long_running_class)
    registration_flow.create_registration_window(event_key, event)
    return

def change_font_size_window():
    change_font_size = change_font_size_flow(myFont)
    add_long_running_class(change_font_size)
    change_font_size.add_completion_callback(set_new_font_size)
    change_font_size.create_font_size_change_window(font_size)
    return

def add_long_running_class(long_running_class):
    long_running_classes.append(long_running_class)

def remove_long_running_class(long_running_class):
    long_running_classes.remove(long_running_class)
    return

def kill_all_windows():
    global exit_all_threads, root

    exit_all_threads = True
    for long_running_class in long_running_classes:
        long_running_class.force_exit()

    root.destroy()

def create_status_frame():
    global status_frame
    scrollable_status_frame = ScrollableFrame(root)
    scrollable_status_frame.pack(fill=tk.BOTH, side=tk.TOP, pady=10, expand = True)
    status_frame = scrollable_status_frame.scrollable_frame



def process_si_stick(user_info, forced_registration):
  if (current_mode == REGISTER_MODE) or forced_registration:
      display_as_error = False
      try:
          discovered_user_info = url_caller.lookup_si_unit(user_info.stick_number, event, event_allows_preregistration)
          if (discovered_user_info != None):
              user_info.add_lookup_info(discovered_user_info)
              message = f"Recognized member {user_info.get_lookup_info().name} with SI unit {user_info.stick_number}"
              message += "\nIf registering, use the register button."
          else:
              display_as_error = True
              message = f"Could not find member for SI unit {user_info.stick_number}\n"
              message += "If registering - use SmartPhone based registration instead."
      except UrlTimeoutException:
          display_as_error = True
          message = f"Could not contact website about {user_info.stick_number}\nValidate connectivity and site status\n"
          message += "If registering - reinsert the stick into the reader."
    
      if forced_registration:
          message += "\nIf finishing, SI unit has no information - use self reporting to record a time."
          user_info.set_download_possible(False)
          if current_mode == DOWNLOAD_MODE:
              display_as_error = True
      else:
          message += "\nIf finishing, use the download button"

      if user_info.get_missed_finish():
          message = message + "\n" + MISSED_FINISH_PUNCH_MESSAGE
          display_as_error = True

      user_info.get_widget().set_can_register(user_info.get_lookup_info() != None)
      user_info.get_widget().set_can_replay(not forced_registration)
      user_info.get_widget().show_as_error(display_as_error)
      user_info.get_widget().update(message)

      if (current_mode == REGISTER_MODE) and (user_info.get_lookup_info() != None):
          user_info.get_widget().disable_buttons()
          registration_window(user_info)

  elif (current_mode == DOWNLOAD_MODE):
      upload_initial_results(user_info, event)

  return


def sireader_main():
    if exit_all_threads: return
    found_reader = (si_reader.get() != None)
    if not found_reader:
      progress_label.configure(text="ERROR: Cannot find si download station, is it plugged in?")
      if (serial_port != ""):
        print (f"\tAttempted to read from {serial_port}")
      return
    
    si_continuous_reader = si_processor(si_reader, found_stick_callback, status_update_callback)
    add_long_running_class(si_continuous_reader)
    si_continuous_reader.start()

def status_update_callback(si_continuous_reader):
    time_tuple = time.localtime(None)
    progress_label.configure(text="Awaiting new results at: {:02d}:{:02d}:{:02d}".format(time_tuple.tm_hour, time_tuple.tm_min, time_tuple.tm_sec))


def found_stick_callback(si_continuous_reader, read_stick):
      forced_registration = False
      finish_adjusted = False

      if read_stick.bad_download:
        user_info = stick_info(read_stick.stick, None)
        user_info.set_download_possible(False)
        user_info.add_widget(offline_status_widget(user_info.stick_number, myFont))
        message = f"Prematurely removed SI unit {user_info.stick_number} from download station\n"
        message += "Please reinsert and wait until the unit beeps."
        user_info.get_widget().create(status_frame, message)
        user_info.get_widget().show_as_error(True)
        user_info.get_widget().enable_buttons()
        user_info.get_widget().show(root)
      else:  # Process a valid stick download
        if verbose: print(f"\nFound new key: {read_stick.stick}")
    
        qr_result_string = si_continuous_reader.get_and_log_results_string(read_stick, event)
    
        # If the finish is 0, then the finish wasn't scanned - we've logged it already so we have the raw data
        # By editing the log file and replaying the SI stick, we can adjust the result afterwards if necessary.
        # Though the easiest is to have the competitor go and scan finish and then download again.
        #
        # Now try and figure out what to do if the finish key is 0 (finish not scanned)
        # If start was also not scanned, then the stick was likely cleared and this is probably a registration
        # If start was scanned, then assign a finish split of 10 minutes (something ridiculous) and allow the entry
        # The competitor can always go back and punch finish and then download again
        if read_stick.finish_timestamp == 0:
            if read_stick.start_timestamp == 0:
                forced_registration = True
            else:
                punch_times = list(map(lambda punch: int(punch.split(":")[1]), read_stick.controls_list))
                if len(punch_times) > 0:
                    last_punch = max(punch_times)
                else:
                    last_punch = 0

                if last_punch > read_stick.start_timestamp:
                    read_stick.finish_timestamp = last_punch + MISSED_FINISH_PUNCH_SPLIT
                else:
                    read_stick.finish_timestamp = read_stick.start_timestamp + MISSED_FINISH_PUNCH_SPLIT
                qr_result_string = si_continuous_reader.get_and_log_results_string(read_stick, event)
                finish_adjusted = True

        if not run_offline:
          if exit_all_threads: return
          if current_mode == MASS_START_MODE:
              # Get the start time from the result string
              start_seconds = read_stick.start_timestamp

              hours = start_seconds // 3600
              minutes = (start_seconds - (hours * 3600)) // 60
              seconds = (start_seconds - (hours * 3600) - (minutes * 60))
              status_message = f"Use mass start time of: {hours:02d}h:{minutes:02d}m:{seconds:02d}s ({start_seconds})."

              user_info = stick_info(read_stick.stick, qr_result_string)

              user_info.add_widget(mass_start_status_widget(user_info.stick_number, myFont, user_info,
                                                mass_start_window, start_seconds, url_caller.get_xlated_key(), event))
              user_info.get_widget().create(status_frame, status_message)
              user_info.get_widget().enable_buttons()
              user_info.get_widget().show(root)
          else:
              user_info = stick_info(read_stick.stick, qr_result_string)
              if finish_adjusted:
                  user_info.set_missed_finish(True)

              user_info.add_widget(status_widget(user_info.stick_number, myFont, user_info, replay_stick, registration_window))
              user_info.get_widget().create(status_frame, f"Processing SI unit {user_info.stick_number}")
              user_info.get_widget().show(root)

              # Do this off the main si reader thread so that the next stick can be read
              process_si_stick_thread = Thread(target=process_si_stick, args=(user_info, forced_registration))
              process_si_stick_thread.start()

          if exit_all_threads: return
        else:
          total_time = read_stick.finish_timestamp - read_stick.start_timestamp
          hours = total_time // 3600
          minutes = (total_time - (hours * 3600)) // 60
          seconds = (total_time - (hours * 3600) - (minutes * 60))

          user_info = stick_info(read_stick.stick, qr_result_string)
          if finish_adjusted:
              user_info.set_missed_finish(True)

          status_message = f"Downloaded results for si_stick {read_stick.stick}, time was {hours}h:{minutes:02d}m:{seconds:02d}s ({total_time})."
          if finish_adjusted:
              user_info.set_missed_finish(True)
              status_message = status_message + "\n" + MISSED_FINISH_PUNCH_MESSAGE

          user_info.add_widget(offline_status_widget(user_info.stick_number, myFont))
          user_info.get_widget().create(status_frame, status_message)
          user_info.get_widget().enable_buttons()
          user_info.get_widget().show(root)
    


#########################################################
# Main program

root = tk.Tk()
root.geometry("750x500")
root.title("QRienteering SI download station")

#Configure the default properties, especially font size
myFont = font.Font()


# Set up the initial layout
menubar = tk.Menu(root)
options_menu = tk.Menu(menubar, tearoff = 0)
options_menu.add_command(label = "Mass start from SI unit", command = switch_to_mass_start_mode)
options_menu.add_command(label = "Change font size", command = change_font_size_window)
menubar.add_cascade(label = "Options", menu = options_menu)
root.config(menu = menubar)

mode_frame = tk.Frame(root, highlightbackground="blue", highlightthickness=5)
mode_frame.pack(fill=tk.X, side=tk.TOP)

mode_label = tk.Label(mode_frame, text="Starting up, finding event", font=myFont)
mode_label.pack(side=tk.LEFT) 
exit_button = tk.Button(mode_frame, text="Exit", command=kill_all_windows, font=myFont)
exit_button.pack(side=tk.RIGHT)
mode_button = tk.Button(mode_frame, text="Switch to register mode", command=switch_mode, state=tk.DISABLED, font=myFont)
mode_button.pack(side=tk.RIGHT, padx=5)
progress_label = tk.Label(mode_frame, text="Starting up", font=myFont)
progress_label.pack(side=tk.BOTTOM, pady=5)

root.protocol("WM_DELETE_WINDOW", kill_all_windows)



##89898989;-:-:--:--:--;-:-:--:--:--;-:-:19:34:14;-:-:19:34:43;101;-:-:19:34:23;102;-:-:19:34:36;103;-:-:19:34:38;104;-:-:19:34:40;105;-:-:19:34:41;
initializations = read_ini_file()

event = ""
event_name = ""
event_key = ""
event_allows_preregistration = False
serial_port = ""
event_found = False
font_size = None

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

if ("web_site_timeout" in initializations):
  web_site_timeout = initializations["web_site_timeout"]

if ("font_size" in initializations):
  font_size = int(initializations["font_size"])
  myFont.config(size=font_size)

filename_of_fake_results = ""

try:
  opts, args = getopt.getopt(sys.argv[1:], "e:k:u:s:f:cdvtr:h")
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
    filename_of_fake_results = arg
  elif opt == "-c":
    continuous_testing = True
  elif opt == "-d":
    debug = 1
  elif opt == "-v":
    verbose = 1
  elif opt == "-t":
    testing_run = 1
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

url_caller = url_caller(url, web_site_timeout)
if use_fake_read_results:
    si_reader = fake_si_reader()
    si_reader.initialize(filename_of_fake_results)
else:
    si_reader = real_si_reader()
    if serial_port != "":
        si_reader.set_serial_port(serial_port)

if event != "":
    root.after(1000, lambda: have_event(None, (None, None, event, event), 0))
else:
    root.after(1000, lambda: get_event(event_key))

root.mainloop()
exit_all_threads = True

sys.exit(1)

