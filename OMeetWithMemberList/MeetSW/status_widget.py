import tkinter as tk
import tkinter.ttk as ttk

class status_widget_base:

    def __init__(self, stick_number, resizeable_font):
        self.stick_number = stick_number
        self.font = resizeable_font

    def create(self, enclosing_frame, message):
        result_frame = tk.LabelFrame(enclosing_frame)
        button_frame = tk.Frame(result_frame)
        label_frame = tk.Frame(result_frame)
        stick_label = tk.Label(label_frame, text=self.stick_number, borderwidth=2, relief=tk.SUNKEN, font=self.font)
    
        self.stick_status = tk.Label(label_frame, text=message, font=self.font)
        self.stick_status["fg"] = "green"
    
        stick_ack = tk.Button(button_frame, text="Close notification", command=result_frame.destroy, font=self.font)
        self.stick_ack = stick_ack
        self.add_buttons(button_frame)
    
        # Buttons are disabled by default
        stick_ack.configure(state = tk.DISABLED)
    
    
        stick_label.pack(side=tk.LEFT)
        self.stick_status.pack(side=tk.LEFT, fill=tk.X)
        self.pack_buttons()
        stick_ack.pack(side=tk.RIGHT, padx=5)
        label_frame.pack(side=tk.TOP, fill=tk.X)
        button_frame.pack(side=tk.TOP, fill=tk.X)

        self.status_frame = result_frame
        return

    def add_buttons(self, button_frame):
        pass

    def pack_buttons(self):
        pass

    def show_as_error(self, is_error):
        if is_error:
            self.stick_status["fg"] = "red"
        else:
            self.stick_status["fg"] = "green"

    def update(self, message):
        self.stick_status.configure(text=message)
        self.enable_buttons()
        return

    def show(self, root_frame):
        root_frame.after(1, lambda: self.show_status_widget_mainloop())

    def show_status_widget_mainloop(self):
        self.status_frame.pack(side=tk.TOP, fill=tk.X, pady=5)

    def enable_buttons(self):
        self.stick_ack.configure(state = tk.NORMAL)

    def disable_buttons(self):
        self.stick_ack.configure(state = tk.DISABLED)

class status_widget(status_widget_base):
    def __init__(self, stick_number, resizeable_font, user_info, replay_function, register_function):
        super().__init__(stick_number, resizeable_font)
        self.replay_function = replay_function
        self.register_function = register_function
        self.user_info = user_info
        self.can_register = False
        self.can_replay = False

    def add_buttons(self, button_frame):
        self.stick_register = tk.Button(button_frame, text="Register for course", font=self.font)
        self.stick_replay = tk.Button(button_frame, text="Download stick info", font=self.font)
        self.stick_replay.configure(command=lambda: self.replay_function(self.user_info))
        self.stick_register.configure(command=lambda: self.register_function(self.user_info))

        # Buttons are disabled by default
        self.stick_replay.configure(state = tk.DISABLED)
        self.stick_register.configure(state = tk.DISABLED)

    def pack_buttons(self):
        self.stick_replay.pack(side=tk.LEFT)
        self.stick_register.pack(side=tk.LEFT, padx=5)

    def enable_buttons(self):
        if self.can_replay:
            self.stick_replay.configure(state = tk.NORMAL)
        if self.can_register:
            self.stick_register.configure(state = tk.NORMAL)
        super().enable_buttons()

    def disable_buttons(self):
        self.stick_replay.configure(state = tk.DISABLED)
        self.stick_register.configure(state = tk.DISABLED)
        super().disable_buttons()

    def set_can_replay(self, user_can_download):
        self.can_replay = user_can_download

    def set_can_register(self, user_is_known):
        self.can_register = user_is_known


class offline_status_widget(status_widget_base):
    
    def __init__(self, stick_number, resizeable_font):
        super().__init__(stick_number, resizeable_font)

    def add_buttons(self, button_frame):
        pass

    def pack_buttons(self):
        pass

    def enable_buttons(self):
        super().enable_buttons()

    def disable_buttons(self):
        super().disable_buttons()

class mass_start_status_widget(status_widget_base):

    def __init__(self, stick_number, resizeable_font, user_info, mass_start_function, start_seconds, event_key, event):
        super().__init__(stick_number, resizeable_font)
        self.mass_start_function = mass_start_function
        self.user_info = user_info
        self.start_seconds = start_seconds
        self.event_key = event_key
        self.event = event

    def add_buttons(self, button_frame):
        self.stick_mass_start = tk.Button(button_frame, text="Mass start course(s)", font=self.font)
        self.stick_mass_start.configure(command=lambda: self.mass_start_function(self.user_info, self.start_seconds, self.event_key, self.event))

        # Buttons are disabled by default
        self.stick_mass_start.configure(state = tk.DISABLED)

    def pack_buttons(self):
        self.stick_mass_start.pack(side=tk.LEFT)

    def enable_buttons(self):
        super().enable_buttons()
        self.stick_mass_start.configure(state = tk.NORMAL)

    def disable_buttons(self):
        super().disable_buttons()
        self.stick_mass_start.configure(state = tk.DISABLED)


