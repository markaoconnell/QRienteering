<?php



function get_node($node_list, $node_name) {
  foreach ($node_list as $node) {
    if ($node->nodeName == $node_name) {
      return ($node);
    }
  }

  return NULL;
}

function get_node_value($node_list, $node_name) {
  $found_node = get_node($node_list, $node_name);
  if ($found_node != NULL) {
    return ($found_node->nodeValue);
  }
  else {
    return "";
  }
}

$box_C_nondirectional_items = array("0.3" => "Upper",
	                            "0.4" => "Lower",
                                    "0.5" => "Middle");
                                  

$box_D_items = array("1.1" => "Terrace",
	             "1.2" => "Spur",
		     "1.3" => "Re-entrant",
		     "1.4" => "Earth bank",
		     "1.5" => "Quarry",
		     "1.6" => "Earth wall",
		     "1.7" => "Gully",
		     "1.8" => "Dry ditch",
		     "1.9" => "Hill",
		     "1.10" => "Knoll",
		     "1.11" => "Saddle",
		     "1.12" => "Depression",
		     "1.13" => "Small depression",
		     "1.14" => "Pit",
		     "1.15" => "Broken ground",
		     "1.16" => "Ant hill",
		     "2.1" => "Cliff",
		     "2.2" => "Rock pillar",
		     "2.3" => "Cave",
		     "2.4" => "Boulder",
		     "2.5" => "Boulder field",
		     "2.6" => "Boulder cluster",
		     "2.7" => "Stony ground",
		     "2.8" => "Bare rock",
		     "2.9" => "Narrow passage",
		     "3.1" => "Lake",
		     "3.2" => "Pond",
		     "3.3" => "Waterhole",
		     "3.4" => "Stream",
		     "3.5" => "Water channel",
		     "3.6" => "Wet ditch",
		     "3.7" => "Marsh",
		     "3.8" => "Firm ground in marsh",
		     "3.9" => "Well",
		     "3.10" => "Spring",
		     "3.11" => "Water tank",
		     "4.1" => "Open land",
		     "4.2" => "Semi-open land",
		     "4.3" => "Forest corner",
		     "4.4" => "Clearing",
		     "4.5" => "Thicket",
		     "4.6" => "Linear thicket",
		     "4.7" => "Vegetation boundary",
		     "4.8" => "Copse",
		     "4.9" => "Tree",
		     "4.10" => "Tree stump",
		     "5.1" => "Road",
		     "5.2" => "Path",
		     "5.3" => "Ride",
		     "5.4" => "Bridge",
		     "5.5" => "Power line",
		     "5.6" => "Pylon",
		     "5.7" => "Tunnel",
		     "5.8" => "Stone wall",
		     "5.9" => "Fence",
		     "5.10" => "Crossing point",
		     "5.11" => "Building",
		     "5.12" => "Paved area",
		     "5.13" => "Ruin",
		     "5.14" => "Pipeline",
		     "5.15" => "Tower",
		     "5.16" => "Shooting platform",
		     "5.17" => "Cairn",
		     "5.18" => "Fodder rack",
		     "5.19" => "Charcoal burn",
		     "5.20" => "Monument",
		     "5.23" => "Building passageway",
		     "5.24" => "Stairway",
		     "6.1" => "Special item",
		     "6.2" => "Special item");

$box_E_items = array("8.1" => "low",
	             "8.2" => "shallow",
		     "8.3" => "deep",
		     "8.4" => "overgrown",
		     "8.5" => "open",
		     "8.6" => "rocky",
		     "8.7" => "marshy",
		     "8.8" => "sandy",
		     "8.9" => "needle leaved",
		     "8.10" => "broad leaved",
		     "8.11" => "ruined");

// These items require box E to use the Box D descriptions
$box_F_dual_feature_items = array("10.1" => "crossing",
	                          "10.2" => "junction");

$box_F_single_feature_items = array("11.7" => "bend");

$box_G_directional_items = array("11.1" => "side",
	                         "11.2" => "edge",
				 "11.3" => "part",
				 "11.4" => "inside corner",
				 "11.5" => "outside corner",
				 "11.6" => "tip",
				 "11.8" => "end",
				 "11.14" => "foot");

$box_G_nondirectional_items = array("11.9" => "upper part",
				    "11.10" => "lower part",
				    "11.11" => "top",
				    "11.12" => "beneath",
				    "11.13" => "foot");

$box_G_dual_feature_items = array("11.15" => "Between");



function create_control_description($description_boxes) {
  global $box_C_nondirectional_items;
  global $box_D_items;
  global $box_E_items;
  global $box_F_dual_feature_items, $box_F_single_feature_items;
  global $box_G_directional_items, $box_G_nondirectional_items, $box_G_dual_feature_items;

  $description_items = array();
  foreach ($description_boxes as $description_line) {
    $box = get_node_value($description_line->attributes, "box");
    $iof_ref = get_node_value($description_line->attributes, "iof-2004-ref");
    $value = $description_line->nodeValue;
    $description_items[$box] = array($iof_ref, $value);
  }

  // Between two features
  $description = "";
  if (isset($description_items["G"]) && isset($box_G_dual_feature_items[$description_items["G"][0]])) {
    $description .= $box_G_dual_feature_items[$description_items["G"][0]];
    if (isset($description_items["D"])) {
      $description .= " " . strtolower($box_D_items[$description_items["D"][0]]);
    }
    if (isset($description_items["E"])) {
      $description .= " and " . strtolower($box_D_items[$description_items["E"][0]]);
    }
  }
  elseif (isset($description_items["D"])) {
    if (isset($description_items["C"])) {
      $found_pieces = array(); 
      if (preg_match("/(0.[1-5])([NSEW]*)/", $description_items["C"][0], $found_pieces)) {
        if ($found_pieces[2] == "") {
	  // non-directional description
	  $description .= $box_C_nondirectional_items[$found_pieces[1]] . " " . strtolower($box_D_items[$description_items["D"][0]]);
	}
	else {
	  // Directional description
	  $description .= $found_pieces[2] . " " . strtolower($box_D_items[$description_items["D"][0]]);
	}
      }
      else {
	// This shouldn't happen, but at least put something in this case
        $description .= $box_D_items[$description_items["D"][0]];
      }
    }
    else {
      $description .= $box_D_items[$description_items["D"][0]];
    }
  }

  // If true, then box E should use the box D descriptions
  if (isset($description_items["F"]) && isset($box_F_dual_feature_items[$description_items["F"][0]])) {
    if (isset($description_items["E"])) {
      $description .= " and " . strtolower($box_D_items[$description_items["E"][0]]);
    }
    $description .= " {$box_F_dual_feature_items[$description_items["F"][0]]}";
  }
  elseif (isset($description_items["E"])) {
    $description .= " {$box_E_items[$description_items["E"][0]]}";
  }

  // In this case, box F describes the feature in box D - height etc
  // Except for a bend, this is a literal description of the item
  if (isset($description_items["F"]) && !isset($box_F_dual_feature_items[$description_items["F"][0]])) {
    if (isset($box_F_single_feature_items[$description_items["F"][0]])) {
      $description .= " {$box_F_single_feature_items[$description_items["F"][0]]}";
    }
    else {
      $description .= " {$description_items["F"][1]}";
    }
  }

  // Handle the other cases of column G - location on a single feature
  if (isset($description_items["G"]) && !isset($box_G_dual_feature_items[$description_items["G"][0]])) {
    $found_pieces = array(); 
    if (preg_match("/([0-9]+.[0-9]+)([NSEW]*)/", $description_items["G"][0], $found_pieces)) {
      if ($found_pieces[2] == "") { // No direction item found
        $description .= " {$box_G_nondirectional_items[$found_pieces[1]]}";
      }
      else { // iof descriptor and direction found
        $description .= " {$found_pieces[2]} {$box_G_directional_items[$found_pieces[1]]}";
      }
    }
  }

  return ($description);
}

function get_event_description($ppen_file, $include_get_em_all) {

  $event_description_string = "";

  $doc = new DOMDocument();
  $doc->load($ppen_file);

$verbose = false;

if ($doc->firstChild->localName == "course-scribe-event") {
  $child_list = $doc->firstChild->childNodes;
}
else {
  echo "Why didn't I find the course-scribe-event tag??\n";
  $child_list = $doc->childNodes;
}

$controls = array();
$courses = array();
$course_controls = array();
$control_descriptions = array();

$IS_NORMAL = 0;
$CONTROL_CODE = 1;
$CONTROL_TYPE = 2;

$COURSE_NAME = 0;
$COURSE_FIRST_CONTROL = 1;
$COURSE_TYPE = 2;

$COURSE_CONTROL_ID = 0;
$COURSE_CONTROL_NEXT = 1;
$COURSE_CONTROL_POINTS = 2;

foreach ($child_list as $top_level_child) {
  $name = $top_level_child->nodeName;
  if ($verbose) {
    echo "Found {$top_level_child->nodeName} with value {$top_level_child->nodeValue}\n";
    echo "Found {$top_level_child->nodeName}\n";
  }
  $attrs = $top_level_child->attributes;
  
  if ($attrs != NULL) {
    //foreach ($attrs as $course_attrs) {
    //  echo "\t{$course_attrs->nodeName} maps to {$course_attrs->nodeValue}\n";
    //}
  
    if ($name == "event") {
      $course_children = $top_level_child->childNodes;
      $event_title = get_node_value($course_children, "title");
    }

    if ($name == "course") {
      $course_children = $top_level_child->childNodes;
      $id = get_node_value($attrs, "id");     
      $kind = get_node_value($attrs, "kind");     
      $course_name = get_node_value($course_children, "name");
      $first_control_node = get_node($course_children, "first");
      $fc_attrs = $first_control_node->attributes;
      $first_control = get_node_value($fc_attrs, "course-control");
//      foreach ($course_children as $course_info) {
//        echo "\t\t{$course_info->nodeName}, {$course_info->nodeValue}\n";
//      }
      if ($verbose) {
        echo "Course {$id}, named {$course_name} starts with control id {$first_control}\n";
      }
      $courses[$id] = array($course_name, $first_control, $kind);
    }
  
    if ($name == "control") {
      $id = get_node_value($attrs, "id");
      $kind = get_node_value($attrs, "kind");
      $control_children = $top_level_child->childNodes;
      $code = get_node_value($control_children, "code");
      $control_description_box_list = array();
      foreach ($control_children as $child_info) {
        //echo "\t\t{$child_info->nodeName}, {$child_info->nodeValue}\n";
	if ($child_info->nodeName == "description") {
	  $control_description_box_list[] = $child_info;
	}
      }

      if ($verbose) {
	echo "Control id {$id} has code {$code} and kind {$kind}\n";
      }

      if (($kind == "normal") && count($control_description_box_list) > 0) {
        $control_descriptions[$code] = create_control_description($control_description_box_list);
        if ($verbose) {
          echo "Control id {$id} (code {$code}) has description: {$control_descriptions[$code]}\n";
	}
      }

      if ($kind == "normal") {
        $controls[$id] = array(true, $code, $kind);
      }
      else {
        $controls[$id] = array(false, $code, $kind);
      }
    }
  
    if ($name == "course-control") {
      $course_children = $top_level_child->childNodes;
      $id = get_node_value($attrs, "id");
      $points = get_node_value($attrs, "points");
      $control_id = get_node_value($attrs, "control");
      $next_node = get_node($course_children, "next");
      if ($next_node != NULL) {
        $next_course_control_id = get_node_value($next_node->attributes, "course-control");
      }
      else {
        $next_course_control_id = "Finish";
      }

//      foreach ($course_children as $course_info) {
//        echo "\t\t{$course_info->nodeName}, {$course_info->nodeValue}\n";
//      }

      if ($verbose) {
        echo "Course control id {$id} is control id {$control_id} and next goes to course-control {$next_course_control_id}\n";
      }
      $course_controls[$id] = array($control_id, $next_course_control_id, $points);
    }
  }
}

$course_strings = array();
foreach ($courses as $one_course) {
  if ($verbose) {
    echo "{$one_course[$COURSE_NAME]} starts at {$controls[$course_controls[$one_course[$COURSE_FIRST_CONTROL]][$COURSE_CONTROL_ID]][$CONTROL_CODE]}\n";
  }
  $course_array = array();
  $score_course = false;
  if ($one_course[$COURSE_TYPE] == "normal") {
    $course_array[] = "l:{$one_course[$COURSE_NAME]}";
  }
  elseif ($one_course[$COURSE_TYPE] == "score") {
    $score_course = true;
    $course_array[] = "s:{$one_course[$COURSE_NAME]}:0:0";
  }
  else {
    echo "Unknown course type {$one_course[$COURSE_TYPE]}, assuming regular (linear) course.\n";
    $course_array[] = "l:{$one_course[$COURSE_NAME]}";
  }

  $next_course_control_id = $one_course[$COURSE_FIRST_CONTROL];
  if ($verbose) {
    echo "\tCourse {$one_course[$COURSE_NAME]} starts with course_control id {$next_course_control_id}\n";
    echo "\t\tCourse " . (($controls[$course_controls[$next_course_control_id][$COURSE_CONTROL_ID]][$CONTROL_TYPE] == "start") ? "has" : "lacks") .
	  " the start control\n";
  }

  while ($next_course_control_id != "Finish") {
    $next_control_entry = $controls[$course_controls[$next_course_control_id][$COURSE_CONTROL_ID]];
    if ($next_control_entry[$IS_NORMAL]) {  // Is this a printable (normal) control?
      if ($score_course) {
	// If a point value has not been assigned to a ScoreO course, give it a value of 1 (rather than erroring cryptically later)
	if ($course_controls[$next_course_control_id][$COURSE_CONTROL_POINTS] == "") {
	  $course_controls[$next_course_control_id][$COURSE_CONTROL_POINTS] = "1";
	}
        $course_array[] = "{$next_control_entry[$CONTROL_CODE]}:{$course_controls[$next_course_control_id][$COURSE_CONTROL_POINTS]}";
      }
      else {
        $course_array[] = $next_control_entry[$CONTROL_CODE];
      }
    }
    if ($verbose) {
      echo "\tNext Control is {$controls[$course_controls[$next_course_control_id][$COURSE_CONTROL_ID]][$CONTROL_CODE]}" . 
              ", {$controls[$course_controls[$next_course_control_id][$COURSE_CONTROL_ID]][$CONTROL_TYPE]}\n";
    }
    $next_course_control_id = $course_controls[$next_course_control_id][$COURSE_CONTROL_NEXT];
  }
  $course_strings[strtolower($one_course[$COURSE_NAME])] = implode(",", $course_array);
  if ($verbose) {
    echo "\n\n" . implode(",", $course_array) . "\n\n";
  }
}

// echo implode("\n", $course_strings);

//echo "{$event_title}\n";
$ordered_courses = array("white", "yellow", "orange", "tan", "brown", "green", "red", "blue");
foreach ($ordered_courses as $course_to_print) {
  if (isset($course_strings[$course_to_print])) {
//    echo "{$course_strings[$course_to_print]}\n";
    $event_description_string .= "{$course_strings[$course_to_print]}\n";
    unset($course_strings[$course_to_print]);
  }
}
if (count($course_strings) > 0) {
//  echo implode("\n", $course_strings);
  $event_description_string .= "\n" . implode("\n", $course_strings) . "\n";
}
//echo "\ns:GetEmAll:0:0," . implode(",", array_map(function ($elt) { return ("{$elt[1]}:1"); }, array_values(array_filter($controls, function ($elt) { return ($elt[0]); }))));
if ($include_get_em_all) {
  $event_description_string .= "\ns:GetEmAll:0:0," . implode(",", array_map(function ($elt) { return ("{$elt[1]}:1"); }, array_values(array_filter($controls, function ($elt) { return ($elt[0]); }))));
  $event_description_string .= "\n";
}

  $event_description_string .= "\n" . implode("\n", array_map(function ($key) use ($control_descriptions) { return "d:{$key}:{$control_descriptions[$key]}"; }, array_keys($control_descriptions))) . "\n";

  return (array("title" => $event_title, "description" => $event_description_string));
}
?>
