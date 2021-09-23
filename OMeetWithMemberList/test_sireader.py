#!/usr/bin/python

from sportident import SIReader, SIReaderReadout, SIReaderControl, SIReaderException
from time import sleep
import sys

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
  if sys.platform == 'win32':
    for i in range(256):
      try:
        com_port = "COM{:d}".format(i)
        print ("Trying {}".format(com_port))
        si = SIReaderReadout(port=com_port)
        print ("{} appears ok".format(com_port))
        break
      except SIReaderException as sire:
        si = None
  else:
    si = SIReaderReadout()
except SIReaderException as sire:
  si = None
  print "Cannot find si download station, reason: {}".format(sire)

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

print("The start time is {:d}.".format(card_data['start']))
print("The end time is {:d}.".format(card_data['finish']))
print("The check time is {:d} - why do I want this?".format(card_data['check']))
print("The punch list is {}.".format(card_data['punches']))  # list of (station,time) tuples

string_of_punches = ";".join(map(lambda punch: ",".join(punch), card_data['punches']))
print("The reformatted punch list is {}.".format(string_of_punches))

