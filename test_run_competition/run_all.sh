#!/bin/sh

php test_name_matcher.php
if [ -f failure ]
then
  echo ERROR: possible test failure?  Failure file present.
  exit 1
fi

for i in test_*.pl
do
  perl ./${i}
  if [ -f members.csv ]
  then
    echo ERROR: Test did not cleanup, did it fail perhaps\?
    exit 1
  fi
done
