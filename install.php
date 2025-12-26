<?php
// Fichier d'installation - À supprimer après utilisation

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $host = $_POST['host'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $database = $_POST['database'];
    
    try {
        // Connexion à MySQL
        $conn = new PDO("mysql:host=$host", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Créer la base de données
        $conn->exec("CREATE DATABASE IF NOT EXISTS $database");
        $conn->exec("USE $database");
        
        // Exécuter le script SQL
        $sql = file_get_contents('database.sql');
        $conn->exec($sql);
        
        // Créer le fichier de configuration
        $config_content = "<?php
class Database {
    private \$host = \"$host\";
    private \$db_name = \"$database\";
    private \$username = \"$username\";
    private \$password = \"$password\";
    public \$conn;

    public function getConnection() {
        \$this->conn = null;

        try {
            \$this->conn = new PDO(
                \"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name . \";charset=utf8mb4\",
                \$this->username,
                \$this->password
            );
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException \$exception) {
            echo \"Erreur de connexion: \" . \$exception->getMessage();
        }

        return \$this->conn;
    }
}

// Fonction pour sécuriser les données
function sanitize(\$data) {
    \$data = trim(\$data);
    \$data = stripslashes(\$data);
    \$data = htmlspecialchars(\$data, ENT_QUOTES, 'UTF-8');
    return \$data;
}

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>";
        
        file_put_contents('config/database.php', $config_content);
        
        echo "<div class='alert alert-success'>Installation réussie ! Supprimez ce fichier (install.php) maintenant.</div>";
        
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>Erreur: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Installation Aadelice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="text-center mb-4">Installation Aadelice</h1>
            <form method="POST">
                <div class="mb-3">
                    <label>Hôte MySQL</label>
                    <input type="text" name="host" class="form-control" value="localhost" required>
                </div>
                <div class="mb-3">
                    <label>Nom d'utilisateur MySQL</label>
                    <input type="text" name="username" class="form-control" value="root" required>
                </div>
                <div class="mb-3">
                    <label>Mot de passe MySQL</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Nom de la base de données</label>
                    <input type="text" name="database" class="form-control" value="aadelice_db" required>
                </div>
                <button type="submit" name="install" class="btn btn-primary w-100">Installer</button>
            </form>
        </div>
    </div>
</body>
</html>