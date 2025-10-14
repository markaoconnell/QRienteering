<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function send_email($email_addr, $subject, $body_string, $email_properties) {
  if (isset($email_properties["use_php_mail_function"])) {
    $headers = array();
    $headers[] = "From: " . $email_properties["from"];
    $headers[] = "Reply-To: ". $email_properties["reply-to"];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=iso-8859-1";

    $header_string = implode("\r\n", $headers);

    // echo "<p>Emailing to {$email_addr}\n";
    if (isset($email_properties["extra_params"]) && ($email_properties["extra_params"] != "")) {
      $email_send_result = mail($email_addr, $subject, $body_string, $header_string, $email_properties["extra_params"]);
    }
    else {
      $email_send_result = mail($email_addr, $subject, $body_string, $header_string);
	}

	if (!$email_send_result) {
      echo "<p>Mail: Failed when sending email to {$email_addr}\n, {$e->errorMessage()}\n";
	}
  }
  else if (isset($email_properties["use_phpmailer"])) {
    try {
      $email_sender = new PHPMailer(true);
      $email_sender->isSMTP();
      $email_sender->Host = $email_properties["email_host"];
      $email_sender->SMTPAuth = true;
      $email_sender->Username = $email_properties["email_sender_username"];
      $email_sender->Password = $email_properties["email_sender_password"];
      $email_sender->SMTPSecure = $email_properties["email_secure_protocol"];
      $email_sender->Port = $email_properties["email_port"];
      $email_sender->addReplyTo($email_properties["reply-to"]);
      if (isset($email_properties["from_name"])) {
        $email_sender->setFrom($email_properties["from"], $email_properties["from_name"]);
      }
      else {
        $email_sender->setFrom($email_properties["from"]);
      }
      $email_sender->addAddress($email_addr);
      $email_sender->isHTML(true);
      $email_sender->Subject = $subject;
      $email_sender->Body = $body_string;
      $email_sender->send();
    } catch (Exception $e) {
      echo "<p>Mail: Failed when sending email to {$email_addr}\n, {$e->errorMessage()}\n";
    }
  }
}
?>
