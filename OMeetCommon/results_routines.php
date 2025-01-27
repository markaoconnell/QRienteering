<?php

// Show the results for a course
function show_results($event, $key, $course, $result_class, $show_points, $max_points, $base_course_list, $path_to_top = "..") {
  $result_string = "";
  $result_string .= "<p>Results on " . ltrim($course, "0..9-") . (($result_class != "") ? ":{$result_class}" : "") . "\n";

  if ($result_class == "") {
    $results_path = get_results_path($event, $key);
    if (count($base_course_list) == 0) {
      if (!is_dir("{$results_path}/{$course}")) {
        $result_string .= "<p>No Results yet<p><p><p>\n";
        return($result_string);
      }
      $results_list = scandir("{$results_path}/{$course}");
    }
    else {
      $results_list = array();
      foreach ($base_course_list as $course_to_check) {
        if (is_dir("{$results_path}/{$course_to_check}")) {
          $these_results = scandir("{$results_path}/{$course_to_check}");
	  $these_results = array_diff($these_results, array(".", ".."));
	  $results_list = array_merge($results_list, $these_results);
	}
      }

      if (count($results_list) == 0) {
        $result_string .= "<p>No Results yet<p><p><p>\n";
        return($result_string);
      }
      else {
        sort($results_list);
      }
    }
  }
  else {
    $results_path = get_results_per_class_path($event, $key);
    if (!is_dir("{$results_path}/{$result_class}")) {
      $result_string .= "<p>No Results yet<p><p><p>\n";
      return($result_string);
    }
    $results_list = scandir("{$results_path}/{$result_class}");
  }
  $results_list = array_diff($results_list, array(".", ".."));
  if (count($results_list) == 0) {
    $result_string .= "<p>No Results yet<p><p><p>\n";
    return($result_string);
  }

  if ($show_points) {
    $points_header = "<th>Points</th>";
  }
  else {
    $points_header = "";
  }

  $finish_place = 0;

  $result_string .= "<table border=1><tr><th>Place</th><th>Name</th><th>Time</th>{$points_header}</tr>\n";
  $dnfs = "";
  foreach ($results_list as $this_result) {
    $finish_place++;
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key);
    $competitor_name = file_get_contents("{$competitor_path}/name");
    if ($show_points) {
      $points_value = "<td>" . ($max_points - $result_pieces[0]) . "</td>";
    }
    else {
      $points_value = "";
    }

    if (file_exists("./{$competitor_path}/self_reported")) {
      if (file_exists("./{$competitor_path}/dnf")) {
        $dnfs .= "<tr><td>{$finish_place}</td><td>{$competitor_name}</td><td>DNF</td>{$points_value}</tr>\n";
      }
      else if (file_exists("./{$competitor_path}/no_time")) {
        $result_string .= "<tr><td>{$finish_place}</td><td>{$competitor_name}</td><td>No time</td>{$points_value}</tr>\n";
      }
      else {
        $result_string .= "<tr><td>{$finish_place}</td><td>{$competitor_name}</td><td>" . formatted_time($result_pieces[1]) . "</td>{$points_value}</tr>\n";
      }
    }
    else if (!file_exists("./{$competitor_path}/dnf")) {
      $result_string .= "<tr><td>{$finish_place}</td><td><a href=\"../OMeet/show_splits.php?event={$event}&key={$key}&entry={$this_result}\">{$competitor_name}</a></td><td>" . formatted_time($result_pieces[1]) . "</td>{$points_value}</tr>\n";
    }
    else {
      // For a scoreO course, there are no DNFs, so $points_value should always be "", but show it just in case
      $dnfs .= "<tr><td>{$finish_place}</td><td><a href=\"../OMeet/show_splits.php?event={$event}&key={$key}&entry={$this_result}\">{$competitor_name}</a></td><td>DNF</td>{$points_value}</tr>\n";
    }
  }
  $result_string .= "{$dnfs}</table>\n<p><p><p>";
  return($result_string);
}

// Show the results for a course as a csv
function get_csv_results($event, $key, $course, $result_class, $show_points, $max_points, $base_course_list, $path_to_top = "..") {
  $result_string = "";
  $readable_course_name = ltrim($course, "0..9-");
  $class_for_results = "";

  if ($result_class == "") {
    // No results yet - .csv is empty
    $results_path = get_results_path($event, $key);
    if (count($base_course_list) == 0) {
      if (!is_dir("{$results_path}/{$course}")) {
        return("");
      }
      $results_list = scandir("{$results_path}/{$course}");
    }
    else {
      $results_list = array();
      foreach ($base_course_list as $course_to_check) {
        if (is_dir("{$results_path}/{$course_to_check}")) {
          $these_results = scandir("{$results_path}/{$course_to_check}");
	  $these_results = array_diff($these_results, array(".", ".."));
	  $results_list = array_merge($results_list, $these_results);
	}
      }

      if (count($results_list) == 0) {
        return("");
      }
      else {
        sort($results_list);
      }
    }
  }
  else {
    $class_for_results = ";{$result_class}";

    // No results yet - .csv is empty
    $results_path = get_results_per_class_path($event, $key);
    if (!is_dir("{$results_path}/{$result_class}")) {
      return("");
    }
    $results_list = scandir("{$results_path}/{$result_class}");
  }

  $results_list = array_diff($results_list, array(".", ".."));

  $dnfs = "";
  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key);
    $competitor_name = file_get_contents("{$competitor_path}/name");
    if ($show_points) {
      $points_value = $max_points - $result_pieces[0];
    }
    else {
      $points_value = "";
    }

    $nre_info = ";;;;";
    if (file_exists("{$competitor_path}/registration_info")) {
      $registration_info = parse_registration_info(file_get_contents("{$competitor_path}/registration_info"));
      if (isset($registration_info["classification_info"])) {
        if ($registration_info["classification_info"] != "") {
          $classification_info = decode_entrant_classification_info($registration_info["classification_info"]);
          $nre_info = ";{$classification_info["BY"]};{$classification_info["G"]};";
	  $nre_info .= get_class_for_competitor($competitor_path) . ";";
        }

      }
      if (isset($registration_info["club_name"])) {
        $nre_info .= "{$registration_info["club_name"]};";
      }
      else {
        $nre_info .= ";";
      }
    }

    if (!file_exists("{$competitor_path}/dnf")) {
      // 1 is a valid result
      $result_string .= "{$readable_course_name}{$class_for_results};{$competitor_name};" . csv_formatted_time($result_pieces[1]) . ";OK;1;{$points_value}{$nre_info}\n";
    }
    else {
      // 2 is a DNF, 3 is a MissedPunch, manually adjust these afterwards
      $result_string .= "{$readable_course_name}{$class_for_results};{$competitor_name};". csv_formatted_time($result_pieces[1]) . ";DNF;2;{$points_value}{$nre_info}\n";
    }
  }
  return($result_string);
}

// Get the results for a course as an array
function get_results_as_array($event, $key, $course, $show_points, $max_points, $path_to_top = "..") {
  $result_array = array();
  $readable_course_name = ltrim($course, "0..9-");

  // No results yet - .csv is empty
  $results_path = get_results_path($event, $key);
  if (!is_dir("{$results_path}/{$course}")) {
    return($result_array);
  }
  
  $results_list = scandir("{$results_path}/{$course}");
  $results_list = array_diff($results_list, array(".", ".."));

  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key);
    $competitor_name = file_get_contents("{$competitor_path}/name");
    if ($show_points) {
      $points_value = $max_points - $result_pieces[0];
    }
    else {
      $points_value = 0;
    }

    $competitor_result_array = array();
    $competitor_result_array["competitor_id"] = $result_pieces[2];
    $competitor_result_array["competitor_name"] = $competitor_name;
    $competitor_result_array["time"] = csv_formatted_time($result_pieces[1]);
    $competitor_result_array["raw_time"] = $result_pieces[1];
    $competitor_result_array["dnf"] = file_exists("{$competitor_path}/dnf");
    if (file_exists("{$competitor_path}/si_stick")) {
      $competitor_result_array["si_stick"] = file_get_contents("{$competitor_path}/si_stick");
    }
    $competitor_result_array["scoreo_points"] = $points_value;
    $competitor_result_array["competitive_class"] = get_class_for_competitor($competitor_path);
    $competitor_result_array["birth_year"] = "";
    $competitor_result_array["gender"] = "\"\"";
    $competitor_result_array["club_name"] = "\"\"";

    if (event_is_using_nre_classes($event, $key)) {
      if (file_exists("{$competitor_path}/registration_info")) {
        $registration_info = parse_registration_info(file_get_contents("{$competitor_path}/registration_info"));
        if (isset($registration_info["club_name"])) {
          $competitor_result_array["club_name"] = $registration_info["club_name"];
        }

        if (isset($registration_info["classification_info"])) {
          if ($registration_info["classification_info"] != "") {
            $classification_info = decode_entrant_classification_info($registration_info["classification_info"]);
            $competitor_result_array["birth_year"] = $classification_info["BY"];
            $competitor_result_array["gender"] = $classification_info["G"];
  	  }
        }
      }
    }

    $result_array[] = $competitor_result_array;
  }
  return($result_array);
}

// Get the statistics for a course in an event
function get_course_stats($event, $key, $course) {
  $course_results = array("starts" => 0, "members" => 0, "non-members" => 0, "qr_coders" => 0, "self_reported" => 0,
	  "si_unit" => 0, "dnfs" => 0, "complete" => 0);
  $course_results["start_names"] = array();

  // No results yet - .csv is empty
  $results_path = get_results_path($event, $key);
  if (!is_dir("{$results_path}/{$course}")) {
    return($course_results);
  }
  
  $results_list = scandir("{$results_path}/{$course}");
  $results_list = array_diff($results_list, array(".", ".."));

  foreach ($results_list as $this_result) {
    $result_pieces = explode(",", $this_result);
    $competitor_path = get_competitor_path($result_pieces[2], $event, $key);
    $competitor_name = file_get_contents("{$competitor_path}/name");

    $course_results["start_names"][$competitor_name] = 1;
    $course_results["starts"]++;
    if (file_exists("{$competitor_path}/dnf")) {
      $course_results["dnfs"]++;
    }
    else {
      $course_results["complete"]++;
    }

    if (file_exists("{$competitor_path}/si_stick")) {
      $course_results["si_unit"]++;
    }
    else if (file_exists("{$competitor_path}/self_reported")) {
      $course_results["self_reported"]++;
    }
    else {
      $course_results["qr_coders"]++;
    }

    if (file_exists("{$competitor_path}/registration_info")) {
      $registration_info = parse_registration_info(file_get_contents("{$competitor_path}/registration_info"));
      if ($registration_info["is_member"] == "yes") {
        $course_results["members"]++;
      }
      else {
        $course_results["non-members"]++;
      }
    }
    else {
      $course_results["non-members"]++;
    }
  }

  return($course_results);
}

function get_all_course_result_links($event, $key, $path_to_top = "..") {
  $courses_path = get_courses_path($event, $key);
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  $links_string = "<p>Show results for ";
  foreach ($course_list as $one_course) {
    if (!file_exists("{$courses_path}/{$one_course}/removed")) {
      $links_string .= "<a href=\"../OMeet/view_results.php?event={$event}&key={$key}&course=$one_course\">" . ltrim($one_course, "0..9-") . "</a> \n";
    }
  }
  $links_string .= "<a href=\"../OMeet/view_results.php?event={$event}&key={$key}\">All</a> \n";
  if (event_is_using_nre_classes($event, $key)) {
    $links_string .= "<a href=\"../OMeet/view_results.php?event={$event}&key={$key}&per_class=1\">Per-class results</a> \n";
  }

  return($links_string);
}

function get_all_class_result_links($event, $key, $classification_info) {
  if (!event_is_using_nre_classes($event, $key)) {
    return("");
  }

  $courses_path = get_courses_path($event, $key);
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  // Set a mapping from the readable course name (used in the classes table) to the actual course name, e.g. Green -> 04-Green
  $course_hash = array();
  array_map(function ($elt) use (&$course_hash) { $course_hash[ltrim($elt, "0..9-")] = $elt; }, $course_list);
  $readable_course_list = array_keys($course_hash);
  $valid_classes_for_event = array_filter($classification_info, function ($elt) use ($readable_course_list) { return(in_array($elt[0], $readable_course_list)); });

  $processed_classes = array();
  $links_string = "<p>Show results for ";
  foreach ($valid_classes_for_event as $this_class) {
    if (!isset($processed_classes[$this_class[5]])) {
      $course_for_class = $course_hash[$this_class[0]];
      $links_string .= "<a href=\"../OMeet/view_results.php?event={$event}&key={$key}&course={$course_for_class}&class={$this_class[5]}&per_class=1\">" .
	      "{$this_class[0]}:{$this_class[5]}</a> \n";
      $processed_classes[$this_class[5]] = 1;
    }
  }
  $links_string .= "<a href=\"../OMeet/view_results.php?event={$event}&key={$key}&per_class=1\">All Classes</a> \n";
  $links_string .= "<a href=\"../OMeet/view_results.php?event={$event}&key={$key}\">Results by course</a> \n";

  return($links_string);
}

function get_email_course_result_links($event, $key, $path_to_top = "..") {
  if (isset($_SERVER["HTTPS"])) {
    $proto = "https://";
  }
  else {
    $proto = "http://";
  }
  $base_path_for_links = $proto . $_SERVER["SERVER_NAME"] . dirname(dirname($_SERVER["REQUEST_URI"]));

  $courses_path = get_courses_path($event, $key);
  $course_list = scandir($courses_path);
  $course_list = array_diff($course_list, array(".", ".."));

  $links_string = "<p>Show results for ";
  foreach ($course_list as $one_course) {
    if (!file_exists("{$courses_path}/{$one_course}/removed")) {
      $links_string .= "<a href=\"{$base_path_for_links}/OMeet/view_results.php?event={$event}&key={$key}&course=$one_course\">" . ltrim($one_course, "0..9-") . "</a> \n";
    }
  }
  $links_string .= "<a href=\"{$base_path_for_links}/OMeet/view_results.php?event={$event}&key={$key}\">All</a> \n";
  if (event_is_using_nre_classes($event, $key)) {
    $links_string .= "<a href=\"{$base_path_for_links}/OMeet/view_results.php?event={$event}&key={$key}&per_class=1\">Per-class results</a> \n";
  }


  return($links_string);
}

?>
