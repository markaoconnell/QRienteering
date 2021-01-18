#!/bin/sh

testing_directory="../OMeetData/TestingDirectory";

if [ -d "${testing_directory}" ]
then
  echo Removing ${testing_directory}
  rm -rf ${testing_directory}
  rm -rf event-[0-9a-f]*-results.log
else
  echo No directory found ${testing_directory}, no cleanup appears to be needed.
fi
