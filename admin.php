<?php
require_once 'config/database.php';
require_once 'admin_auth.php';

// Vérifier l'authentification
if (!isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Configuration pour l'upload d'images
$upload_dir = 'uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Récupérer les statistiques complètes
function getStatistics($db) {
    $stats = [
        'total_products' => 0,
        'total_orders' => 0,
        'total_revenue' => 0,
        'total_customers' => 0,
        'today_visits' => 0,
        'today_orders' => 0,
        'today_revenue' => 0,
        'week_visits' => 0,
        'week_orders' => 0,
        'week_revenue' => 0,
        'pending_orders' => 0
    ];
    
    try {
        // Nombre de produits
        $query = "SELECT COUNT(*) as count FROM products";
        $stmt = $db->query($query);
        $stats['total_products'] = $stmt->fetch()['count'];
        
        // Nombre de commandes
        $query = "SELECT COUNT(*) as count FROM orders";
        $stmt = $db->query($query);
        $stats['total_orders'] = $stmt->fetch()['count'];
        
        // Revenu total
        $query = "SELECT SUM(final_amount) as total FROM orders WHERE status != 'cancelled'";
        $stmt = $db->query($query);
        $stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;
        
        // Nombre de clients
        $query = "SELECT COUNT(*) as count FROM customers";
        $stmt = $db->query($query);
        $stats['total_customers'] = $stmt->fetch()['count'];
        
        // Statistiques du jour
        $today = date('Y-m-d');
        $query = "SELECT visits, orders, revenue FROM site_stats WHERE date = :date";
        $stmt = $db->prepare($query);
        $stmt->execute([':date' => $today]);
        $today_data = $stmt->fetch();
        if ($today_data) {
            $stats['today_visits'] = $today_data['visits'] ?? 0;
            $stats['today_orders'] = $today_data['orders'] ?? 0;
            $stats['today_revenue'] = $today_data['revenue'] ?? 0;
        }
        
        // Statistiques de la semaine
        $last_week = date('Y-m-d', strtotime('-7 days'));
        $query = "SELECT SUM(visits) as visits, SUM(orders) as orders, SUM(revenue) as revenue 
                  FROM site_stats WHERE date >= :date";
        $stmt = $db->prepare($query);
        $stmt->execute([':date' => $last_week]);
        $week_data = $stmt->fetch();
        if ($week_data) {
            $stats['week_visits'] = $week_data['visits'] ?? 0;
            $stats['week_orders'] = $week_data['orders'] ?? 0;
            $stats['week_revenue'] = $week_data['revenue'] ?? 0;
        }
        
        // Commandes en attente
        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'";
        $stmt = $db->query($query);
        $stats['pending_orders'] = $stmt->fetch()['count'];
        
    } catch(PDOException $e) {
        error_log("Erreur statistiques: " . $e->getMessage());
    }
    
    return $stats;
}

// Récupérer les produits
function getProductsForAdmin($db) {
    try {
        $query = "SELECT * FROM products ORDER BY created_at DESC LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur produits admin: " . $e->getMessage());
        return [];
    }
}

// Récupérer les commandes
function getOrdersForAdmin($db) {
    try {
        $query = "SELECT o.*, c.first_name, c.last_name, c.phone, c.email 
                  FROM orders o 
                  LEFT JOIN customers c ON o.customer_id = c.id 
                  ORDER BY o.created_at DESC 
                  LIMIT 20";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur commandes admin: " . $e->getMessage());
        return [];
    }
}

$stats = getStatistics($db);
$products = getProductsForAdmin($db);
$orders = getOrdersForAdmin($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin - Aadelice</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Quicksand:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="images/Aadelice_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --primary: #E6007E;
            --secondary: #00BFA6;
            --tertiary: #333;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-logo {
            font-family: 'Quicksand', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
        }

        .sidebar-logo span {
            color: var(--primary);
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin: 5px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(230, 0, 126, 0.2);
            color: white;
            border-left-color: var(--primary);
        }

        .sidebar-menu a i {
            width: 25px;
            margin-right: 15px;
            font-size: 1.1rem;
        }

        /* Main Content */
        .admin-main {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .admin-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--tertiary);
            margin: 0;
        }

        .admin-header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-logout {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #d40071;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.revenue { background: linear-gradient(135deg, var(--secondary) 0%, #00a693 100%); }
        .stat-icon.products { background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%); }
        .stat-icon.orders { background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); }
        .stat-icon.customers { background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); }
        .stat-icon.visits { background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); }
        .stat-icon.pending { background: linear-gradient(135deg, #FFC107 0%, #FFA000 100%); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--tertiary);
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.85rem;
            color: var(--secondary);
            font-weight: 600;
        }

        /* Tables */
        .admin-table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .admin-table-container h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--tertiary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: #f8f9fa;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            color: var(--tertiary);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-top: 1px solid #f0f0f0;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-processing { background: #d4edda; color: #155724; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        /* Buttons */
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            margin: 0 2px;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #2196F3;
            color: white;
        }

        .btn-edit:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-view {
            background: var(--secondary);
            color: white;
        }

        .btn-view:hover {
            background: #00a693;
            transform: translateY(-2px);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230, 0, 126, 0.3);
            color: white;
        }

        /* Modal */
        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Form Controls */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(230, 0, 126, 0.25);
        }

        /* Image Preview */
        .image-preview-container {
            margin-top: 10px;
            text-align: center;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px dashed #ddd;
            display: none;
        }
        
        .current-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }

        /* Sections */
        .admin-section {
            display: none;
        }

        .admin-section.active {
            display: block;
        }

        /* Product Image */
        .product-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        /* Modal Order Details */
        #orderDetailContent .table {
            margin-bottom: 0;
        }

        #orderDetailContent .table thead th {
            border: none;
            padding: 12px;
        }

        #orderDetailContent .table tbody td {
            padding: 15px 12px;
            vertical-align: middle;
        }

        #orderDetailContent .payment-method-card {
            transition: all 0.3s ease;
        }

        #orderDetailContent .payment-method-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        #orderDetailContent .bg-light {
            background-color: #f8f9fa !important;
        }

        #orderDetailContent img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
        <!-- Sidebar -->
        <aside class="admin-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">Aa<span>delice</span></div>
            <small style="color: rgba(255,255,255,0.6);">Dashboard Admin</small>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" class="nav-link active" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" data-section="products">
                    <i class="fas fa-candy-cane"></i>
                    <span>Produits</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" data-section="orders">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Commandes</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" data-section="customers">
                    <i class="fas fa-users"></i>
                    <span>Clients</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" data-section="stats">
                    <i class="fas fa-chart-line"></i>
                    <span>Statistiques</span>
                </a>
            </li>
            <li style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                <a href="index.php" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Voir le site</span>
                </a>
            </li>
        </ul>
        </aside>
        
    <!-- Main Content -->
        <main class="admin-main">
        <!-- Header -->
        <div class="admin-header">
            <h1 id="pageTitle">Dashboard</h1>
            <div class="user-info">
                <span>Bienvenue, <strong><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></strong></span>
                <a href="admin_logout.php" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </div>
        </div>

        <!-- Dashboard Section -->
        <section id="dashboard" class="admin-section active">
            <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Revenu Total</div>
                            <div class="stat-value"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> <small style="font-size: 1rem;">FCFA</small></div>
                        </div>
                        <div class="stat-icon revenue">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> Aujourd'hui: <?php echo number_format($stats['today_revenue'], 0, ',', ' '); ?> FCFA
                    </div>
                    </div>
                    
                    <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Produits</div>
                            <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                        </div>
                        <div class="stat-icon products">
                            <i class="fas fa-candy-cane"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-info-circle"></i> En stock
                    </div>
                    </div>
                    
                    <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Commandes</div>
                            <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                        </div>
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-clock"></i> <?php echo $stats['pending_orders']; ?> en attente
                    </div>
                    </div>
                    
                    <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Clients</div>
                            <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
                        </div>
                        <div class="stat-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-user-plus"></i> Total inscrits
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Visiteurs Aujourd'hui</div>
                            <div class="stat-value"><?php echo $stats['today_visits']; ?></div>
                        </div>
                        <div class="stat-icon visits">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-calendar-week"></i> Cette semaine: <?php echo $stats['week_visits']; ?>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-label">Commandes Aujourd'hui</div>
                            <div class="stat-value"><?php echo $stats['today_orders']; ?></div>
                        </div>
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-chart-line"></i> Cette semaine: <?php echo $stats['week_orders']; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Products -->
                <div class="admin-table-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Produits récents</h3>
                    <a href="#" class="nav-link text-primary" data-section="products" style="padding: 0;">
                        <i class="fas fa-arrow-right me-2"></i>Voir tous
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                    Aucun produit pour le moment
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>#<?php echo $product['id']; ?></td>
                                <td>
                                    <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/50'); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="product-thumbnail"
                                         onerror="this.src='https://via.placeholder.com/50'">
                                </td>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($product['category']); ?></span></td>
                                <td><?php echo number_format($product['price'], 0, ',', ' '); ?> FCFA</td>
                                <td>
                                    <span class="badge <?php echo $product['stock'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $product['stock']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>
                
            <!-- Recent Orders -->
                <div class="admin-table-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Commandes récentes</h3>
                    <a href="#" class="nav-link text-primary" data-section="orders" style="padding: 0;">
                        <i class="fas fa-arrow-right me-2"></i>Voir toutes
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-shopping-cart fa-2x mb-2"></i><br>
                                    Aucune commande pour le moment
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['phone'] ?? '-'); ?></td>
                                <td><strong><?php echo number_format($order['final_amount'], 0, ',', ' '); ?> FCFA</strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php 
                                        $statusLabels = [
                                            'pending' => 'En attente',
                                            'confirmed' => 'Confirmée',
                                            'processing' => 'En traitement',
                                            'shipped' => 'Expédiée',
                                            'delivered' => 'Livrée',
                                            'cancelled' => 'Annulée'
                                        ];
                                        echo $statusLabels[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-action btn-view" onclick="viewOrder('<?php echo $order['id']; ?>')" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <select class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="updateOrderStatus('<?php echo $order['id']; ?>', this.value)" title="Changer statut">
                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>En attente</option>
                                        <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmée</option>
                                        <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>En traitement</option>
                                        <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                                        <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                                        <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            </section>
            
        <!-- Products Section -->
        <section id="products" class="admin-section">
            <div class="admin-table-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Gestion des Produits</h3>
                    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openProductModal()">
                        <i class="fas fa-plus me-2"></i>Ajouter un produit
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Poids</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Orders Section -->
        <section id="orders" class="admin-section">
            <div class="admin-table-container">
                <h3>Toutes les Commandes</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="allOrdersTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Customers Section -->
        <section id="customers" class="admin-section">
            <div class="admin-table-container">
                <h3>Liste des Clients</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Ville</th>
                                <th>Date d'inscription</th>
                            </tr>
                        </thead>
                        <tbody id="customersTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Statistics Section -->
        <section id="stats" class="admin-section">
            <div class="admin-table-container">
                <h3>Statistiques Détaillées</h3>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="stat-card">
                            <h5>Visiteurs</h5>
                            <div class="stat-value"><?php echo $stats['week_visits']; ?></div>
                            <div class="stat-label">Visiteurs cette semaine</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="stat-card">
                            <h5>Revenus</h5>
                            <div class="stat-value"><?php echo number_format($stats['week_revenue'], 0, ',', ' '); ?> FCFA</div>
                            <div class="stat-label">Revenus cette semaine</div>
                        </div>
                    </div>
                </div>
            </div>
            </section>
        </main>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Ajouter un produit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm" enctype="multipart/form-data">
                        <input type="hidden" id="productId" name="id">
                        <input type="hidden" id="currentImage" name="current_image">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nom du produit *</label>
                                <input type="text" class="form-control" id="productName" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Catégorie *</label>
                                <select class="form-select" id="productCategory" name="category" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="Sucres">Sucrés</option>
                                    <option value="Sans sucre">Sans sucre</option>
                                    <option value="Box">Box</option>
                                    <option value="Boisson">Boisson</option>
                                    <option value="Divers">Divers</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Prix (FCFA) *</label>
                                <input type="number" class="form-control" id="productPrice" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock *</label>
                                <input type="number" class="form-control" id="productStock" name="stock" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Poids</label>
                                <input type="text" class="form-control" id="productWeight" name="weight" placeholder="ex: 100g">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Image du produit *</label>
                                <input type="file" class="form-control" id="productImage" name="image" accept="image/*">
                                <small class="text-muted">Formats acceptés: JPG, PNG, GIF. Taille max: 2MB</small>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="image-preview-container">
                                    <img id="imagePreview" class="image-preview" alt="Aperçu de l'image">
                                    <div id="currentImageContainer"></div>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary-custom" onclick="saveProduct()">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Detail Modal -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailTitle">
                        <i class="fas fa-shopping-cart me-2"></i>Détails de la commande
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailContent" style="max-height: 80vh; overflow-y: auto;">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation entre sections
        document.querySelectorAll('.sidebar-menu a[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const section = this.dataset.section;
                
                // Mettre à jour les liens actifs
                document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
                this.classList.add('active');
                
                // Afficher la section
                document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
                document.getElementById(section).classList.add('active');
                
                // Mettre à jour le titre
                const titles = {
                    'dashboard': 'Dashboard',
                    'products': 'Gestion des Produits',
                    'orders': 'Commandes',
                    'customers': 'Clients',
                    'stats': 'Statistiques'
                };
                document.getElementById('pageTitle').textContent = titles[section] || 'Dashboard';
                
                // Charger les données si nécessaire
                if (section === 'products') {
                    loadProducts();
                } else if (section === 'orders') {
                    loadAllOrders();
                } else if (section === 'customers') {
                    loadCustomers();
                }
            });
        });

        // Gestion de l'aperçu de l'image
        document.getElementById('productImage').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const currentImageContainer = document.getElementById('currentImageContainer');
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    currentImageContainer.innerHTML = '';
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Charger les produits
        function loadProducts() {
            fetch('admin_ajax.php?action=get_products')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayProducts(data.products);
                    } else {
                        document.getElementById('productsTableBody').innerHTML = 
                            '<tr><td colspan="8" class="text-center text-danger">Erreur: ' + data.message + '</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('productsTableBody').innerHTML = 
                        '<tr><td colspan="8" class="text-center text-danger">Erreur de chargement</td></tr>';
                });
        }

        // Afficher les produits
        function displayProducts(products) {
            const tbody = document.getElementById('productsTableBody');
            
            if (products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucun produit</td></tr>';
                return;
            }
            
            tbody.innerHTML = products.map(product => `
                <tr>
                    <td>#${product.id}</td>
                    <td>
                        <img src="${product.image_url || 'https://via.placeholder.com/50'}" 
                             alt="${product.name}" 
                             class="product-thumbnail"
                             onerror="this.src='https://via.placeholder.com/50'">
                    </td>
                    <td><strong>${product.name}</strong></td>
                    <td><span class="badge bg-primary">${product.category}</span></td>
                    <td>${parseFloat(product.price).toLocaleString('fr-FR')} FCFA</td>
                    <td>
                        <span class="badge ${product.stock > 0 ? 'bg-success' : 'bg-danger'}">
                            ${product.stock}
                        </span>
                    </td>
                    <td>${product.weight || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-action btn-edit" onclick="editProduct(${product.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-action btn-delete" onclick="deleteProduct(${product.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Ouvrir le modal produit
        function openProductModal(productId = null) {
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('currentImage').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('currentImageContainer').innerHTML = '';
            document.getElementById('productModalTitle').textContent = productId ? 'Modifier le produit' : 'Ajouter un produit';
            
            if (productId) {
                // Charger les données du produit
                fetch('admin_ajax.php?action=get_products')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const product = data.products.find(p => p.id == productId);
                            if (product) {
                                document.getElementById('productId').value = product.id;
                                document.getElementById('productName').value = product.name;
                                document.getElementById('productCategory').value = product.category;
                                document.getElementById('productPrice').value = product.price;
                                document.getElementById('productStock').value = product.stock;
                                document.getElementById('productWeight').value = product.weight || '';
                                document.getElementById('currentImage').value = product.image_url || '';
                                document.getElementById('productDescription').value = product.description || '';
                                
                                // Afficher l'image actuelle si elle existe
                                if (product.image_url) {
                                    const currentImageContainer = document.getElementById('currentImageContainer');
                                    currentImageContainer.innerHTML = `
                                        <p class="text-muted mb-2">Image actuelle:</p>
                                        <img src="${product.image_url}" 
                                             class="current-image" 
                                             alt="Image actuelle"
                                             onerror="this.style.display='none'">
                                    `;
                                }
                            }
                        }
                    });
            }
        }

        // Éditer un produit
        function editProduct(id) {
            openProductModal(id);
            const modal = new bootstrap.Modal(document.getElementById('productModal'));
            modal.show();
        }

        // Sauvegarder un produit
        function saveProduct() {
            const form = document.getElementById('productForm');
            const formData = new FormData(form);
            const productId = document.getElementById('productId').value;
            const action = productId ? 'update_product' : 'add_product';
            
            // Vérifier si une image est requise (sauf pour modification si pas de nouvelle image)
            const imageInput = document.getElementById('productImage');
            if (!productId && !imageInput.files[0]) {
                alert('Veuillez sélectionner une image pour le produit');
                return;
            }
            
            fetch(`admin_ajax.php?action=${action}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Produit ' + (productId ? 'modifié' : 'ajouté') + ' avec succès !');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('productModal'));
                    modal.hide();
                    loadProducts();
                    // Recharger la page pour mettre à jour les stats
                    if (document.getElementById('dashboard').classList.contains('active')) {
                        location.reload();
                    }
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la sauvegarde');
            });
        }

        // Supprimer un produit
        function deleteProduct(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('id', id);
            
            fetch('admin_ajax.php?action=delete_product', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Produit supprimé avec succès !');
                    loadProducts();
                    if (document.getElementById('dashboard').classList.contains('active')) {
                        location.reload();
                    }
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la suppression');
            });
        }

        // Charger toutes les commandes
        function loadAllOrders() {
            fetch('admin_ajax.php?action=get_orders')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAllOrders(data.orders);
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // Afficher toutes les commandes
        function displayAllOrders(orders) {
            const tbody = document.getElementById('allOrdersTableBody');
            
            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucune commande</td></tr>';
                return;
            }
            
            const statusLabels = {
                'pending': 'En attente',
                'confirmed': 'Confirmée',
                'processing': 'En traitement',
                'shipped': 'Expédiée',
                'delivered': 'Livrée',
                'cancelled': 'Annulée'
            };
            
            tbody.innerHTML = orders.map(order => `
                <tr>
                    <td><strong>${order.id}</strong></td>
                    <td>${order.first_name} ${order.last_name}</td>
                    <td>${order.phone || '-'}</td>
                    <td>${order.email || '-'}</td>
                    <td><strong>${parseFloat(order.final_amount).toLocaleString('fr-FR')} FCFA</strong></td>
                    <td>
                        <span class="status-badge status-${order.status}">
                            ${statusLabels[order.status] || order.status}
                        </span>
                    </td>
                    <td>${new Date(order.created_at).toLocaleDateString('fr-FR')} ${new Date(order.created_at).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}</td>
                    <td>
                        <button class="btn btn-sm btn-action btn-view" onclick="viewOrder('${order.id}')" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <select class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="updateOrderStatus('${order.id}', this.value)">
                            <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>En attente</option>
                            <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmée</option>
                            <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>En traitement</option>
                            <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Expédiée</option>
                            <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Livrée</option>
                            <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Annulée</option>
                        </select>
                    </td>
                </tr>
            `).join('');
        }

        // Mettre à jour le statut d'une commande
        function updateOrderStatus(orderId, status) {
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('status', status);
            
            fetch('admin_ajax.php?action=update_order_status', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour l'affichage
                    loadAllOrders();
                    if (document.getElementById('dashboard').classList.contains('active')) {
                        location.reload();
                    }
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour');
            });
        }

        // Voir les détails d'une commande
        function viewOrder(orderId) {
            fetch(`admin_ajax.php?action=get_order_details&order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        const statusLabels = {
                            'pending': 'En attente',
                            'confirmed': 'Confirmée',
                            'processing': 'En traitement',
                            'shipped': 'Expédiée',
                            'delivered': 'Livrée',
                            'cancelled': 'Annulée'
                        };
                        
                        // Déterminer le mode de paiement
                        const paymentMethod = order.payment_method || 'cash';
                        const isCash = (paymentMethod === 'cash' || paymentMethod === 'Paiement à la livraison');
                        const isWave = (paymentMethod === 'wave' || paymentMethod === 'Wave');
                        
                        let itemsHtml = '';
                        if (order.items && order.items.length > 0) {
                            itemsHtml = `
                                <h6 class="mt-3 mb-3" style="color: var(--primary); font-weight: 600;">
                                    <i class="fas fa-shopping-cart me-2"></i>Articles commandés
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead style="background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%); color: white;">
                                            <tr>
                                                <th>Produit</th>
                                                <th class="text-center">Quantité</th>
                                                <th class="text-end">Prix unitaire</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${order.items.map(item => `
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            ${item.image_url ? `
                                                                <img src="${item.image_url}" 
                                                                     alt="${item.name || 'Produit'}" 
                                                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px; border: 2px solid #f0f0f0;"
                                                                     onerror="this.src='https://via.placeholder.com/60'">
                                                            ` : `
                                                                <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                                                                    <i class="fas fa-image text-muted"></i>
                                                                </div>
                                                            `}
                                                            <div>
                                                                <strong style="font-size: 1.1rem;">${item.name || 'Produit supprimé'}</strong>
                                                                ${item.weight ? `<br><small class="text-muted"><i class="fas fa-weight me-1"></i>${item.weight}</small>` : ''}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary" style="font-size: 1.1rem; padding: 10px 15px; min-width: 50px;">
                                                            <i class="fas fa-shopping-cart me-1"></i>${item.quantity}
                                                        </span>
                                                        <br><small class="text-muted">unité(s)</small>
                                                    </td>
                                                    <td class="text-end">
                                                        <strong>${parseFloat(item.unit_price).toLocaleString('fr-FR')} FCFA</strong>
                                                        <br><small class="text-muted">par unité</small>
                                                    </td>
                                                    <td class="text-end">
                                                        <strong style="font-size: 1.2rem; color: var(--primary);">
                                                            ${parseFloat(item.total_price).toLocaleString('fr-FR')} FCFA
                                                        </strong>
                                                        <br><small class="text-muted">
                                                            (${item.quantity} × ${parseFloat(item.unit_price).toLocaleString('fr-FR')})
                                                        </small>
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            `;
                        }
                        
                        // Adresse complète - utiliser delivery_address en priorité (de la table orders)
                        // puis customer_address (de la table customers) en fallback
                        const fullAddress = order.delivery_address || order.customer_address || 'Non spécifiée';
                        const quartier = order.quartier || '';
                        const city = order.city || 'Dakar';
                        
                        document.getElementById('orderDetailTitle').textContent = `Commande ${order.id}`;
                        document.getElementById('orderDetailContent').innerHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded mb-3">
                                        <h6 style="color: var(--primary); font-weight: 600;">
                                            <i class="fas fa-user me-2"></i>Informations client
                                        </h6>
                                        <p class="mb-2"><strong>Nom:</strong> ${order.first_name} ${order.last_name}</p>
                                        <p class="mb-2"><strong>Téléphone:</strong> <a href="tel:${order.phone || ''}">${order.phone || '-'}</a></p>
                                        <p class="mb-0"><strong>Email:</strong> ${order.email ? `<a href="mailto:${order.email}">${order.email}</a>` : '-'}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded mb-3">
                                        <h6 style="color: var(--primary); font-weight: 600;">
                                            <i class="fas fa-shopping-cart me-2"></i>Informations commande
                                        </h6>
                                        <p class="mb-2"><strong>Date:</strong> ${new Date(order.created_at).toLocaleString('fr-FR')}</p>
                                        <p class="mb-2"><strong>Statut:</strong> <span class="status-badge status-${order.status}">${statusLabels[order.status] || order.status}</span></p>
                                        <p class="mb-0"><strong>N° Commande:</strong> <span class="badge bg-secondary">${order.id}</span></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Adresse de livraison complète -->
                            <div class="p-3 bg-light rounded mb-3">
                                <h6 style="color: var(--primary); font-weight: 600;">
                                    <i class="fas fa-truck me-2"></i>Informations de livraison
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong><i class="fas fa-map-marker-alt me-2"></i>Adresse complète:</strong><br>
                                        ${fullAddress}</p>
                                        ${quartier ? `<p class="mb-2"><strong><i class="fas fa-building me-2"></i>Quartier:</strong> ${quartier}</p>` : ''}
                                        <p class="mb-2"><strong><i class="fas fa-city me-2"></i>Ville:</strong> ${city}</p>
                                        ${order.shipping_zone ? `<p class="mb-2"><strong><i class="fas fa-map me-2"></i>Zone de livraison:</strong> <span class="badge bg-primary">${order.shipping_zone}</span></p>` : ''}
                                    </div>
                                    <div class="col-md-6">
                                        ${order.delivery_instructions ? `<p class="mb-2"><strong><i class="fas fa-sticky-note me-2"></i>Instructions:</strong><br>${order.delivery_instructions}</p>` : ''}
                                        <p class="mb-0"><strong><i class="fas fa-money-bill-wave me-2"></i>Frais de livraison:</strong> ${parseFloat(order.shipping_amount || 0).toLocaleString('fr-FR')} FCFA</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mode de paiement -->
                            <div class="p-3 bg-light rounded mb-3">
                                <h6 style="color: var(--primary); font-weight: 600;">
                                    <i class="fas fa-credit-card me-2"></i>Mode de paiement
                                </h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <div class="p-3 rounded" style="background: ${isCash ? '#e8f5e9' : '#e3f2fd'}; border: 2px solid ${isCash ? '#4caf50' : '#2196F3'};">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="paymentMethodDisplay" 
                                                       id="cashPayment" ${isCash ? 'checked' : ''} disabled>
                                                <label class="form-check-label" for="cashPayment">
                                                    <i class="fas fa-money-bill-wave me-2"></i>
                                                    <strong>Paiement à la livraison</strong>
                                                </label>
                                            </div>
                                            ${isCash ? `
                                                <p class="mt-2 mb-0 small">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Montant à préparer: <strong>${parseFloat(order.final_amount).toLocaleString('fr-FR')} FCFA</strong>
                                                </p>
                                            ` : ''}
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <div class="p-3 rounded" style="background: ${isWave ? '#e8f5e9' : '#f5f5f5'}; border: 2px solid ${isWave ? '#4caf50' : '#ddd'};">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="paymentMethodDisplay" 
                                                       id="wavePayment" ${isWave ? 'checked' : ''} disabled>
                                                <label class="form-check-label" for="wavePayment">
                                                    <i class="fas fa-mobile-alt me-2"></i>
                                                    <strong>Paiement par Wave</strong>
                                                </label>
                                            </div>
                                            ${isWave ? `
                                                <p class="mt-2 mb-0 small">
                                                    <i class="fas fa-check-circle me-1 text-success"></i>
                                                    Paiement effectué via Wave Business
                                                </p>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${itemsHtml}
                            
                            <!-- Résumé financier -->
                            <div class="mt-3 p-3 bg-light rounded">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Sous-total:</strong> ${parseFloat(order.total_amount).toLocaleString('fr-FR')} FCFA</p>
                                        <p><strong>Livraison:</strong> ${parseFloat(order.shipping_amount).toLocaleString('fr-FR')} FCFA</p>
                                        ${order.discount_amount > 0 ? `<p><strong>Réduction:</strong> -${parseFloat(order.discount_amount).toLocaleString('fr-FR')} FCFA</p>` : ''}
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <h5><strong>TOTAL: ${parseFloat(order.final_amount).toLocaleString('fr-FR')} FCFA</strong></h5>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Résumé pour la livraison -->
                            <div class="mt-3 p-3 rounded" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 4px solid var(--secondary);">
                                <h6 style="color: var(--secondary); font-weight: 600;">
                                    <i class="fas fa-clipboard-check me-2"></i>Résumé pour la livraison
                                </h6>
                                <div class="row mt-3">
                                    <div class="col-md-6 mb-2">
                                        <div class="p-2 bg-white rounded">
                                            <strong><i class="fas fa-user me-2 text-primary"></i>Client:</strong><br>
                                            <span>${order.first_name} ${order.last_name}</span><br>
                                            <small class="text-muted"><i class="fas fa-phone me-1"></i>${order.phone || '-'}</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <div class="p-2 bg-white rounded">
                                            <strong><i class="fas fa-map-marker-alt me-2 text-primary"></i>Adresse:</strong><br>
                                            <span>${fullAddress}</span><br>
                                            ${quartier ? `<small class="text-muted"><i class="fas fa-building me-1"></i>${quartier}, ${city}</small>` : ''}
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <div class="p-2 bg-white rounded">
                                            <strong><i class="fas fa-shopping-bag me-2 text-primary"></i>Articles:</strong><br>
                                            <span>${order.items ? order.items.length : 0} produit(s) commandé(s)</span><br>
                                            <small class="text-muted">Total: <strong>${parseFloat(order.final_amount).toLocaleString('fr-FR')} FCFA</strong></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <div class="p-2 bg-white rounded">
                                            <strong><i class="fas fa-credit-card me-2 text-primary"></i>Paiement:</strong><br>
                                            ${isCash ? `
                                                <span class="badge bg-success"><i class="fas fa-money-bill-wave me-1"></i>À la livraison</span><br>
                                                <small class="text-muted">Préparer: ${parseFloat(order.final_amount).toLocaleString('fr-FR')} FCFA</small>
                                            ` : `
                                                <span class="badge bg-info"><i class="fas fa-mobile-alt me-1"></i>Wave (Payé)</span>
                                            `}
                                        </div>
                                    </div>
                                </div>
                                ${order.delivery_instructions ? `
                                <div class="mt-3 p-3 bg-white rounded">
                                    <strong><i class="fas fa-sticky-note me-2 text-warning"></i>Instructions spéciales:</strong><br>
                                    <span>${order.delivery_instructions}</span>
                                </div>
                                ` : ''}
                            </div>
                        `;
                        const modal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
                        modal.show();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors du chargement des détails');
                });
        }

        // Charger les clients
        function loadCustomers() {
            fetch('admin_ajax.php?action=get_customers')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCustomers(data.customers);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('customersTableBody').innerHTML = 
                        '<tr><td colspan="6" class="text-center text-danger">Erreur de chargement</td></tr>';
                });
        }

        // Afficher les clients
        function displayCustomers(customers) {
            const tbody = document.getElementById('customersTableBody');
            
            if (!customers || customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Aucun client</td></tr>';
                return;
            }
            
            tbody.innerHTML = customers.map(customer => `
                <tr>
                    <td>#${customer.id}</td>
                    <td><strong>${customer.first_name} ${customer.last_name}</strong></td>
                    <td>${customer.email || '-'}</td>
                    <td>${customer.phone || '-'}</td>
                    <td>${customer.city || 'Dakar'}</td>
                    <td>${new Date(customer.created_at).toLocaleDateString('fr-FR')}</td>
                </tr>
            `).join('');
        }

        // Charger les produits au chargement de la page si on est sur la section produits
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier si on doit charger les produits
            if (document.getElementById('products').classList.contains('active')) {
                loadProducts();
            }
        });
    </script>
</body>
</html>