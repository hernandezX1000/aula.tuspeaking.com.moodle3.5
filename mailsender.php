<?php
if(isset($_POST['email'])) {
	$email_to = $_POST['email'];
	$email_subject = $_POST['subject'];
	$email_content = $_POST['content'];
	echo sendEmail($email_to, $email_subject, $email_content);
}

function sendEmail($email_to, $email_subject, $email_content, $email_bbc = null, $attachedFile = null){
    $headers = "From: no-reply@tuspeaking.com";
    if ($email_bbc != null){
        $headers .= "\nBcc: {$email_bbc}";
    }

// This attaches the file
    $semi_rand     = md5(time());
    $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
    $headers      .= "\nMIME-Version: 1.0\n";

    if ($attachedFile == null) {
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        $message = $email_content;
    } else {
        $fileType = mime_content_type($attachedFile);
        $fileName = pathinfo($attachedFile)['basename'];
        $file = fopen($attachedFile, 'rb');
        $data = fread($file, filesize($attachedFile));
        fclose($file);
        $data = chunk_split(base64_encode($data));
        $message = "This is a multi-part message in MIME format.\n\n" .
            "--{$mime_boundary}\n" .
            "Content-Type: text/html; charset=iso-8859-1\n" .
            "Content-Transfer-Encoding: 7bit\n\n" .
            $email_content  . "\n\n" .
            "--{$mime_boundary}\n" .
            "Content-Type: {$fileType};\n" .
            " name=\"{$fileName}\"\n" .
            "Content-Disposition: attachment;\n" .
            " filename=\"{$fileName}\"\n" .
            "Content-Transfer-Encoding: base64\n\n" .
            $data . "\n\n" .
            "--{$mime_boundary}--\n";

        $headers .= "Content-Type: multipart/mixed;\n";
    }
    $headers .= " boundary=\"{$mime_boundary}\"";

// Send the email
    if(mail($email_to, $email_subject, $message, $headers)) {
        // Set a 200 (okay) response code.
        http_response_code(200);
        $msg = "The email was sent.";
    } else {
        // Set a 500 (internal server error) response code.
        http_response_code(500);
        $msg = "There was an error sending the mail.";
    }
    return $msg;
}