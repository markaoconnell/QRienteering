<?php
// Format is control,points
$control_list[] = array(201,1);
$control_list[] = array(202,2);
$control_list[] = array(203,3);
$control_list[] = array(204,4);
$control_list[] = array(205,5);

$controls_points_hash = array_combine(array_map(function ($element) { return $element[0]; }, $control_list),
                                      array_map(function ($element) { return $element[1]; }, $control_list));

echo "Controls points hash is: \n";
print_r($controls_points_hash);

// Format is time,control
$controls_done = array("55,201", "234,202", "145,203", "217,204", "169,205");

  // Just pluck off the controls found (ignore the timestamp for now
  $controls_found = array_map(function ($item) { return (explode(",", $item)[1]); }, $controls_done);

echo "Controls found is now:\n";
print_r($controls_found);

  // For each control, look up its point value in the associative array and sum the total points
  $total_score = array_reduce($controls_found, function ($carry, $element) use ($controls_points_hash) { echo "carry is $carry, element is $element, points is {$controls_points_hash[$element]}.\n"; return($carry + $controls_points_hash[$element]); }, 0);

echo "Got a total score of {$total_score}.\n";
?>
