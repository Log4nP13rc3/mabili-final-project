<?php
require '../vendor/autoload.php'; //configure vendor classes

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// loading env file vars
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// db config from env vars
$hostname = $_ENV['DB_HOSTNAME'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$database = $_ENV['DB_DATABASE'];

// connect to db using PDO
try {
    $pdo = new PDO("mysql:host=$hostname;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(stripcslashes(trim($data)));
}

// to validate inputs/ check for errors
function validateInput($data, $pdo) {
    $errors = [];

    // required fields
    if (empty($data['username'])) {
        $errors[] = "Username is required.";
    }

    // check if username is already in use
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->execute(['username' => $data['username']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username is already in use. Please choose a different username.";
    }

    if (empty($data['email'])) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }

    // check if email is already in use
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->execute(['email' => $data['email']]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Looks like you've already signed up with us. <a href='sign-in.html'>Sign in here</a>.";
    }

    if (empty($data['password'])) {
        $errors[] = "Password is required.";
    } elseif (strlen($data['password']) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if (!empty($data['password']) && empty($data['confirmPassword'])) {
        $errors[] = "Please confirm your password.";
    } elseif ($data['password'] !== $data['confirmPassword']) {
        $errors[] = "Passwords do not match.";
    }

    return $errors;
}

// function to send welcome email using PHPMailer
function sendWelcomeEmail($email, $username) {
    $phpmailer = new PHPMailer();
    $phpmailer->isSMTP();
    $phpmailer->Host = 'sandbox.smtp.mailtrap.io';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 2525;
    $phpmailer->Username = 'f75fa1c791f58d';
    $phpmailer->Password = '803641885c348f';

    $phpmailer->setFrom('noreply@yourdomain.com', 'Your Application Name');
    $phpmailer->addAddress($email, $username);

    $phpmailer->isHTML(true);
    $phpmailer->Subject = 'Welcome to Our Application!';
    $phpmailer->Body = '
        <html>
        <head>
            <style>
                body, html {
                    display: flex;
                    flex-direction: column;
                    min-height: 100vh;
                    margin: 0;
                    padding: 0;
                    height: 100%;
                    font-family: Arial, sans-serif;
                    background-color: #f8f8f8ff;
                }

                .email-wrapper {
                    width: 100%;
                    max-width: 600px;
                    margin: auto;
                    background-color: #f2f2f2ff;
                    border: 1px solid #7f8c8dff;
                    border-radius: 8px;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }

                header {
                    background-color: #333333ff;
                    color: #f8f8f8ff;
                    padding: 20px;
                    text-align: center;
                }

                .branding {
                    font-size: 24px;
                }

                main {
                    padding: 20px;
                    color: #333333ff;
                }

                h1, h2 {
                    color: #333333ff;
                }

                p {
                    line-height: 1.6;
                }

                footer {
                    background-color: #333333ff;
                    color: #f8f8f8ff;
                    padding: 10px;
                    text-align: center;
                }

                @media (max-width: 768px) {
                    .email-wrapper {
                        width: 100%;
                        padding: 20px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <header>
                    <div class="branding">Artist Investigators</div>
                </header>
                <main>
                    <h1>Welcome to Artist Investigators, ' . htmlspecialchars($username) . '!</h1>
                    <p>Explore posts created by fellow members, share your thoughts, and engage in discussions about artists and their works.</p>
                    <p>We are excited to have you join us and look forward to seeing your contributions to our artful community.</p>
                    <p>Warm regards,<br>The Artist Investigators Team</p>
                </main>
                <footer>
                    <p>&copy; 2024 Artist Investigators. All rights reserved.</p>
                </footer>
            </div>
        </body>
        </html>
    ';
        
        $phpmailer->AltBody = "Welcome to Artist Investigators, $username!\n\nThank you for signing up.";

        try {
            $phpmailer->send();
        } catch (Exception $e) {
            echo "Email could not be sent. Mailer Error: {$phpmailer->ErrorInfo}";
        }
    }

// form via post method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // sanitize input data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);
    $confirmPassword = sanitizeInput($_POST['confirmPassword']);

    // prep input data for validation
    $inputData = [
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'confirmPassword' => $confirmPassword,
    ];

    // validate input data
    $validationErrors = validateInput($inputData, $pdo);

    if (!empty($validationErrors)) {
        // return if validation errors
        echo '<style>
            .error-message {
                color: #FF7F50;
                font-weight: bold;
            }
        </style>';
        // return if validation errors
        echo "<ul class='error-message'>";
        foreach ($validationErrors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    } else {
        // hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // insert user into db
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)");
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash
        ]);

        // send welcome email
        sendWelcomeEmail($email, $username);

        echo "<p>Sign-Up successful! Check your email!</p>";
    }
}