#!/bin/sh

for i in test_*.pl
do
  perl ./${i}
  if [ -d UnitTestingEvent ]
  then
    echo ERROR: Test did not cleanup, did it fail perhaps\?
    exit 1
  fi
done
