#!/usr/bin/python

from sireader import SIReader, SIReaderReadout, SIReaderControl

SIReader only supports the so called "Extended Protocol" mode. If your
base station is not in this mode you have to change the protocol mode
first::

  # change to extended protocol mode
  si.set_extended_protocol()

To use a SportIdent base station for card readout::

  from time import sleep

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

