#! /usr/bin/perl

use strict;

sub setup_member_files {

  my($base_path) = @_;

  open(MEMBER_FILE, ">${base_path}/members.csv");
  my($file_contents);

  $file_contents = qq(591;Aaron;Aaker;
171;Peter;Amram;
1701;Jose Luis;Bernal;
41;Larry;Berrill;
1681;Katia;Bertoldi;
101;Ian;Finlayson;556;
102;Isla;Finlayson;558;
103;Issi;Finlayson;559;
109;Victoria;Campbell;1024;tori\@nowhere.com;
315;Student;Body;314159;;5086148225;;;Individual;StAndrewsScotland;
150;Alumno;Nadie;225256;;5086148225;;;Individual;USD;
180;Etudiant;Personne;141421;;5086148225;;;Individual;;DVOA
314;Mark;OConnell;2108369;mark\@mkoconnell.com;5086148225;1967;m;Family;Dartmouth;NEOC
31;Karen;Yeowell;3959473;karen\@mkoconnell.com;5083959473;1968;f;Family;Dartmouth;NEOC);

  print MEMBER_FILE $file_contents;
  close(MEMBER_FILE);
  
  
  open(NICKNAME_FILE, ">${base_path}/nicknames.csv");

  $file_contents = qq(Victoria;Tori;
Lawrence;Larry;
Rebecca;Becky;
Judith;Judy;
Timothy;Tim;);

  print NICKNAME_FILE $file_contents;
  close(NICKNAME_FILE);
}

sub remove_member_files {
  my($base_path) = @_;

  unlink("${base_path}/members.csv");
  unlink("${base_path}/nicknames.csv");
}

1;
