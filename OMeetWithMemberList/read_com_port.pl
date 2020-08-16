#!/usr/bin/perl

use strict;
#use Win32::SerialPort;

#my($PortObj);
#$PortObj = new Win32::SerialPort ("COM4")
#     || die "Can't open COM4: $^E\n";

#$PortObj->error_msg(1);  # prints hardware messages like "Framing Error"
#$PortObj->user_msg(1);   # prints function messages like "Waiting for CTS"

#$PortObj->databits(8);
#$PortObj->baudrate(9600);
#$PortObj->parity("none");
#$PortObj->write_settings || undef $PortObj;
 
#my($count_in, $string_in);
#($count_in, $string_in) = $PortObj->read(200);
#warn "read unsuccessful\n" unless ($count_in == $InBytes);

#print "Read $count_in bytes from COM4\n";
#print "Read $string_in from COM4\n";


#Opening com4
open(COM_PORT, "+<\\\\.\\COM4") || die "Cannot open COM4: $^E\n";
my($bytes_read);
my($in);
print "Attempting to read bytes from the com port.\n";
$bytes_read = sysread COM_PORT, $in, 255;
print "Looks like it read $bytes_read bytes from the com port.\n";
while ($bytes_read > 0) {
  my($hex_output) = unpack("H*", $in);
  print "$hex_output\n";
  print "Will read again from the com port.\n\n";
  $bytes_read = sysread COM_PORT, $in, 255;
  print "Looks like it read $bytes_read bytes from the com port.\n";
}

close(COM_PORT);