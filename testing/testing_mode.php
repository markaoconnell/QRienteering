<?php
// Check to see if there is an artificial input file and process it if necessary
function artificial_input_file_parse() {
  if (file_exists("./artificial_input")) {
//echo "Found file artificial_input\n";
    $artificial_input = file_get_contents("./artificial_input");
    $artificial_input_lines = explode("\n", $artificial_input);
    foreach ($artificial_input_lines as $this_line) {
//echo "Found line {$this_line}\n";
      $comment_start = strpos($this_line, "#");
      if ($comment_start !== false) {
        $this_line = substr($this_line, 0, $comment_start);
      }

//echo "Line is now {$this_line}\n";

      $this_line_elements = preg_split("/\s+/", $this_line, -1, PREG_SPLIT_NO_EMPTY);

      if (count($this_line_elements) == 3) {
        if ($this_line_elements[0] == "GET") {
//echo "Set GET {$this_line_elements[1]} to {$this_line_elements[2]}\n";
          $_GET[$this_line_elements[1]] = $this_line_elements[2];
        }
        else if ($this_line_elements[0] == "COOKIE") {
//echo "Set COOKIE {$this_line_elements[1]} to {$this_line_elements[2]}\n";
          $_COOKIE[$this_line_elements[1]] = $this_line_elements[2];
        }
      }
    }
  }
}
?>
