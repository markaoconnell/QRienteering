import tkinter as tk
import tkinter.ttk as ttk
import tkinter.font as font
from LongRunningClass import LongRunningClass

class change_font_size_flow(LongRunningClass):

    def __init__(self, font):
        super().__init__()
        self.change_font_size_frame = None
        self.local_font = font
        self.force_exit_called = False
        self.completion_callback = None
        self.new_font_size = None
        pass

    def add_completion_callback(self, callback):
        self.completion_callback = callback

    def get_new_font_size(self):
        return self.new_font_size

    
    def create_font_size_change_window(self, current_font_size):
        self.change_font_size_frame = tk.Toplevel()
        self.change_font_size_frame.geometry("300x300")
        self.change_font_size_frame.title("Change font size")
    
        choices_frame = tk.Frame(self.change_font_size_frame)
        button_frame = tk.Frame(self.change_font_size_frame)
        info_label = tk.Label(choices_frame, text="Enter new font size:", font=self.local_font)
        info_label.pack(side=tk.TOP, anchor=tk.W)
    
        new_font_size = tk.StringVar(choices_frame, "")
        if current_font_size != None:
          new_font_size.set(str(current_font_size))
          
        font_size_box = tk.Entry(choices_frame, textvariable = new_font_size, font=self.local_font)
        font_size_box.pack(side=tk.TOP, anchor=tk.W)
    
    
        ok_button = tk.Button(button_frame, text="Change font size", command=lambda: self.make_font_size_change(info_label, new_font_size), font=self.local_font)
        cancel_button = tk.Button(button_frame, text="Cancel", command=lambda: self.kill_change_font_size_frame(), font=self.local_font)
    
        ok_button.pack(side=tk.LEFT)
        cancel_button.pack(side=tk.LEFT)
    
        choices_frame.pack(side=tk.TOP)
        button_frame.pack(side=tk.TOP)
    
        self.change_font_size_frame.protocol("WM_DELETE_WINDOW", lambda: self.kill_change_font_size_frame())
        return

    
    #####################################################################
    def make_font_size_change(self, info_label, new_font_size):
        new_font_size_int = -1
        try:
            new_font_size_int = int(new_font_size.get())
        except ValueError:
            pass
    
        if new_font_size_int != -1:
            self.new_font_size = new_font_size_int
            if self.completion_callback != None:
                self.completion_callback(self)
            self.kill_change_font_size_frame()
        else:
            info_label.configure(text="Please enter a valid font size:")
    
    
    
    #####################################################################
    def kill_change_font_size_frame(self):
        if self.change_font_size_frame != None:
            self.change_font_size_frame.destroy()
        self.change_font_size_frame = None


    def force_exit(self):
        super().force_exit()
        self.force_exit_called = True
    

