<?php

// Load Composer Autoloader

require '/home/zacharykai/.config/composer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuration

$to_email = "hi@zacharykai.net";

// Initialize Response

$response = [
    'success' => false,
    'message' => ''
];

// Check Form Was Submitted Via POST

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Determine Form Type Based On The Hidden Field

    $form_type = $_POST['form_type'] ?? '';
    
    $valid_form = true;
    $email_subject = "";
    $email_body = "";
    $sender_email = "";
    $sender_name = "";
    $expected_captcha = "";
    
    // Process Based On Form Type

    switch ($form_type) {

        // HTML Day Guestbook

        case 'htmldayguestbook':
            $first_name = trim($_POST['first-name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $comment = trim($_POST['comment'] ?? '');
            $captcha = trim($_POST['captcha'] ?? '');
            $expected_captcha = 'HTML Day: Online';
            
            if (empty($first_name) || empty($email) || empty($comment)) {
                $response['message'] = "Please fill in all required fields.";
                $valid_form = false;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = "Please provide a valid email address.";
                $valid_form = false;
            } else {
                $email_subject = "HTML Day Online: Guestbook Entry";
                $email_body .= "Name: $first_name\n";
                $email_body .= "Email: $email\n";
                $email_body .= "URL: $website\n";
                $email_body .= "Comment: $comment\n";
                $sender_email = $email;
                $sender_name = $first_name;
            }
            break;
    
        // HTML Day RSVP

        case 'htmlday':
            $name = trim($_POST['first-name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $timezone = trim($_POST['timezone'] ?? '');
            $captcha = trim($_POST['captcha'] ?? '');
            $expected_captcha = 'HTML Day: Online';
            
            if (empty($name) || empty($email) || empty($timezone)) {
                $response['message'] = "Please fill in all required fields.";
                $valid_form = false;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = "Please provide a valid email address.";
                $valid_form = false;
            } else {
                $email_subject = "HTML Day: Online Registration";
                $email_body .= "Name: $name\n";
                $email_body .= "Email: $email\n";
                $email_body .= "Time Zone: $timezone\n";
                $sender_email = $email;
                $sender_name = $name;
            }
            break;
        
        default:
            $response['message'] = "Invalid form type.";
            $valid_form = false;
    }
    
    // Check Captcha

    if ($valid_form && !empty($expected_captcha)) {
        $captcha = trim($_POST['captcha'] ?? '');
        if (strcasecmp($captcha, $expected_captcha) !== 0) {
            $response['message'] = "Please enter the page's title.";
            $valid_form = false;
        }
    }
    
    // Send Email If Form Is Valid

    if ($valid_form) {

        // Retrieve SMTP Credentials

        $smtpUsername = getenv('FASTMAIL_SMTP_USERNAME');
        $smtpPassword = getenv('FASTMAIL_SMTP_PASSWORD');
        
        // Create New PHPMailer Instance

        $mail = new PHPMailer(true);
        try {

            // Server settings

            $mail->isSMTP();
            $mail->Host       = 'smtp.fastmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUsername;
            $mail->Password   = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            
            // Sender & Recipient settings

            $mail->setFrom('site@zacharykai.net', 'LS Site');
            if (!empty($sender_email) && !empty($sender_name)) {
                $mail->addReplyTo($sender_email, $sender_name);
            }
            $mail->addAddress($to_email, 'Zachary Kai');
            
            $mail->isHTML(false);
            $mail->Subject = $email_subject;
            $mail->Body    = $email_body;
            
            $mail->send();
            
            // Redirect To Success Page If Email Sent

            header('Location: /successful');
            exit();

        } catch (Exception $e) {
            $response['message'] = "There was an error sending your submission. Mailer Error: " . $mail->ErrorInfo;
        }
    }

} else {
    $response['message'] = "Invalid request method.";
}

// Output Response Message If Not Redirected

echo $response['message'];
?>