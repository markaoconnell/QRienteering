<?php

require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';

function error_and_exit_from_test($error_string) {
  echo "ERROR: {$error_string}\n";
  file_put_contents("./failure", "{$error_string}\n");
  exit(1);
}


function create_member_properties($member_properties) {
  $member_properties_list = array_map(function($member_props_key) use ($member_properties)
                                        { return "{$member_props_key} : {$member_properties{$member_props_key}}"; }, array_keys($member_properties));

  file_put_contents("./member_properties.txt", implode("\n", $member_properties_list));
}

// Clean up after prior invocations of the test
if (file_exists("../keys")) {
  unlink("../keys");
}

// Create the key file
// Have the keys file point the event to this directory
file_put_contents("../keys", "testing_key,../test_run_competition,kdkddkkd\n");
$key = "testing_key";

$member_properties = [];
$member_properties["club_name"] = "BOK";
create_member_properties($member_properties);

$member_properties_from_file = get_member_properties(get_base_path($key));

$member_list_file = get_members_path($key, $member_properties_from_file);
if ($member_list_file != "../OMeetData/../test_run_competition/members.csv") {
  error_and_exit_from_test("Did not get default location for members.csv, got {$member_list_file}");
}

$nickname_list_file = get_nicknames_path($key, $member_properties_from_file);
if ($nickname_list_file != "../OMeetData/../test_run_competition/nicknames.csv") {
  error_and_exit_from_test("Did not get default location for nicknames.csv, got {$nickname_list_file}");
}



$member_properties["member_list_file"] = "members_new_filename.csv";
create_member_properties($member_properties);

$member_properties_from_file = get_member_properties(get_base_path($key));

$member_list_file = get_members_path($key, $member_properties_from_file);
if ($member_list_file != "../OMeetData/../test_run_competition/members_new_filename.csv") {
  error_and_exit_from_test("Did not get members_new_filename.csv in default location, got {$member_list_file}");
}

$nickname_list_file = get_nicknames_path($key, $member_properties_from_file);
if ($nickname_list_file != "../OMeetData/../test_run_competition/nicknames.csv") {
  error_and_exit_from_test("Did not get default location for nicknames.csv, got {$nickname_list_file}");
}



unset($member_properties["member_list_file"]);
$member_properties["nickname_list_file"] = "nicknames_new_filename.csv";
create_member_properties($member_properties);

$member_properties_from_file = get_member_properties(get_base_path($key));

$member_list_file = get_members_path($key, $member_properties_from_file);
if ($member_list_file != "../OMeetData/../test_run_competition/members.csv") {
  error_and_exit_from_test("Did not get default location for members.csv, got {$member_list_file}");
}

$nickname_list_file = get_nicknames_path($key, $member_properties_from_file);
if ($nickname_list_file != "../OMeetData/../test_run_competition/nicknames_new_filename.csv") {
  error_and_exit_from_test("Did not get nicknames_new_filename.csv as nicknames in default location, got {$nickname_list_file}");
}



$member_properties["member_list_path"] = "./testing_directory/members_new_filename.csv";
create_member_properties($member_properties);

$member_properties_from_file = get_member_properties(get_base_path($key));

$member_list_file = get_members_path($key, $member_properties_from_file);
if ($member_list_file != "./testing_directory/members_new_filename.csv") {
  error_and_exit_from_test("Did not get testing_directory location for members_new_filename.csv, got {$member_list_file}");
}

$nickname_list_file = get_nicknames_path($key, $member_properties_from_file);
if ($nickname_list_file != "../OMeetData/../test_run_competition/nicknames_new_filename.csv") {
  error_and_exit_from_test("Did not get nicknames_new_filename.csv as nicknames in default location, got {$nickname_list_file}");
}



$member_properties["member_list_file"] = "./members_new_filename.csv";
$member_properties["nickname_list_path"] = "testing_directory/nicknames_new_filename.csv";
create_member_properties($member_properties);

$member_properties_from_file = get_member_properties(get_base_path($key));

$member_list_file = get_members_path($key, $member_properties_from_file);
if ($member_list_file != "../OMeetData/../test_run_competition/./members_new_filename.csv") {
  error_and_exit_from_test("Did not get members_new_filename.csv in default location when _file and _path specified, got {$member_list_file}");
}

$nickname_list_file = get_nicknames_path($key, $member_properties_from_file);
if ($nickname_list_file != "../OMeetData/../test_run_competition/nicknames_new_filename.csv") {
  error_and_exit_from_test("Did not get nicknames_new_filename.csv as nicknames in default location when _file and _path specified, got {$nickname_list_file}");
}



unset($member_properties["member_list_file"]);
unset($member_properties["nickname_list_file"]);
create_member_properties($member_properties);

$member_properties_from_file = get_member_properties(get_base_path($key));

$member_list_file = get_members_path($key, $member_properties_from_file);
if ($member_list_file != "./testing_directory/members_new_filename.csv") {
  error_and_exit_from_test("Did not get testing_directory location for members_new_filename.csv, got {$member_list_file}");
}

$nickname_list_file = get_nicknames_path($key, $member_properties_from_file);
if ($nickname_list_file != "testing_directory/nicknames_new_filename.csv") {
  error_and_exit_from_test("Did not get nicknames_new_filename.csv as nicknames in testing_directory location, got {$nickname_list_file}");
}



// Clean up after the test
if (file_exists("../keys")) {
  unlink("../keys");
}

if (file_exists("./member_properties.txt")) {
  unlink("./member_properties.txt");
}

echo "Success!\n\n";

?>
