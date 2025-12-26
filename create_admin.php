<?php
/**
 * Script pour créer ou réinitialiser le mot de passe admin
 * À exécuter une seule fois, puis à supprimer pour la sécurité
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Configuration
$username = 'admin';
$password = 'admin123'; // Changez ce mot de passe en production !
$email = 'admin@aadelice.sn';

try {
    // Vérifier d'abord si la table existe et a la bonne structure
    $query = "SHOW COLUMNS FROM admins LIKE 'password'";
    $stmt = $db->query($query);
    
    if ($stmt->rowCount() == 0) {
        // La colonne password n'existe pas, l'ajouter
        $db->exec("ALTER TABLE `admins` ADD COLUMN `password` VARCHAR(255) NOT NULL AFTER `username`");
        echo "<div style='padding: 20px; background: #fff3cd; color: #856404; border-radius: 5px; margin: 20px;'>";
        echo "<h3>⚠️ Colonne 'password' ajoutée à la table</h3>";
        echo "</div>";
    }
    
    // Hasher le mot de passe
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Vérifier si l'admin existe déjà
    $query = "SELECT * FROM admins WHERE username = :username LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':username' => $username]);
    $existingAdmin = $stmt->fetch();
    
    if ($existingAdmin) {
        // Mettre à jour le mot de passe
        $query = "UPDATE admins SET password = :password, email = :email WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':password' => $hashedPassword,
            ':email' => $email,
            ':username' => $username
        ]);
        echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 5px; margin: 20px;'>";
        echo "<h2>✅ Mot de passe admin mis à jour avec succès !</h2>";
        echo "<p><strong>Username:</strong> $username</p>";
        echo "<p><strong>Password:</strong> $password</p>";
        echo "<p><strong>Email:</strong> $email</p>";
        echo "</div>";
    } else {
        // Créer un nouvel admin
        $query = "INSERT INTO admins (username, password, email) VALUES (:username, :password, :email)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':email' => $email
        ]);
        echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 5px; margin: 20px;'>";
        echo "<h2>✅ Compte admin créé avec succès !</h2>";
        echo "<p><strong>Username:</strong> $username</p>";
        echo "<p><strong>Password:</strong> $password</p>";
        echo "<p><strong>Email:</strong> $email</p>";
        echo "</div>";
    }
    
    echo "<div style='padding: 20px; background: #fff3cd; color: #856404; border-radius: 5px; margin: 20px;'>";
    echo "<h3>⚠️ IMPORTANT - SÉCURITÉ</h3>";
    echo "<p>1. Notez ces identifiants dans un endroit sûr</p>";
    echo "<p>2. <strong>SUPPRIMEZ ce fichier (create_admin.php) après utilisation</strong></p>";
    echo "<p>3. Changez le mot de passe en production</p>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px; margin: 20px;'>";
    echo "<h2>❌ Erreur</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

