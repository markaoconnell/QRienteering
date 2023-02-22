<?php
function xlate_control_id_for_display($key, $event, $control_id) {
  $control_xlation_path = get_event_path($event, $key) . "/xlations/{$control_id}";
  if (file_exists($control_xlation_path)) {
    return(file_get_contents($control_xlation_path));
  }
  else {
    return($control_id);
  }
}

function set_xlation_for_control($key, $event, $control_id, $xlation) {
  $control_xlation_dir = get_event_path($event, $key) . "/xlations";
  $control_xlation_path = "{$control_xlation_dir}/{$control_id}";
  if (!is_dir($control_xlation_dir)) {
    mkdir($control_xlation_dir);
  }

  file_put_contents($control_xlation_path, $xlation);
}

function remove_xlation_for_control($key, $event, $control_id) {
  $control_xlation_dir = get_event_path($event, $key) . "/xlations";
  $control_xlation_path = "{$control_xlation_dir}/{$control_id}";
  if (file_exists($control_xlation_path)) {
    unlink($control_xlation_path);
  }
}

function get_control_xlations($key, $event) {
  $control_xlation_dir = get_event_path($event, $key) . "/xlations";
  if (is_dir($control_xlation_dir)) {
    $xlation_entries = scandir($control_xlation_dir);
    $xlation_entries = array_diff($xlation_entries, array(".", ".."));
    $xlation_hash = array();
    array_map(function ($elt) use (&$xlation_hash, $control_xlation_dir) { $xlation_hash[$elt] = file_get_contents("{$control_xlation_dir}/{$elt}"); }, $xlation_entries);
    return($xlation_hash);
  }
  else {
    return(array());
  }
}
?>
