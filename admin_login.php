<?php
require_once 'config/database.php';
require_once 'admin_auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Connexion à la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    if (adminLogin($username, $password, $db)) {
        $response['success'] = true;
        $response['message'] = 'Connexion réussie';
    } else {
        $response['message'] = 'Identifiants incorrects';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>