import tkinter as tk
import tkinter.ttk as ttk
from threading import Thread
import time

mode_label = None
mode_button = None
download_mode = True
exit_all_threads = False
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

def make_status(enclosing_frame, stick, message, is_error):
    result_frame = tk.LabelFrame(enclosing_frame)
    button_frame = tk.Frame(result_frame)
    label_frame = tk.Frame(result_frame)
    stick_label = tk.Label(label_frame, text=stick, borderwidth=2, relief=tk.SUNKEN)
    stick_status = tk.Label(label_frame, text=message)
    if is_error:
        stick_status["fg"] = "red"
    else:
        stick_status["fg"] = "green"
    stick_ack = tk.Button(button_frame, text="Ack", command=result_frame.destroy)
    stick_register = tk.Button(button_frame, text="Register")
    stick_replay = tk.Button(button_frame, text="Replay")
    buttons_to_disable = [stick_ack, stick_register, stick_replay]
    stick_replay.configure(command=lambda: replay_stick(stick, stick_status, buttons_to_disable))
    stick_label.pack(side=tk.LEFT)
    stick_status.pack(side=tk.LEFT, fill=tk.X)
    stick_replay.pack(side=tk.LEFT)
    stick_ack.pack(side=tk.LEFT, padx=5)
    stick_register.pack(side=tk.LEFT, padx=5)
    label_frame.pack(side=tk.TOP, fill=tk.X)
    button_frame.pack(side=tk.TOP, fill=tk.X)
    result_frame.pack(side=tk.TOP, fill=tk.X, pady=5)

def replay_stick(stick, stick_status_widget, buttons_to_disable):
    stick_status_widget["text"] = f"Replaying SI results for {stick}"
    for button in buttons_to_disable:
        button.configure(state=tk.DISABLED)
    replay_thread = Thread(target=replay_stick_thread, args=(stick, stick_status_widget, buttons_to_disable))
    replay_thread.start()
    return

def replay_stick_thread(stick, stick_status_widget, buttons_to_enable):
    interruptible_sleep(10)

    if exit_all_threads: return

    stick_status_widget["text"] = "Here are the results of the replay at: " + time.strftime("%H:%M:%S", time.localtime())
    stick_status_widget["fg"] = "green"

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
    interruptible_sleep(10)
    if exit_all_threads: return
    make_status(status_frame, "2108369", "Mark OConnell, 1h40m21s on Red, course complete", False)

    interruptible_sleep(5)
    if exit_all_threads: return
    make_status(status_frame, "5083959473", "Karen Yeowell, 1h25m21s on Red, course complete", False)

    interruptible_sleep(22)
    if exit_all_threads: return
    make_status(status_frame, "USD", "John OConnell, 2h25m21s on Red, DNF", True)

    interruptible_sleep(10)
    if exit_all_threads: return
    make_status(status_frame, "314159", "Billy OConnell, 1h40m21s on White, course complete", False)

    interruptible_sleep(10)
    if exit_all_threads: return
    make_status(status_frame, "271828", "Lydia OConnell, 25m21s on Red, course complete", False)

    interruptible_sleep(10)
    if exit_all_threads: return
    make_status(status_frame, "141421", "Janet Berrill, 1h15m31s on Yellow, DNF", True)

    interruptible_sleep(30)
    if exit_all_threads: return
    make_status(status_frame, "me-myself", "Judith Berrill, 1h45m31s on Yellow, course complete", False)

    interruptible_sleep(30)
    if exit_all_threads: return
    make_status(status_frame, "678234", "The sun also rises, 1h19m58s on Orange, course complete", False)

    interruptible_sleep(30)
    if exit_all_threads: return
    make_status(status_frame, "349821", "The Beatles, 1h05m01s on Brown, course complete", False)

    return



root = tk.Tk()
root.geometry("750x500");
root.title("QRienteering SI download station")
mode_frame = tk.Frame(root, highlightbackground="blue", highlightthickness=5)
status_frame = tk.Frame(root)
mode_frame.pack(fill=tk.X, side=tk.TOP)
status_frame.pack(fill=tk.BOTH, side=tk.TOP, pady=10)

mode_label = tk.Label(mode_frame, text="In Download mode")
mode_button = tk.Button(mode_frame, text="Switch to register mode", command=switch_mode)
mode_label.pack(side=tk.LEFT) 
mode_button.pack(side=tk.LEFT, padx=5)
exit_button = tk.Button(mode_frame, text="Exit", command=root.destroy)
exit_button.pack(side=tk.RIGHT)



status_reader_thread = Thread(target=slowly_add_status)
status_reader_thread.start()

root.mainloop()

exit_all_threads = True
