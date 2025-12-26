<?php
/**
 * Script pour vérifier et corriger la structure de la base de données
 * Exécutez ce script si vous avez des erreurs de colonnes manquantes
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Réparation Base de Données</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h2 { color: #333; }
    </style>
</head>
<body>
<h1>🔧 Réparation de la Base de Données Aadelice</h1>";

try {
    // Vérifier si la table admins existe
    $query = "SHOW TABLES LIKE 'admins'";
    $stmt = $db->query($query);
    
    if ($stmt->rowCount() == 0) {
        echo "<div class='error'><h2>❌ La table 'admins' n'existe pas</h2>";
        echo "<p>Création de la table...</p></div>";
        
        // Créer la table admins
        $query = "CREATE TABLE IF NOT EXISTS `admins` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(50) NOT NULL UNIQUE,
          `password` VARCHAR(255) NOT NULL,
          `email` VARCHAR(100) DEFAULT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($query);
        echo "<div class='success'>✅ Table 'admins' créée avec succès !</div>";
    } else {
        echo "<div class='info'>✅ La table 'admins' existe</div>";
        
        // Vérifier si la colonne password existe
        $query = "SHOW COLUMNS FROM admins LIKE 'password'";
        $stmt = $db->query($query);
        
        if ($stmt->rowCount() == 0) {
            echo "<div class='error'><h2>❌ La colonne 'password' n'existe pas</h2>";
            echo "<p>Ajout de la colonne...</p></div>";
            
            // Ajouter la colonne password
            $query = "ALTER TABLE `admins` ADD COLUMN `password` VARCHAR(255) NOT NULL AFTER `username`";
            $db->exec($query);
            echo "<div class='success'>✅ Colonne 'password' ajoutée avec succès !</div>";
        } else {
            echo "<div class='info'>✅ La colonne 'password' existe</div>";
        }
        
        // Vérifier les autres colonnes nécessaires
        $requiredColumns = ['id', 'username', 'email', 'created_at'];
        foreach ($requiredColumns as $col) {
            $query = "SHOW COLUMNS FROM admins LIKE '$col'";
            $stmt = $db->query($query);
            if ($stmt->rowCount() == 0) {
                echo "<div class='error'>Colonne '$col' manquante, ajout...</div>";
                // Ajouter selon le type de colonne
                if ($col == 'id') {
                    $db->exec("ALTER TABLE `admins` ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
                } elseif ($col == 'username') {
                    $db->exec("ALTER TABLE `admins` ADD COLUMN `username` VARCHAR(50) NOT NULL UNIQUE");
                } elseif ($col == 'email') {
                    $db->exec("ALTER TABLE `admins` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL");
                } elseif ($col == 'created_at') {
                    $db->exec("ALTER TABLE `admins` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                }
                echo "<div class='success'>✅ Colonne '$col' ajoutée</div>";
            }
        }
    }
    
    // Vérifier si un admin existe
    $query = "SELECT COUNT(*) as count FROM admins";
    $stmt = $db->query($query);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "<div class='info'><h2>📝 Aucun admin trouvé, création d'un admin par défaut...</h2></div>";
        
        $username = 'admin';
        $password = 'admin123';
        $email = 'admin@aadelice.sn';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO admins (username, password, email) VALUES (:username, :password, :email)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':email' => $email
        ]);
        
        echo "<div class='success'>
            <h2>✅ Compte admin créé avec succès !</h2>
            <p><strong>Username:</strong> $username</p>
            <p><strong>Password:</strong> $password</p>
            <p><strong>Email:</strong> $email</p>
        </div>";
    } else {
        echo "<div class='info'>✅ Des comptes admin existent déjà dans la base de données</div>";
        
        // Afficher les admins existants
        $query = "SELECT id, username, email FROM admins";
        $stmt = $db->query($query);
        $admins = $stmt->fetchAll();
        
        echo "<div class='info'><h3>Comptes admin existants:</h3><ul>";
        foreach ($admins as $admin) {
            echo "<li>ID: {$admin['id']} - Username: {$admin['username']} - Email: {$admin['email']}</li>";
        }
        echo "</ul></div>";
    }
    
    echo "<div class='success'>
        <h2>✅ Vérification terminée !</h2>
        <p>La base de données est maintenant prête à être utilisée.</p>
        <p><strong>⚠️ Supprimez ce fichier (fix_database.php) après utilisation pour la sécurité.</strong></p>
    </div>";
    
} catch(PDOException $e) {
    echo "<div class='error'>
        <h2>❌ Erreur</h2>
        <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Code:</strong> " . $e->getCode() . "</p>
    </div>";
}

echo "</body></html>";
?>

