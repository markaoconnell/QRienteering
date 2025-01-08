from LongRunningClass import LongRunningClass
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
from user_info import found_user_info


# raised when the website fails to respond in time
class UrlTimeoutException(Exception):
    pass


class url_caller(LongRunningClass):

    # URLs for the web site
    VIEW_RESULTS = "OMeet/view_results.php"
    FINISH_COURSE = "OMeet/finish_course.php"
    REGISTER_COURSE = "OMeetRegistration/register.php"
    MANAGE_EVENTS = "OMeetMgmt/manage_events.php"
    REGISTER_COMPETITOR = "OMeetRegistration/register_competitor.php"
    SI_LOOKUP = "OMeetWithMemberList/stick_lookup.php"
    MASS_START = "OMeetMgmt/mass_start_courses.php"

    def __init__(self, base_url, timeout):
        self.web_site_timeout = timeout
        self.testing_run = False
        self.debug = False
        self.verbose = False
        self.xlated_event_key = None
        self.force_exit = False
        self.url = base_url

    def set_testing_run(self, testing_run):
        self.testing_run = testing_run

    ##################################################################
    # May raise UrlTimeoutException
    def make_url_call(self, php_script_to_call, params):
    
      if (self.testing_run and os.path.isfile("../" + php_script_to_call)):
        param_pair_list = re.split("&", params)
        param_kv_list = map(lambda param_pair: re.split("=", param_pair), param_pair_list)
        artificial_get_line_list = map(lambda param_kv: "GET {} {}".format(param_kv[0], param_kv[1]), param_kv_list)
        artificial_get_file_content = "\n".join(artificial_get_line_list)
        with open("./artificial_input", "w") as output_file:
          output_file.write(artificial_get_file_content)
        cmd = "php ../{}".format(php_script_to_call)
      else:
        # 10 second timeout on calls to the web site
        #cmd = "curl -m 10 -s \"{}/{}?{}\"".format(url, php_script_to_call, params)
        cmd = f"curl -m {self.web_site_timeout} -s \"{self.url}/{php_script_to_call}?{params}\""
    
      if (self.debug):
        print("Running " + cmd)
    
      try:
        output = subprocess.check_output(cmd, shell=True)
      except subprocess.CalledProcessError as cpe:
        output = cpe.output
    
      if (self.debug):
        print("Command output is: " + output.decode("utf-8"))
    
      decoded_output = output.decode("utf-8")
      found_closing_tag = re.search(r"</html>", decoded_output)
      if found_closing_tag == None:
          # No output, the command must have timed out
          raise UrlTimeoutException
    
      # convert to character representation before returning it
      return decoded_output

    ############################################################################
    def get_xlated_key(self):
        return self.xlated_event_key
    
    ############################################################################
    def get_event_list(self, current_event_key):
      try:
        output = self.make_url_call(self.MANAGE_EVENTS, "key=" + current_event_key + "&recent_event_timeout=12h")
        #output = self.make_url_call(MANAGE_EVENTS, "key=" + current_event_key + "&recent_event_timeout=120d")
        #print (f"Call to manage_events returned {output}")
      except UrlTimeoutException:
          output = ""
    
      event_matches_list = re.findall(r"####,[A-Z]*_EVENT,.*", output)
      key_match = re.search(r"####,XLATED_KEY,(.*)", output);
      if key_match != None:
        self.xlated_event_key = key_match.group(1);
      else:
        self.xlated_event_key = current_event_key

    
      if (self.debug):
        print("Found " + str(len(event_matches_list)) + " events from the website.")
    
      return event_matches_list
    
    
    
    ###############################################################
    def get_courses(self, event, event_key):
        try:
            output = self.make_url_call(self.VIEW_RESULTS, "event={}&key={}&only_course_list=yes".format(event, event_key))
        except UrlTimeoutException:
            output = ""
            
        if (re.search("####,", output) == None):
            print(f"Event {event} not found, please check if event {event} and key {event_key} are valid.")
    
        if re.search(f"####,Event,{event},", output) == None:
            print(f"Event {event} not found, please check if event {event} and key {event_key} are valid.")
    
        match = re.search("(####,CourseList,.*)", output)
        if match == None:
            print(f"Cannot find course list for {event}, is it a valid event?")
            discovered_courses = None
        else:
            courses = match.group(1).split(",")[2:]
            discovered_courses = list(map(lambda entry: (entry.lstrip("0123456789-"), entry), courses))
    
        if self.verbose: print ("\n".join(map(lambda s: s[0] + " -> " + s[1], discovered_courses)) + "\n")
    
        return discovered_courses
    
    
    ###############################################################
    def upload_results(self, user_info, event_key, event):
      web_site_string = base64.standard_b64encode(user_info.qr_result_string.encode("utf-8")).decode("utf-8")
      web_site_string = re.sub("\n", "", web_site_string)
      web_site_string = re.sub(r"=", "%3D", web_site_string)
    
      try:
          output = self.make_url_call(self.FINISH_COURSE, "event={}&key={}&si_stick_finish={}".format(event, event_key, web_site_string))
          is_timeout = False
      except UrlTimeoutException:
          output = r"####,ERROR,Timed out contacting web site, validate internet connectivity and web site status"
          is_timeout = True
    
      name = "Unknown"
      match = re.search(r"####,RESULT,(.*)", output)
      if match != None:
          finish_entries = match.group(1).split(",")
          name = base64.standard_b64decode(finish_entries[0]).decode("utf-8")
          if (user_info.get_lookup_info() == None) or (user_info.get_lookup_info().name != name):
              if user_info.get_lookup_info() == None:
                  registered_user_info = found_user_info(name=name, stick=user_info.stick_number)
                  user_info.add_lookup_info(registered_user_info)
              else:
                  if self.verbose: print (f"Updating entry from {user_info.get_lookup_info().name} to {name} based on result download.\n")
                  user_info.get_lookup_info().name = name
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
          nre_class_match = re.search(r"####,CLASS,(.*)", output)
          if nre_class_match != None:
              upload_status_string += f"({nre_class_match.group(1)}) - "
      else:
          upload_status_string = f"Error, download of {user_info.stick_number} failed"
    
      error_list = re.findall(r"####,ERROR,.*", output)
      if (len(error_list) == 0):
          is_error = False
          upload_status_string += "OK"
      else:
          error_list = list(map(lambda entry: entry.split(",")[2], error_list))
          is_error = True
          upload_status_string += "\n" + "\n".join(error_list)
    
      return(upload_status_string, is_error, is_timeout)

    #######################################################################################
    # May raise a UrlTimeoutException
    def lookup_si_unit(self, stick, event, check_preregistrations):
    
        lookup_results = None
        if check_preregistrations:
            lookup_results = self.make_lookup_si_unit_call(stick, event, True)
    
        if lookup_results == None:
            lookup_results = self.make_lookup_si_unit_call(stick, event, False)
    
        return lookup_results
    
    
    #######################################################################################
    # May raise a UrlTimeoutException
    def make_lookup_si_unit_call(self, stick, event, check_preregistration):
      if check_preregistration:
          extra_params = "&checkin=true"
      else:
          extra_params = ""
    
      if self.force_exit: return None
      output = self.make_url_call(self.SI_LOOKUP, f"key={self.get_xlated_key()}&event={event}&si_stick={stick}{extra_params}")
      if self.force_exit: return None
    
      if self.debug or self.verbose:
        print ("Got results from si lookup: {}".format(output))
      
      registered_stick_found = re.search(r"####,REGISTERED,(.*)", output)
      if (registered_stick_found != None):
          registered_stick_msg = registered_stick_found.group(1)
      else:
          registered_stick_msg = None

      member_match = re.search(r"####,MEMBER_ENTRY,(.*)", output)
      if member_match == None:
          return (found_user_info(registration_info = registered_stick_msg))
    
      member_elements = member_match.group(1).split(",")
      found_name = base64.standard_b64decode(member_elements[0]).decode("utf-8")
      found_id = member_elements[1]
      found_email = member_elements[2]
      cell_phone = member_elements[3]
      club_name = member_elements[4]
      course = None
      if (len(member_elements) > 5):
          course = member_elements[5]
    
      nre_info = None
      nre_info_match = re.search(r"####,CLASSIFICATION_INFO,(.*)", output)
      if nre_info_match != None:
          nre_info = nre_info_match.group(1)
    
      return (found_user_info(name = found_name, member_id = found_id, email = found_email, club = club_name, stick = stick, cell_phone = cell_phone, course = course, nre_info = nre_info, registration_info = registered_stick_msg))
    
