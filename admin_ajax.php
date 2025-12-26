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
        $query = "INSERT INTO products (name, category, price, stock, weight, image_url, description, rating, reviews) 
                  VALUES (:name, :category, :price, :stock, :weight, :image_url, :description, :rating, :reviews)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':name' => $_POST['name'],
            ':category' => $_POST['category'],
            ':price' => $_POST['price'],
            ':stock' => $_POST['stock'],
            ':weight' => $_POST['weight'] ?? '',
            ':image_url' => $_POST['image_url'],
            ':description' => $_POST['description'] ?? '',
            ':rating' => $_POST['rating'] ?? 4.0,
            ':reviews' => $_POST['reviews'] ?? 0
        ]);
        
        $product_id = $db->lastInsertId();
        
        echo json_encode(['success' => true, 'product_id' => $product_id]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateProduct($db) {
    try {
        $query = "UPDATE products SET 
                  name = :name,
                  category = :category,
                  price = :price,
                  stock = :stock,
                  weight = :weight,
                  image_url = :image_url,
                  description = :description,
                  rating = :rating,
                  reviews = :reviews
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id' => $_POST['id'],
            ':name' => $_POST['name'],
            ':category' => $_POST['category'],
            ':price' => $_POST['price'],
            ':stock' => $_POST['stock'],
            ':weight' => $_POST['weight'] ?? '',
            ':image_url' => $_POST['image_url'],
            ':description' => $_POST['description'] ?? '',
            ':rating' => $_POST['rating'] ?? 4.0,
            ':reviews' => $_POST['reviews'] ?? 0
        ]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteProduct($db) {
    try {
        $query = "DELETE FROM products WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $_POST['id']]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
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
    }
}

function updateOrderStatus($db) {
    try {
        $query = "UPDATE orders SET status = :status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id' => $_POST['order_id'],
            ':status' => $_POST['status']
        ]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
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
