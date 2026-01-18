<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');


session_start();

date_default_timezone_set('Africa/Nairobi');
// date_default_timezone_set('Africa/Accra');


require dirname(__DIR__).'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dbhost = $_ENV['DB_HOST'];
$dbuser = $_ENV['DB_USER'];
$dbpass = $_ENV['DB_PASS'];
$dbname = $_ENV['DB_NAME'];

$admin = [
   "name" => $_ENV["adminname"],
   "email" => $_ENV["adminemail"],
   "company" => $_ENV["company"],
   "domain" => $_ENV["domain"],
   "backend" => $_ENV["backend"],
   "stktoken" => ['9514103023e8101b4ab3d73e','9514103023e8101b4ab3d73e']
];

$backend = $admin['backend'];
$domain = $admin['domain'];
$company = $admin['company'];


$dev = [
   "name" => $_ENV['devname'],
   "email" => $_ENV['devemail'],
];


$conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$conn) {
   http_response_code(500);
   echo json_encode([]);
   exit;
}

