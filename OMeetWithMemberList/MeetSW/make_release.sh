#!/bin/sh

echo Starting to build release version of manage_event.py
PYTHONPATH="../../../sportident-python" pyinstaller -y manage_event.py

if [ -d "./dist/manage_event" ]
then
  echo Build successful, pausing briefly
  sleep 2
  echo Build successful, installing in NEOC_SI_reader
  mv  "../../NEOC_SI_reader/NEOC SI Reader data files/dist/manage_event" "../../NEOC_SI_reader/NEOC SI Reader data files/dist/manage_event.old"
  mv  ./dist/manage_event "../../NEOC_SI_reader/NEOC SI Reader data files/dist/manage_event" 
  rm -rf "../../NEOC_SI_reader/NEOC SI Reader data files/dist/manage_event.old"
fi
