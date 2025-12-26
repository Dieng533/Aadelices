<?php
require_once 'config/database.php';
require_once 'admin_auth.php';

// Vérifier l'authentification
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Configuration pour l'upload d'images
$upload_dir = 'uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 2 * 1024 * 1024; // 2MB

// Fonction pour uploader une image
function uploadImage($file, $current_image = null) {
    global $upload_dir, $allowed_types, $max_size;
    
    // Si pas de nouvelle image, retourner l'image actuelle
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_image;
    }
    
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'L\'image dépasse la taille maximale autorisée par le serveur',
            UPLOAD_ERR_FORM_SIZE => 'L\'image dépasse la taille maximale spécifiée',
            UPLOAD_ERR_PARTIAL => 'L\'upload de l\'image a été interrompu',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier'
        ];
        
        $error_msg = $error_messages[$file['error']] ?? 'Erreur inconnue lors de l\'upload';
        throw new Exception($error_msg);
    }
    
    // Vérifier le type de fichier
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP');
    }
    
    // Vérifier la taille
    if ($file['size'] > $max_size) {
        throw new Exception('L\'image est trop volumineuse. Taille max: 2MB');
    }
    
    // Vérifier si c'est bien une image
    if (!getimagesize($file['tmp_name'])) {
        throw new Exception('Le fichier n\'est pas une image valide');
    }
    
    // Générer un nom de fichier unique
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $destination = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Erreur lors du déplacement du fichier');
    }
    
    // Supprimer l'ancienne image si elle existe
    if ($current_image && file_exists($current_image) && strpos($current_image, $upload_dir) !== false) {
        unlink($current_image);
    }
    
    return $destination;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_products':
        getProducts($db);
        break;
    case 'add_product':
        addProduct($db);
        break;
    case 'update_product':
        updateProduct($db);
        break;
    case 'delete_product':
        deleteProduct($db);
        break;
    case 'get_orders':
        getOrders($db);
        break;
    case 'update_order_status':
        updateOrderStatus($db);
        break;
    case 'get_stats':
        getStats($db);
        break;
    case 'get_customers':
        getCustomers($db);
        break;
    case 'get_order_details':
        getOrderDetails($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Action non valide']);
}

function getProducts($db) {
    try {
        $query = "SELECT * FROM products ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'products' => $products]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function addProduct($db) {
    try {
        // Validation des données
        if (empty($_POST['name']) || empty($_POST['category']) || empty($_POST['price']) || empty($_POST['stock'])) {
            throw new Exception('Tous les champs obligatoires doivent être remplis');
        }
        
        // Upload de l'image
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image_url = uploadImage($_FILES['image']);
        } else {
            throw new Exception('Une image est requise pour le produit');
        }
        
        $query = "INSERT INTO products (name, category, price, stock, weight, image_url, description, rating, reviews, created_at) 
                  VALUES (:name, :category, :price, :stock, :weight, :image_url, :description, :rating, :reviews, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':name' => $_POST['name'],
            ':category' => $_POST['category'],
            ':price' => $_POST['price'],
            ':stock' => $_POST['stock'],
            ':weight' => $_POST['weight'] ?? '',
            ':image_url' => $image_url,
            ':description' => $_POST['description'] ?? '',
            ':rating' => $_POST['rating'] ?? 4.0,
            ':reviews' => $_POST['reviews'] ?? 0
        ]);
        
        $product_id = $db->lastInsertId();
        
        echo json_encode(['success' => true, 'product_id' => $product_id]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateProduct($db) {
    try {
        // Validation des données
        if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['category']) || empty($_POST['price']) || empty($_POST['stock'])) {
            throw new Exception('Tous les champs obligatoires doivent être remplis');
        }
        
        // Récupérer l'image actuelle
        $query = "SELECT image_url FROM products WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $_POST['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $current_image = $product['image_url'] ?? '';
        
        // Upload de la nouvelle image si fournie
        $image_url = $current_image;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image_url = uploadImage($_FILES['image'], $current_image);
        }
        
        $query = "UPDATE products SET 
                  name = :name,
                  category = :category,
                  price = :price,
                  stock = :stock,
                  weight = :weight,
                  image_url = :image_url,
                  description = :description,
                  rating = :rating,
                  reviews = :reviews,
                  updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id' => $_POST['id'],
            ':name' => $_POST['name'],
            ':category' => $_POST['category'],
            ':price' => $_POST['price'],
            ':stock' => $_POST['stock'],
            ':weight' => $_POST['weight'] ?? '',
            ':image_url' => $image_url,
            ':description' => $_POST['description'] ?? '',
            ':rating' => $_POST['rating'] ?? 4.0,
            ':reviews' => $_POST['reviews'] ?? 0
        ]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteProduct($db) {
    try {
        if (empty($_POST['id'])) {
            throw new Exception('ID produit manquant');
        }
        
        // Récupérer l'image avant suppression
        $query = "SELECT image_url FROM products WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $_POST['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Supprimer le produit
        $query = "DELETE FROM products WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $_POST['id']]);
        
        // Supprimer l'image si elle existe
        if ($product && !empty($product['image_url']) && file_exists($product['image_url']) && strpos($product['image_url'], 'uploads/products/') !== false) {
            unlink($product['image_url']);
        }
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getOrders($db) {
    try {
        $query = "SELECT o.*, c.first_name, c.last_name, c.phone, c.email 
                  FROM orders o 
                  LEFT JOIN customers c ON o.customer_id = c.id 
                  ORDER BY o.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'orders' => $orders]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getOrderDetails($db) {
    try {
        $order_id = $_GET['order_id'] ?? $_POST['order_id'] ?? '';
        
        if (empty($order_id)) {
            throw new Exception('ID commande manquant');
        }
        
        // Récupérer la commande avec toutes les informations
        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.phone, c.email, 
                         c.address as customer_address, c.city, c.quartier 
                  FROM orders o 
                  LEFT JOIN customers c ON o.customer_id = c.id 
                  WHERE o.id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
            return;
        }
        
        // Récupérer les articles de la commande avec tous les détails
        $query = "SELECT oi.*, p.name, p.image_url, p.weight 
                  FROM order_items oi 
                  LEFT JOIN products p ON oi.product_id = p.id 
                  WHERE oi.order_id = :order_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':order_id' => $order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order['items'] = $items;
        
        echo json_encode(['success' => true, 'order' => $order]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateOrderStatus($db) {
    try {
        if (empty($_POST['order_id']) || empty($_POST['status'])) {
            throw new Exception('Données manquantes');
        }
        
        $query = "UPDATE orders SET status = :status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id' => $_POST['order_id'],
            ':status' => $_POST['status']
        ]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStats($db) {
    try {
        // Statistiques du jour
        $today = date('Y-m-d');
        $query = "SELECT visits, orders, revenue FROM site_stats WHERE date = :date";
        $stmt = $db->prepare($query);
        $stmt->execute([':date' => $today]);
        $today_stats = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['visits' => 0, 'orders' => 0, 'revenue' => 0];
        
        // Statistiques des 7 derniers jours
        $last_week = date('Y-m-d', strtotime('-7 days'));
        $query = "SELECT SUM(visits) as visits, SUM(orders) as orders, SUM(revenue) as revenue 
                  FROM site_stats WHERE date >= :date";
        $stmt = $db->prepare($query);
        $stmt->execute([':date' => $last_week]);
        $week_stats = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['visits' => 0, 'orders' => 0, 'revenue' => 0];
        
        // Produits les plus vendus
        $query = "SELECT p.name, SUM(oi.quantity) as total_sold 
                  FROM order_items oi 
                  JOIN products p ON oi.product_id = p.id 
                  GROUP BY p.id 
                  ORDER BY total_sold DESC 
                  LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'today' => $today_stats,
            'week' => $week_stats,
            'top_products' => $top_products
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getCustomers($db) {
    try {
        $query = "SELECT * FROM customers ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'customers' => $customers]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>