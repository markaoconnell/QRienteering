#!/bin/sh

for i in test_*.php
do
  echo Running tests in ${i}
  php ${i}
  if [ -f failure ]
  then
    echo ERROR: possible test failure?  Failure file present.
    exit 1
  fi
done

for i in test_*.pl
do
  perl ./${i}
  if [ -d ../OMeetData/TestingDirectory ]
  then
    echo ERROR: Test did not cleanup, did it fail perhaps\?
    exit 1
  fi
done
