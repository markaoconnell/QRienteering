<?php
// Return a string with the time in seconds pretty printed,
// like strftime("%T") but with no timezone adjustments
// (for displaying si unit times)
function format_si_time($time_in_seconds) {
  $hours = floor($time_in_seconds / 3600);
  $mins = floor($time_in_seconds / 60) % 60;
//  $hours = 0;
//  $mins = floor($time_in_seconds / 60);
  $secs = ($time_in_seconds % 60);

  return sprintf("%02d:%02d:%02d", $hours, $mins, $secs);
}

// Return a string with the elapsed time in seconds pretty printed
function formatted_time($time_in_seconds) {
  $hours = floor($time_in_seconds / 3600);
  $mins = floor($time_in_seconds / 60) % 60;
//  $hours = 0;
//  $mins = floor($time_in_seconds / 60);
  $secs = ($time_in_seconds % 60);

  if ($hours > 0) {
    return sprintf("%2dh:%02dm:%02ds", $hours, $mins, $secs);
  }
  else if ($mins > 0) {
    return sprintf("   %2dm:%02ds", $mins, $secs);
  }
  else {
    return sprintf("       %2ds", $secs);
  }
}

// Return a string with the elapsed time in seconds in a compact format
// Currently only used for OUSA results formatting
function formatted_time_compact($time_in_seconds) {
  $hours = floor($time_in_seconds / 3600);
  $mins = floor($time_in_seconds / 60) % 60;
  $secs = ($time_in_seconds % 60);

  return sprintf("%02d:%02d:%02d", $hours, $mins, $secs);
}

// Convert a time limit of the form XXhYYmZZs to seconds.
// Note - all fields are optional
// If just a number, it is assumed to be in seconds (backwards compatability)
// Errors return a limit of -1
function time_limit_to_seconds($time_limit_entry) {
  $limit_in_seconds = 0;

  if (preg_match('/^[0-9]+$/', $time_limit_entry)) {
    // old style entry, assume just in seconds
    return ($time_limit_entry);
  }

  $remaining_time = trim($time_limit_entry);
  if (preg_match('/^[0-9]+M/', $remaining_time)) {
    $month_location = strpos($remaining_time, "M");
    $limit_in_seconds += substr($remaining_time, 0, $month_location) * 86400 * 30;
    $remaining_time = substr($remaining_time, $month_location + 1);
  }

  $remaining_time = trim($remaining_time);
  if (preg_match('/^[0-9]+d/', $remaining_time)) {
    $d_location = strpos($remaining_time, "d");
    $limit_in_seconds += substr($remaining_time, 0, $d_location) * 86400;
    $remaining_time = substr($remaining_time, $d_location + 1);
  }

  $remaining_time = trim($remaining_time);
  if (preg_match('/^[0-9]+h/', $remaining_time)) {
    $h_location = strpos($remaining_time, "h");
    $limit_in_seconds += substr($remaining_time, 0, $h_location) * 3600;
    $remaining_time = substr($remaining_time, $h_location + 1);
  }

  $remaining_time = trim($remaining_time);
  if (preg_match('/^[0-9]+m/', $remaining_time)) {
    $m_location = strpos($remaining_time, "m");
    $limit_in_seconds += substr($remaining_time, 0, $m_location) * 60;
    $remaining_time = substr($remaining_time, $m_location + 1);
  }

  $remaining_time = trim($remaining_time);
  if (preg_match('/^[0-9]+s/', $remaining_time)) {
    $s_location = strpos($remaining_time, "s");
    $limit_in_seconds += substr($remaining_time, 0, $s_location);
    $remaining_time = substr($remaining_time, $s_location + 1);
  }

  if (trim($remaining_time) != "") {
    return(-1);
  }

  return($limit_in_seconds);
}



// Return a string with the elapsed time in seconds formatted for easy parsing
function csv_formatted_time($time_in_seconds) {
  $mins = floor($time_in_seconds / 60);
  $secs = ($time_in_seconds % 60);

  return sprintf("%3d:%02d", $mins, $secs);
}


// Return a string with the elapsed time in seconds formatted for easy parsing
function format_time_as_minutes_since_midnight($unix_timestamp) {
  $hours_mins_secs = strftime("%T", $unix_timestamp);
  $time_pieces = explode(":", $hours_mins_secs);

  $mins = ($time_pieces[0] * 60) + $time_pieces[1];
  $secs = $time_pieces[2];

  return sprintf("%4d:%02d", $mins, $secs);
}

?>
