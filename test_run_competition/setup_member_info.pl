#! /usr/bin/perl

use strict;

open(MEMBER_FILE, ">./members.csv");
my($file_contents);
$file_contents = qq(591;Aaron;Aaker;
171;Peter;Amram;
1701;Jose Luis;Bernal;
41;Larry;Berrill;
1681;Katia;Bertoldi;
101;Ian;Finlayson;556;
102;Isla;Finlayson;558;
103;Issi;Finlayson;559;
109;Victoria;Campbell;1024;
314;Mark;OConnell;2108369;
31;Karen;Yeowell;3959473);
print MEMBER_FILE $file_contents;
close(MEMBER_FILE);


open(NICKNAME_FILE, ">./nicknames.csv");
$file_contents = qq(Victoria;Tori;
Lawrence;Larry;
Rebecca;Becky;
Judith;Judy;
Timothy;Tim;);
print NICKNAME_FILE $file_contents;
close(NICKNAME_FILE);

1;
