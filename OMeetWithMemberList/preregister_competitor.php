<?php
require '../OMeetCommon/common_routines.php';
require '../OMeetCommon/course_properties.php';
require 'name_matcher.php';

ck_testing();


$key = isset($_GET["key"]) ? $_GET["key"] : "invalid";
if (!key_is_valid($key)) {
  error_and_exit("Unknown key \"$key\", are you using an authorized link?\n");
}

$base_path = get_base_path($key, "..");
$event = isset($_GET["event"]) ? $_GET["event"] : "invalid";

if (!file_exists("{$base_path}/{$event}")) {
  error_and_exit("Event \"{$event}\" does not appear valid, is this a valid link?\n");
}

$event_name = file_get_contents("{$base_path}/{$event}/description");

if (file_exists("{$base_path}/{$event}/done")) {
  error_and_exit("Event {$event_name} has completed and preregistrations are no longer possible.\n");
}

if (!preregistrations_allowed($event, $key)) {
  error_and_exit("Event {$event_name} is not available for preregistration.\n");
}

$courses_path = get_courses_path($event, $key);
$courses_array = scandir($courses_path);
$courses_array = array_diff($courses_array, array(".", "..")); // Remove the annoying . and .. entries
$current_time = time(NULL);

$success_string = "";

// Look through the entries for prior preregistrations and see if any of these are being preregistered
$num_remembered_entries = isset($_GET["num_remembered_entries"]) ? $_GET["num_remembered_entries"] : 0;
$preregistered_entrants = array();
for ($i = 0; $i < $num_remembered_entries; $i++) {
  if (isset($_GET["remembered-{$i}"])) {
    $value = explode(" ", $_GET["remembered-{$i}"]);  // Format is "base64_encoded_entrant course"
    $this_entrant = explode(":", base64_decode($value[0]));  // Format is base64(firstname:lastname:stick:member_id:timestamp)
    $preregistered_entrants[] = array("first_name" => $this_entrant[0],
                                      "last_name" => $this_entrant[1],
                                      "stick" => $this_entrant[2],
                                      "member_id" => $this_entrant[3],
                                      "course" => $value[1],
                                      "timestamp" => $current_time);

    $success_string .= "Preregistered {$this_entrant[0]} {$this_entrant[1]} on {$value[1]}<br>\n";
  }
}

if (isset($_GET["competitor_first_name"]) && ($_GET["competitor_first_name"] != "") && isset($_GET["competitor_last_name"]) && ($_GET["competitor_last_name"] != "")) {
  $first_name = $_GET["competitor_first_name"];
  $last_name = $_GET["competitor_last_name"];
  $stick = isset($_GET["competitor_si_stick"]) ? $_GET["competitor_si_stick"] : "";
  $course = isset($_GET["competitor_course"]) ? $_GET["competitor_course"] : "";
  $is_member = isset($_GET["competitor_is_member"]) ? $_GET["competitor_is_member"] : "";

  if ($is_member) {
    $member_properties = get_member_properties(get_base_path($key));
    $matching_info = read_names_info(get_members_path($key, $member_properties), get_nicknames_path($key, $member_properties));

    $possible_member_ids = find_best_name_match($matching_info, $first_name, $last_name);


    $error_string = "";
    if (count($possible_member_ids) == 0) {
      error_and_exit("No such member {$first_name} {$last_name} found, please retry or ask for assistance.\n");
    }
    else if (count($possible_member_ids) == 1) {
      $name_array = get_member_name_info($possible_member_ids[0], $matching_info);
      $si_stick = get_si_stick($possible_member_ids[0], $matching_info);
      $preregistered_entrants[] = array("first_name" => $name_array[0],
                                        "last_name" => $name_array[1],
                                        "stick" => $stick,
                                        "member_id" => $possible_member_ids[0],
                                        "course" => $course,
                                        "timestamp" => $current_time);
      $success_string .= "Preregistered {$name_array[0]} {$name_array[1]} on {$course}<br>\n";
    }
    else {
      $success_string .= "<p>Ambiguous member name, please choose:\n";
      $success_string .= "<form action=\"name_lookup.php\">\n";
      foreach ($possible_member_ids as $possible_member) {
        $success_string .= "<p><input type=radio name=\"member_id\" value=\"{$possible_member}\"> " . get_full_name($possible_member, $matching_info) . "\n";
      }
      $success_string .= "<input type=\"hidden\" name=\"key\" value=\"{$key}\">\n";
      $success_string .= "<p><input type=submit name=\"Choose member\"/>\n";
      $success_string .= "</form>\n";
    }
  }
  else {
    // Not a member
    $preregistered_entrants[] = array("first_name" => $first_name,
                                      "last_name" => $last_name,
                                      "stick" => $stick,
                                      "member_id" => "not_a_member",
                                      "course" => $course,
                                      "timestamp" => $current_time);
    $success_string .= "Preregistered {$first_name} {$last_name} on {$course}<br>\n";
  }
}


// Check if already preregistered

// add in as a preregistration
$num_preregistered = 0;
foreach ($preregistered_entrants as $this_entrant) {

  // Generate the preregister_id and make sure it is truly unique
  $tries = 0;
  while ($tries < 5) {
    $preregister_id = uniqid() . "-{$num_preregistered}-{$tries}";
    $preregister_path = get_preregistered_entrant($preregister_id, $event, $key);
    $preregister_file = fopen("{$preregister_path}", "x");
    if ($preregister_file !== false) {
      break;
    }
    $tries++;
  }

  if ($tries == 5) {
    error_and_exit("ERROR: Cannot process preregistrations now, please retry later.\n");
  }

  $entrant_pieces = array();
  foreach (array_keys($this_entrant) as $this_entrant_key) {
    $entrant_pieces[] .= "${this_entrant_key}," . base64_encode($this_entrant[$this_entrant_key]);
  }

  // Save the information about the competitor
  fwrite($preregister_file, implode(":", $entrant_pieces));
  fclose($preregister_file);

  $num_preregistered++;

  $success_string .= "<p>Preregistered {$this_entrant["first_name"]} {$this_entrant["last_name"]} on {$this_entrant["course"]}.\n";
}

// ********************
// Set the new cookie
$new_cookie_entries = array();
if (isset($_COOKIE["{$key}-preregistrations"])) {
  // Filter out the expired entries
  // Format will be base64(first_name:last_name:stick:member_id:timestamp_of_last_registration),base64(first_name:last_name:stick:member_id:timestamp_of_last_registration),...
  $entries = explode(",", $_COOKIE["{$key}-preregistrations"]);
  $time_cutoff = time() - (86400 * 90);  // 3 month window
  foreach ($entries as $saved_entrant) {
    $this_entrant = explode(":", base64_decode($saved_entrant));
    if ($this_entrant[4] > $time_cutoff) {
      $entry_for_cookie = array("first_name" => $this_entrant[0],
                                        "last_name" => $this_entrant[1],
                                        "stick" => $this_entrant[2],
                                        "member_id" => $this_entrant[3],
                                        "timestamp" => $this_entrant[4],
                                        "cookie_value" => $saved_entrant);
      $new_cookie_entries[] = $entry_for_cookie;
    }
  }
}

// Add the new entries
// If the same person is registering as in the cookie, then replace the entry with the new timestamp
// Otherwise just add a new entry
foreach ($preregistered_entrants as $this_entrant) {
  $new_entry = base64_encode("{$this_entrant["first_name"]}:{$this_entrant["last_name"]}:{$this_entrant["stick"]}:{$this_entrant["member_id"]}:{$this_entrant["timestamp"]}");
  for ($i = 0; $i < count($new_cookie_entries); $i++) {
    if (($new_cookie_entries[$i]["first_name"] == $this_entrant["first_name"]) && 
        ($new_cookie_entries[$i]["last_name"] == $this_entrant["last_name"]) && 
        ($new_cookie_entries[$i]["stick"] == $this_entrant["stick"]) && 
        ($new_cookie_entries[$i]["member_id"] == $this_entrant["member_id"])) {
      $new_cookie_entries[$i]["cookie_value"] = $new_entry;
      break;
    }
  }

  if ($i >= count($new_cookie_entries)) {
    $new_cookie_entries[$i]["first_name"] = $this_entrant["first_name"];
    $new_cookie_entries[$i]["last_name"] = $this_entrant["last_name"];
    $new_cookie_entries[$i]["stick"] = $this_entrant["stick"];
    $new_cookie_entries[$i]["member_id"] = $this_entrant["member_id"];
    $new_cookie_entries[$i]["timestamp"] = $this_entrant["timestamp"];
    $new_cookie_entries[$i]["cookie_value"] = $new_entry;
  }
}

$cookie_path = isset($_SERVER["REQUEST_URI"]) ? dirname(dirname($_SERVER["REQUEST_URI"])) : "";
$new_cookie_value = implode(",", array_map(function ($entry) { return ($entry["cookie_value"]); }, $new_cookie_entries));
setcookie("{$key}-preregistrations", $new_cookie_value, $current_time + 86400 * 90, $cookie_path);


echo get_web_page_header(true, false, true);

echo $success_string;

echo get_web_page_footer();
?>
