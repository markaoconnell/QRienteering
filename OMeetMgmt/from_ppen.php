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

function get_event_description($ppen_file, $include_get_em_all) {

  $event_descrption_string = "";

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

foreach ($child_list as $top_level_child) {
  $name = $top_level_child->nodeName;
  //echo "Found {$top_level_child->nodeName} with value {$top_level_child->nodeValue}\n";
  //echo "Found {$top_level_child->nodeName}\n";
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
      $course_children = $top_level_child->childNodes;
      $code = get_node_value($course_children, "code");
//      foreach ($course_children as $course_info) {
//        echo "\t\t{$course_info->nodeName}, {$course_info->nodeValue}\n";
//      }

      if ($verbose) {
        echo "Control id {$id} has code {$code} and kind {$kind}\n";
      }
      if ($kind == "normal") {
        $controls[$id] = array(true, $code);
      }
      else {
        $controls[$id] = array(false, $kind);
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
    echo "{$one_course[0]} starts at {$controls[$course_controls[$one_course[1]][0]][1]}\n";
  }
  $course_array = array();
  $score_course = false;
  if ($one_course[2] == "normal") {
    $course_array[] = "l:{$one_course[0]}";
  }
  elseif ($one_course[2] == "score") {
    $score_course = true;
    $course_array[] = "s:{$one_course[0]}:0:0";
  }
  else {
    echo "Unknown course type {$one_course[2]}, assuming regular (linear) course.\n";
    $course_array[] = "l:{$one_course[0]}";
  }
  $next_control = $course_controls[$one_course[1]][1];
  while ($next_control != "Finish") {
    $next_control_code = $controls[$course_controls[$next_control][0]];
    if ($next_control_code[0]) {  // Is this a printable (normal) control?
      if ($score_course) {
        $course_array[] = "{$next_control_code[1]}:{$course_controls[$next_control][2]}";
      }
      else {
        $course_array[] = $next_control_code[1];
      }
    }
    if ($verbose) {
      echo "\tNext Control is {$controls[$course_controls[$next_control][0]]}\n";
    }
    $next_control = $course_controls[$next_control][1];
  }
  $course_strings[strtolower($one_course[0])] = implode(",", $course_array);
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

  return (array("title" => $event_title, "description" => $event_description_string));
}
?>
