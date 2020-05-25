<?php
require '../common_routines.php';

ck_testing();

echo get_web_page_header(true, false, true);


echo "<h1>NEOC club member registration:</h1>\n";
echo "<form action=\"./name_lookup.php\">\n";
echo "<p>Lookup by member name:\n";
echo "<p>First name \n";
echo "<input type=\"text\" name=\"competitor_first_name\"><br>\n";
echo "<p>Last name \n";
echo "<input type=\"text\" name=\"competitor_last_name\"><br>\n";
echo "<input type=\"submit\" value=\"Member name lookup\">\n";
echo "</form>\n";

echo "<form action=\"./stick_lookup.php\">\n";
echo "<p>Lookup by Si Stick:\n";
echo "<input type=\"text\" name=\"si_stick\"><br>\n";
echo "<input type=\"submit\" value=\"SI stick lookup\">\n";
echo "</form>\n";


echo "<br><br><br><h1>Non-NEOC club member registration:</h1>\n";
echo "<form action=\"./non_member.php\">\n";
echo "<br><p>What is your name?<br>\n";
echo "<p>First name \n";
echo "<input type=\"text\" name=\"competitor_first_name\"><br>\n";
echo "<p>Last name \n";
echo "<input type=\"text\" name=\"competitor_last_name\"><br>\n";
echo "<br><p>What is your orienteering club affiliation (if any)?<br>\n";
echo "<input type=\"text\" name=\"club_name\"><br>\n";
echo "<br><p>What is your SI stick number (if any)?<br>\n";
echo "<input type=\"text\" name=\"si_stick\"><br>\n";
echo "<br><p>What is your email (to send results link)?<br>\n";
echo "<input type=\"text\" name=\"email\"><br>\n";
echo "<br><p>What is your cell phone number (in case we need to contact you)?<br>\n";
echo "<input type=\"text\" name=\"cell_number\"><br>\n";
echo "<br><p>What car make/model did you come in (so we can see if you've left)?<br>\n";
echo "<input type=\"text\" name=\"car_info\"><br>\n";
echo "<input type=\"submit\" value=\"Submit\">\n";
echo "</form>";

echo get_web_page_footer();
?>
