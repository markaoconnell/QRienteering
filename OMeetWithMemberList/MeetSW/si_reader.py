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
from sireader2 import SIReader, SIReaderReadout, SIReaderControl, SIReaderException, SIReaderTimeout, SIReaderCardChanged
from LongRunningClass import LongRunningClass

TWELVE_HOURS_IN_SECONDS = (12 * 3600)

class si_stick_contents:
    def __init__(self):
        self.stick = None
        self.start_timestamp = 0
        self.finish_timestamp = 0
        self.controls_list = None
        self.bad_download = False

    def set_bad_download(self):
        self.bad_download = True

    def set_stick(self, stick):
        self.stick = stick

    def set_stick_info(self, start=0, finish=0, controls_list = None):
        self.start_timestamp = start
        self.finish_timestamp = finish
        self.controls_list = controls_list


class si_processor(LongRunningClass):

    def __init__(self, reader, stick_callback, status_update_callback):
        self.si_reader = reader
        self.verbose = False
        self.debug = False
        self.stick_callback = stick_callback
        self.status_update_callback = status_update_callback
        self.force_exit_called = False
        self.si_reader = reader
    
    def get_and_log_results_string(self, read_stick, event):
        upload_entry_list = [ "{:d};{:d}".format(read_stick.stick, read_stick.start_timestamp) ]
        upload_entry_list.append("start:{:d}".format(read_stick.start_timestamp))
        upload_entry_list.append("finish:{:d}".format(read_stick.finish_timestamp))
        upload_entry_list.extend(read_stick.controls_list)
        qr_result_string = ",".join(upload_entry_list)
        if self.verbose:
          print (f"Got results {qr_result_string} for si_stick {stick_values[SI_STICK_KEY]}.")
    
        with open("{}-results.log".format(event), "a") as LOGFILE:
          LOGFILE.write(qr_result_string + "\n")
    
        return qr_result_string
        
    
    def start(self):
        sireader_thread = Thread(target=self.sireader_main)
        sireader_thread.start()
    
    def sireader_main(self):
        if self.force_exit_called: return
        
        loop_count = 0
        while True:
          finish_adjusted = False
          if self.force_exit_called: return
    
          read_stick = self.si_reader.read_results()
        
          if ((loop_count % 60) == 0):
            if self.status_update_callback != None:
              self.status_update_callback(self)
        
          if read_stick.stick != None:
            self.stick_callback(self, read_stick)
        
          time.sleep(1)
          loop_count += 1

    def force_exit(self):
        super().force_exit()
        self.force_exit_called = True

class generic_si_reader:
    def __init__(self):
        pass

    def get(self):
        pass

    def read_results(self):
        pass

class real_si_reader(generic_si_reader):

    def __init__(self):
        self.verbose = False
        self.debug = False
        self.serial_port_name = ""
        self.si_reader = None

    def set_serial_port(self, serial_port_name):
        self.serial_port_name = serial_port_name

    ###############################################################
    def get(self):
    # connect to base station, the station is automatically detected,
    # if this does not work, give the path to the port as an argument
    # see the pyserial documentation for further information.
      try:
        if (self.serial_port_name != ""):
          si = SIReaderReadout(port=self.serial_port_name)
        else:
          si = SIReaderReadout()
      except SIReaderException as sire:
        si = None
        if self.verbose:
          print (f"Cannot find si download station, reason: {sire}")
    
      self.si_reader = si
      return si
    
  
    #################################################################
    def read_results(self):
    # wait for a card to be inserted into the reader
      try:
        if not self.si_reader.poll_sicard():
          return(si_stick_contents())
      except SIReaderException as sire:
        self.si_reader.ack_sicard()
        #print (f"Bad card download, error {sire}.")
        return (si_stick_contents())
    
    # some properties are now set
      card_number = self.si_reader.sicard
      card_type = self.si_reader.cardtype
    
    # read out card data
      try:
        card_data = self.si_reader.read_sicard()
      except (SIReaderException, SIReaderTimeout, SIReaderCardChanged) as sire:
        #print (f"Bad card ({card_number}) download, error {sire}.")
        bad_stick_values = si_stick_contents()
        bad_stick_values.set_stick(card_number)
        bad_stick_values.set_bad_download()
        return(bad_stick_values)
    
    # beep
      self.si_reader.ack_sicard()
      
    # Wait for the card to be removed from the reader
      while not self.si_reader.poll_sicard():
        time.sleep(1)
    
    
    # Convert to the format expected by the rest of the program
    # Check for old sticks which only use 12 hour time, which have some trouble if
    # the event starts before noon and ends after noon
      if card_data['start'] != None:
        start_timestamp = self.get_24hour_timestamp(card_data['start'])
      else:
         start_timestamp = 0
    
      if card_data['finish'] != None:
        finish_timestamp = self.get_24hour_timestamp(card_data['finish'])
      else:
        finish_timestamp = 0
        if self.debug: print (f"No finish timestamp on stick {card_number} - please scan finish and then download.")
    	
      array_of_punches = []
      if ((finish_timestamp < start_timestamp) and (finish_timestamp < TWELVE_HOURS_IN_SECONDS)):
        # Anomaly detected!  Adjust any timestamp less than the start forward by 12 hours
        # First convert the tuples of datetime objects to just a value in seconds
        # Then adjust the appropriate entries (those less than the start timestamp) by 12 hours
        # Then format it as : separated string items
        if (finish_timestamp != 0): finish_timestamp += TWELVE_HOURS_IN_SECONDS
        orig_punches = []
        new_punches = []
        orig_punches = map(lambda punch: (punch[0], self.get_24hour_timestamp(punch[1])), card_data['punches'])
        new_punches = map(lambda punch: (punch[0], punch[1] + TWELVE_HOURS_IN_SECONDS if (punch[1] < start_timestamp) else punch[1]), orig_punches)
        array_of_punches = map(lambda punch: "{}:{}".format(str(punch[0]), str(punch[1])), new_punches)
        if self.verbose: print (f"Adjusting some times for {card_number} by twelve hours.")
      else:
        array_of_punches = map(lambda punch: "{}:{}".format(str(punch[0]), str(self.get_24hour_timestamp(punch[1]))), card_data['punches'])
    
      #print "Here is the array of punches {}.".format(array_of_punches)
    
      entry_to_return = si_stick_contents()
      entry_to_return.set_stick(card_number);
      entry_to_return.set_stick_info(start = start_timestamp, finish = finish_timestamp, controls_list = list(array_of_punches))
      
      return(entry_to_return)

    ###############################################################
    def get_24hour_timestamp(self, punch_time):
    # Take a datetime object, from reading the si card, and convert to seconds since midnight
      #print "Datetime object looks like: {}".format(dir(punch_time))
      #return (datetime.timestamp(punch_time))
      return ((punch_time.hour * 3600) + (punch_time.minute * 60) + punch_time.second)
  

class fake_si_reader(generic_si_reader):
    def __init__(self):
      self.simulated_entries = []
    
    def initialize(self, filename_of_fake_results):
    
      if filename_of_fake_results != "":
          filename = filename_of_fake_results
      else:
          filename = "fake_entries_for_manage_event"
    
      try:
        with open(filename, "r") as FAKE_ENTRIES:
          for line in FAKE_ENTRIES:
            line = line.strip()
            if line.startswith("#"): # Ignore comment lines
              continue
            if line == "":
              self.simulated_entries.append(si_stick_contents())
            else:
              value = line.split(",")
              #print (f"The line is --{line}--")
              #print (f"It has {len(value)} entries.")
              first_entry_pieces = value[0].split(";")
              if (len(first_entry_pieces) > 1):
                # log entry format from a real SI unit download
                # 503555;0,start:0,finish:0
                # 24680;1000,start:1000,finish:2000,151:1100,152:1500,155:1600,151:1680
                si_stick = int(first_entry_pieces[0])
                start = int(value[1].split(":")[1])
                finish = int(value[2].split(":")[1])
              else:
                # Entry format easier for a human to enter
                # stick,start,finish,controls
                # 24680,1000,2000,151:1100,152:1500,155:1600,151:1680
                si_stick = int(value[0])
                start = int(value[1])
                finish = int(value[2])
    
              fake_stick = si_stick_contents()
              fake_stick.set_stick(si_stick)
              if (len(value) > 3):
                 fake_stick.set_stick_info(start = start, finish = finish, controls_list = value[3:])
              else:
                 fake_stick.set_stick_info(start = start, finish = finish, controls_list = [])
              self.simulated_entries.append(fake_stick)
      except FileNotFoundError:
        pass  # Fine if the file is not there, we'll just do nothing
    
    def get(self):
        return self
      
    def read_results(self):
      if len(self.simulated_entries) > 0:
        return self.simulated_entries.pop()
      else:
        return si_stick_contents()
