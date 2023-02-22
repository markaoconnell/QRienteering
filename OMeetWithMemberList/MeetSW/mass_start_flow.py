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
from url_caller import UrlTimeoutException, url_caller
from LongRunningClass import LongRunningClass

class mass_start_flow(LongRunningClass):

    def __init__(self, user_info, url_caller, course_list, font):
        super().__init__()
        self.user_info = user_info
        self.course_list = course_list
        self.mass_start_frame = None
        self.local_font = font
        self.force_exit_called = False
        self.completion_callback = None
        self.url_caller = url_caller
        self.debug = False
        self.verbose = False
        pass

    def add_completion_callback(self, callback):
        self.completion_callback = callback

    
    def create_mass_start_window(self, start_seconds, event_key, event):
        self.user_info.get_widget().disable_buttons()

        self.event_key = event_key
        self.event = event
    
        self.mass_start_frame = tk.Toplevel()
        self.mass_start_frame.geometry("300x300")
        self.mass_start_frame.title("Mass Start course(s)")
    
        choices_frame = tk.Frame(self.mass_start_frame)
        button_frame = tk.Frame(self.mass_start_frame)
        info_label = tk.Label(choices_frame, text="Choose course(s) to start:", font=self.local_font)
        info_label.pack(side=tk.TOP)
    
        course_choices = [ ]
        for course in self.course_list:
            chosen_course = tk.StringVar(self.mass_start_frame, "unselected")
            course_choices.append(chosen_course)
            radio_button = tk.Checkbutton(choices_frame, text=course[0], onvalue=course[1], offvalue="unselected", variable=chosen_course, font=self.local_font)
            radio_button.pack(side=tk.TOP, anchor=tk.W)
    
        ok_button = tk.Button(button_frame, text="Mass start course(s)", command=lambda: self.mass_start_courses(course_choices, start_seconds), font=self.local_font)
        cancel_button = tk.Button(button_frame, text="Cancel", command=lambda: self.exit_mass_start_flow(), font=self.local_font)
    
        ok_button.pack(side=tk.LEFT)
        cancel_button.pack(side=tk.LEFT)
    
        choices_frame.pack(side=tk.TOP)
        button_frame.pack(side=tk.TOP)
    
        self.mass_start_frame.protocol("WM_DELETE_WINDOW", lambda: self.exit_mass_start_flow())
        return
    
    def mass_start_courses(self, course_choices, start_seconds):
        courses_to_start = list(filter(lambda elt: elt.get() != "unselected", course_choices))
        courses_to_start = list(map(lambda elt: elt.get(), courses_to_start))
    
        if len(courses_to_start) != 0:
            printable_courses_to_start = list(map(lambda elt: elt.lstrip("0123456789-"), courses_to_start))
            self.user_info.get_widget().update("Starting courses: " + ", ".join(printable_courses_to_start))
            self.user_info.get_widget().disable_buttons()

            mass_start_thread = Thread(target=self.send_mass_start_command, args=(courses_to_start, start_seconds))
            mass_start_thread.start()
        else:
            self.user_info.get_widget().update("No courses chosen for mass start")
    
        self.mass_start_frame.destroy()
        self.mass_start_frame = None
    
        return
    
    #######################################################################################
    def send_mass_start_command(self, courses_to_start, start_seconds):
    
      mass_start_params = f"key={self.event_key}&event={self.event}&si_stick_time={start_seconds}&courses_to_start=" + ",".join(courses_to_start)
      if self.debug: print(f"Attempting to mass start with params {mass_start_params}.")
            
      if self.force_exit_called: return
      try:
          output = self.url_caller.make_url_call(url_caller.MASS_START, mass_start_params)
      except UrlTimeoutException:
          output = r"####,ERROR,Timed out connecting to web site, validate internet connectivity and site status"
      if self.force_exit_called: return
      if self.debug: print (f"Results of web call {output}.")
                  
      mass_start_results = re.findall(r"####,STARTED,(.*)", output)
      errors = re.findall(r"####,ERROR,(.*)", output)
    
      output_message = ""
      if len(mass_start_results) == 0:
          output_message = "Mass start failed: no competitors_started"
          error_found = True
      else:
          courses_started_list = map(lambda elt: elt.split(",")[1], mass_start_results)
          started_histogram = Counter(courses_started_list)
          starts_by_course = map(lambda elt: elt.lstrip("0123456789-") + ":" + (str(started_histogram[elt]) if elt in started_histogram else "0"), courses_to_start)
          output_message = "Mass starts on course(s): " + ", ".join(starts_by_course)
          error_found = False
    
      if (len(errors) > 0):
          output_message += "\n".join(errors)
          error_found = True
    
      self.user_info.get_widget().show_as_error(error_found)
      self.user_info.get_widget().update(output_message)
    
      return
    
    #####################################################################
    def exit_mass_start_flow(self):
        if self.mass_start_frame != None:
            self.mass_start_frame.destroy()
            self.mass_start_frame = None
        if self.completion_callback != None:
            self.completion_callback(self)
        self.user_info.get_widget().enable_buttons()

    def force_exit(self):
        super().force_exit()
        self.force_exit_called = True
    
