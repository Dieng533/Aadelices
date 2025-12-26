<?php
require_once 'admin_auth.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Déconnecter l'administrateur
adminLogout();

// Rediriger vers la page d'accueil
header('Location: index.php');
exit;
?>

