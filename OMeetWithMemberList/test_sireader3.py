#!/usr/bin/python

from sireader2 import SIReader, SIReaderReadout, SIReaderControl, SIReaderCardChanged, SIReaderException, SIReaderTimeout
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
si = SIReaderReadout()

while True:
  # wait for a card to be inserted into the reader
  bad_download = False
  found_card = False

  while not found_card:
    try:
      if si.poll_sicard():
        found_card = True
      else:
        sleep(1)
    except SIReaderException as sire:
      print(f"Bad card download, how often does this happen????")

  # some properties are now set
  card_number = si.sicard
  card_type = si.cardtype
  
  # read out card data
  try:
    card_data = si.read_sicard()
  except SIReaderCardChanged as sircc:
    bad_download = True
  
  # beep
  si.ack_sicard()
  
  if not bad_download:
    print(f"The card is {card_number}.")
    print(f"The card type is {card_type}.")
    print(f"The start time is {card_data['start']}.")
    print(f"The end time is {card_data['finish']}.")
    print(f"The check time is {card_data['check']} - why do I want this?")
    print(f"The punch list is {card_data['punches']}.")  # list of (station,time) tuples
    
    array_of_punches = map(lambda punch: f"{punch[0]}:{((punch[1].hour * 3600) + (punch[1].minute * 60) + (punch[1].second))}", card_data['punches'])
    string_of_punches = ",".join(array_of_punches)
    
    print("The reformatted punch list is {}.".format(string_of_punches))
    print(f"Done with reading {card_number}.\n\n")
    sys.stdout.flush()
  
    # Wait for the card to be removed
    while not si.poll_sicard():
      sleep(1)
  else:
    print(f"Bad download of {card_number}, please retry.\n\n")
    sys.stdout.flush()

