<?php
// Fichier d'authentification admin
require_once 'config/database.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifier si l'admin est connecté
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Connecter un administrateur
 */
function adminLogin($username, $password, $db) {
    try {
        $query = "SELECT * FROM admins WHERE username = :username LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            return true;
        }
        
        return false;
    } catch(PDOException $e) {
        error_log("Erreur connexion admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Déconnecter l'administrateur
 */
function adminLogout() {
    $_SESSION['admin_logged_in'] = false;
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    session_destroy();
}

/**
 * Créer un compte admin (pour l'installation)
 */
function createAdmin($username, $password, $email, $db) {
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO admins (username, password, email) VALUES (:username, :password, :email)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':email' => $email
        ]);
        return true;
    } catch(PDOException $e) {
        error_log("Erreur création admin: " . $e->getMessage());
        return false;
    }
}
?>

