#!/usr/bin/python

from sireader import SIReader, SIReaderReadout, SIReaderControl
from time import sleep

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
si = SIReaderReadout()

# wait for a card to be inserted into the reader
while not si.poll_sicard():
  sleep(1)

# some properties are now set
card_number = si.sicard
card_type = si.cardtype

# read out card data
card_data = si.read_sicard()

# beep
si.ack_sicard()

print(f"The start time is {card_data['start']}.")
print(f"The end time is {card_data['finish']}.")
print(f"The check time is {card_data['check']} - why do I want this?")
print(f"The punch list is {card_data['punches']}.")  # list of (station,time) tuples

array_of_punches = map(lambda punch: f"{punch[0]}:{((punch[1].hour * 3600) + (punch[1].minute * 60) + (punch[1].second))}", card_data['punches'])
string_of_punches = ",".join(array_of_punches)

print("The reformatted punch list is {}.".format(string_of_punches))

