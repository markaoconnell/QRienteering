import tkinter as tk
import tkinter.ttk as ttk
import tkinter.font as font
from threading import Thread
import urllib.parse
import base64
import re
from LongRunningClass import LongRunningClass
from url_caller import UrlTimeoutException, url_caller


# Used in the registration dialog if the name is not known (should never really happen though)
NAME_NOT_SET = "unknown"

class register_user(LongRunningClass):

    def __init__(self, user_info, url_caller, course_list, font):
        super().__init__()
        self.user_info = user_info
        self.course_list = course_list
        self.registration_frame = None
        self.local_font = font
        self.force_exit_called = False
        self.completion_callback = None
        self.url_caller = url_caller
        self.debug = False
        self.verbose = False
        pass

    def add_completion_callback(self, callback):
        self.completion_callback = callback

    def create_registration_window(self, event_key, event):
        self.user_info.get_widget().disable_buttons()
    
        self.event_key = event_key
        self.event = event

        self.registration_frame = tk.Toplevel()
        if len(self.course_list) < 8:
          self.registration_frame.geometry("300x300")
        else:
          frame_height = (len(self.course_list) * 30) + 100
          self.registration_frame.geometry(f"300x{frame_height}")
        self.registration_frame.title("Register entrant")
    
        choices_frame = tk.Frame(self.registration_frame)
        button_frame = tk.Frame(self.registration_frame)
        chosen_course = tk.StringVar(self.registration_frame, "unselected")
    
        info_frame = tk.Frame(choices_frame)
        name = tk.StringVar(choices_frame)
        registration_string = ""
        if self.user_info.get_lookup_info() != None:
            name.set(self.user_info.get_lookup_info().name)
        else:
            name.set(NAME_NOT_SET)
        info_label_1 = tk.Label(info_frame, text="Register ", font=self.local_font)
        info_label_2 = tk.Entry(info_frame, textvariable = name, font=self.local_font)
        info_label_3 = tk.Label(info_frame, text=f" ({self.user_info.stick_number})", font=self.local_font)
        info_label_1.pack(side=tk.LEFT)
        info_label_2.pack(side=tk.LEFT)
        info_label_3.pack(side=tk.LEFT)
        info_frame.pack(side=tk.TOP)
    
        for course in self.course_list:
            radio_button = tk.Radiobutton(choices_frame, text=course[0], value=course[1], variable=chosen_course, font=self.local_font)
            radio_button.pack(side=tk.TOP, anchor=tk.W)
            if self.user_info.get_lookup_info().course != None:
                if self.user_info.get_lookup_info().course == course[0]:
                    chosen_course.set(course[1])
    
        cell_phone = tk.StringVar(self.registration_frame, "")
        if self.user_info.get_lookup_info().cell_phone != None:
          cell_phone.set(self.user_info.get_lookup_info().cell_phone)
          
        cell_phone_label = tk.Label(choices_frame, text="Verify cell phone (re-enter if incorrect):", font=self.local_font)
        cell_phone_box = tk.Entry(choices_frame, textvariable = cell_phone, font=self.local_font)
        cell_phone_label.pack(side=tk.TOP, anchor=tk.W)
        cell_phone_box.pack(side=tk.TOP, anchor=tk.W)
    
        ok_button = tk.Button(button_frame, text="Register for course", command=lambda: self.register_for_course(name, chosen_course, cell_phone), font=self.local_font)
        cancel_button = tk.Button(button_frame, text="Cancel", command=lambda: self.exit_registration_flow(), font=self.local_font)
    
        ok_button.pack(side=tk.LEFT)
        cancel_button.pack(side=tk.LEFT)
    
        choices_frame.pack(side=tk.TOP)
        button_frame.pack(side=tk.TOP)
    
        self.registration_frame.protocol("WM_DELETE_WINDOW", lambda: self.exit_registration_flow())
        return
    
    
    def register_for_course(self, name, chosen_course, cell_phone):
        # The user changed their name from what we have in the registration and/or member database
        # Update the name now
        if name.get() != NAME_NOT_SET:
            self.user_info.get_lookup_info().name = name.get()
    
        if self.user_info.get_lookup_info() != None:
            message = f"Attempting to register {self.user_info.get_lookup_info().name} ({self.user_info.stick_number}) on " 
        else:
            message = f"Attempting to register member with SI unit {self.user_info.stick_number} on "
    
        self.user_info.get_widget().update(message + chosen_course.get().lstrip("0123456789-"))
        self.registration_frame.destroy()
        self.registration_frame = None
    
        registration_thread = Thread(target=self.register_by_si_unit, args=(chosen_course.get(), cell_phone.get()))
        registration_thread.start()
        return


    #######################################################################################
    def register_by_si_unit(self, chosen_course, cell_phone):
    
      if self.user_info.get_lookup_info() == None:
        message = f"SI unit {self.user_info.stick_number} not registered to a known member, registration canceled"
        self.user_info.get_widget().update(message)
        self.user_info.get_widget().show_as_error(True)
        return
      
      found_name = self.user_info.get_lookup_info().name
      stick = self.user_info.stick_number
      club_name = self.user_info.get_lookup_info().club if self.user_info.get_lookup_info().club != None else ""
      found_email = self.user_info.get_lookup_info().email if self.user_info.get_lookup_info().email != None else ""
      found_id = self.user_info.get_lookup_info().member_id if self.user_info.get_lookup_info().member_id != None else ""
      registration_list = ["first_name", base64.standard_b64encode(found_name.encode("utf-8")).decode("utf-8"),
                                 "last_name", base64.standard_b64encode("".encode("utf-8")).decode("utf-8"),
                                 "club_name", base64.standard_b64encode(club_name.encode("utf-8")).decode("utf-8"),
                                 "si_stick", base64.standard_b64encode(str(stick).encode("utf-8")).decode("utf-8"),
                                 "email_address", base64.standard_b64encode(found_email.encode("utf-8")).decode("utf-8"),
                                 "registration", base64.standard_b64encode("SI unit".encode("utf-8")).decode("utf-8"),
                                 "member_id", base64.standard_b64encode(found_id.encode("utf-8")).decode("utf-8"),
                                 "cell_phone", base64.standard_b64encode(cell_phone.encode("utf-8")).decode("utf-8"),
                                 "is_member", base64.standard_b64encode("yes".encode("utf-8")).decode("utf-8") ]
      if self.user_info.get_lookup_info().nre_info != None:
          registration_list.append("classification_info")
          registration_list.append(base64.standard_b64encode(self.user_info.get_lookup_info().nre_info.encode("utf-8")).decode("utf-8"))
    
      quoted_course = urllib.parse.quote(chosen_course.encode("utf-8"))
      quoted_name = urllib.parse.quote(found_name.encode("utf-8"))
      registration_params = "key={}&event={}&course={}&registration_info={}&competitor_name={}"\
                                   .format(self.event_key, self.event, quoted_course, ",".join(registration_list), quoted_name)
      if self.debug: print("Attempting to register {} with params {}.".format(found_name, registration_params))
            
      if self.force_exit_called: return
      try:
          output = self.url_caller.make_url_call(url_caller.REGISTER_COMPETITOR, registration_params)
      except UrlTimeoutException:
          output = r"####,ERROR,Connectivity error - validate internet connectivity and site status"
      if self.force_exit_called: return
      if self.debug: print ("Results of web call {}.".format(output))
                  
      registration_result = re.search(r"####,RESULT,(.*)", output)
      nre_class_result = re.search(r"####,CLASS,(.*)", output)
      errors = re.findall(r"####,ERROR,(.*)", output)
    
      output_message = ""
      if registration_result == None:
          output_message = "Registration failed: "
          error_found = True
      else:
          output_message = registration_result.group(1) + " "
          if nre_class_result != None:
              output_message += f"({nre_class_result.group(1)}) "
          error_found = False
    
      if (len(errors) > 0):
          output_message += "\n".join(errors)
          error_found = True
    
      self.user_info.get_widget().show_as_error(error_found)
      self.user_info.get_widget().update(output_message)
    
      if self.completion_callback != None:
          self.completion_callback(self)

      return
    
    
    def exit_registration_flow(self):
        if self.registration_frame != None:
            self.registration_frame.destroy()
            self.registration_frame = None
        if self.completion_callback != None:
            self.completion_callback(self)
        self.user_info.get_widget().enable_buttons()

    def force_exit(self):
        super().force_exit()
        self.force_exit_called = True
    
