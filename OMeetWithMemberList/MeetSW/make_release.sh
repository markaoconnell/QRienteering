#!/bin/sh

echo Updating the version file
build_number=$(( $(cat build_number) + 1 ))
build_date=$(date +%Y%m%d)
echo ${build_number} > build_number

cat <<END_OF_SCRIPT > version.py
build_number=${build_number}
build_string="${build_date}"

class version:

    def __init__(self):
        self.version_string=f"{build_string} - {build_number}"

    def get_version(self):
        return(self.version_string)
END_OF_SCRIPT

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

mv version.py last_release_version.py
echo Restoring the version file
build_number=$(( $(cat build_number) + 1 ))
echo ${build_number} > build_number
cat <<END_OF_SCRIPT > version.py
build_number=${build_number}
build_string="${build_date}"

class version:

    def __init__(self):
        self.version_string=f"{build_string} - {build_number}"

    def get_version(self):
        return(self.version_string)
END_OF_SCRIPT

