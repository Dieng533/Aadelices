<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error_message = '';
$success_message = '';
$order_id = '';

// Traitement du formulaire de commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    try {
        // Démarrer une transaction
        $db->beginTransaction();
        
        // 1. Insérer le client
        $query = "INSERT INTO customers (first_name, last_name, email, phone, address, city, quartier) 
                  VALUES (:first_name, :last_name, :email, :phone, :address, :city, :quartier)";
        $stmt = $db->prepare($query);
        
        $stmt->execute([
            ':first_name' => $_POST['first_name'],
            ':last_name' => $_POST['last_name'],
            ':email' => $_POST['email'],
            ':phone' => $_POST['phone'],
            ':address' => $_POST['address'],
            ':city' => $_POST['city'],
            ':quartier' => $_POST['quartier']
        ]);
        
        $customer_id = $db->lastInsertId();
        
        // 2. Calculer les totaux
        $cart_items = json_decode($_POST['cart_items'], true);
        $subtotal = floatval($_POST['subtotal']);
        $shipping = floatval($_POST['shipping']);
        $discount = floatval($_POST['discount']);
        $total = floatval($_POST['total']);
        
        // 3. Créer la commande
        $order_id = 'ADL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $payment_method = $_POST['payment_method'] ?? 'cash';
        
        $query = "INSERT INTO orders (id, customer_id, total_amount, shipping_amount, discount_amount, 
                  final_amount, shipping_zone, delivery_address, delivery_instructions, payment_method, status) 
                  VALUES (:id, :customer_id, :total, :shipping, :discount, :final, :zone, :address, :instructions, :payment, :status)";
        $stmt = $db->prepare($query);
        
        $stmt->execute([
            ':id' => $order_id,
            ':customer_id' => $customer_id,
            ':total' => $subtotal,
            ':shipping' => $shipping,
            ':discount' => $discount,
            ':final' => $total,
            ':zone' => $_POST['zone'] ?? '',
            ':address' => $_POST['address'],
            ':instructions' => $_POST['instructions'] ?? '',
            ':payment' => $payment_method,
            ':status' => $payment_method === 'wave' ? 'pending_payment' : 'pending'
        ]);
        
        // 4. Ajouter les articles de la commande
        foreach ($cart_items as $item) {
            $query = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) 
                      VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price)";
            $stmt = $db->prepare($query);
            
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $item['id'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['price'],
                ':total_price' => $item['price'] * $item['quantity']
            ]);
            
            // Mettre à jour le stock
            $query = "UPDATE products SET stock = stock - :quantity WHERE id = :product_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':quantity' => $item['quantity'],
                ':product_id' => $item['id']
            ]);
        }
        
        // Mettre à jour les statistiques
        $today = date('Y-m-d');
        $query = "INSERT INTO site_stats (date, orders, revenue) 
                  VALUES (:date, 1, :revenue) 
                  ON DUPLICATE KEY UPDATE orders = orders + 1, revenue = revenue + :revenue";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':date' => $today,
            ':revenue' => $total
        ]);
        
        // Valider la transaction
        $db->commit();
        
        // Si paiement Wave, rediriger vers la page de paiement Wave
        if ($payment_method === 'wave') {
            // Construire l'URL Wave avec les paramètres de la commande
            $wave_url = "https://pay.wave.com/m/M_sn_c4ENmbvHHlp4/c/sn/";
            $wave_url .= "?amount=" . urlencode($total);
            $wave_url .= "&order_id=" . urlencode($order_id);
            $wave_url .= "&customer_name=" . urlencode($_POST['first_name'] . ' ' . $_POST['last_name']);
            $wave_url .= "&customer_phone=" . urlencode($_POST['phone']);
            $wave_url .= "&customer_email=" . urlencode($_POST['email']);
            
            // Rediriger vers Wave
            header("Location: " . $wave_url);
            exit;
        } else {
            // Pour paiement à la livraison, rediriger vers la confirmation
            header("Location: order_confirmation.php?id=" . urlencode($order_id));
            exit;
        }
        
    } catch(PDOException $e) {
        $db->rollBack();
        $error_message = "Erreur lors de la commande: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panier - Aadelice</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Quicksand:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Styles -->
    <link rel="shortcut icon" href="images/Aadelice_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cart.css">
    <style>
        .shipping-zone {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .shipping-zone:hover {
            border-color: var(--primary);
            background-color: rgba(231, 0, 126, 0.05);
        }
        
        .shipping-zone.selected {
            border-color: var(--primary);
            background-color: rgba(231, 0, 126, 0.1);
        }
        
        .shipping-zone-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .shipping-zone-details h6 {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .shipping-zone-details p {
            margin-bottom: 0;
            font-size: 12px;
            color: #666;
        }
        
        .shipping-zone-price {
            font-weight: 600;
            color: var(--primary);
        }
        
        .wave-payment-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .wave-logo {
            color: #21D07D;
            font-size: 24px;
        }
        
        .payment-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .payment-divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #ddd;
        }
        
        .payment-divider span {
            background-color: white;
            padding: 0 15px;
            color: #666;
            font-size: 14px;
        }
        
        .payment-info {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }
        
        .delivery-time {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-top: 5px;
        }
        
        /* Nouveau design du panier */
        .cart-item-modern {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .cart-item-modern:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .cart-item-image-modern {
            position: relative;
            width: 120px;
            height: 120px;
            min-width: 120px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .cart-item-image-modern img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .cart-item-modern:hover .cart-item-image-modern img {
            transform: scale(1.05);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(230, 0, 126, 0.1) 0%, rgba(0, 191, 166, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .cart-item-modern:hover .image-overlay {
            opacity: 1;
        }

        .cart-item-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }

        .cart-item-title-modern {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--tertiary);
            margin: 0;
            flex: 1;
        }

        .btn-remove-modern {
            background: #f8f9fa;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc3545;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .btn-remove-modern:hover {
            background: #dc3545;
            color: white;
            transform: rotate(90deg);
        }

        .cart-item-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .cart-item-price-modern {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-label {
            font-size: 0.9rem;
            color: #666;
        }

        .price-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 25px;
        }

        .quantity-btn-modern {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: white;
            color: var(--primary);
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .quantity-btn-modern:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 10px rgba(230, 0, 126, 0.3);
        }

        .quantity-btn-modern:active {
            transform: scale(0.95);
        }

        .quantity-decrease {
            color: #dc3545;
        }

        .quantity-decrease:hover {
            background: #dc3545;
            color: white;
        }

        .quantity-increase {
            color: var(--secondary);
        }

        .quantity-increase:hover {
            background: var(--secondary);
            color: white;
        }

        .quantity-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 60px;
        }

        .quantity-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--tertiary);
            line-height: 1;
        }

        .quantity-label {
            font-size: 0.75rem;
            color: #999;
            margin-top: 2px;
        }

        .cart-item-total-modern {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .total-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .total-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }

        /* Bouton de paiement Wave amélioré */
        .wave-payment-btn {
            background: linear-gradient(135deg, #21D07D 0%, #1AB168 100%);
            border: none;
            color: white;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-bottom: 15px;
        }
        
        .wave-payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 208, 125, 0.3);
            color: white;
        }
        
        .wave-payment-btn i {
            font-size: 1.3rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cart-item-modern {
                flex-direction: column;
                padding: 15px;
            }

            .cart-item-image-modern {
                width: 100%;
                height: 200px;
            }

            .cart-item-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .cart-item-total-modern {
                align-items: flex-start;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
            }
            
            .wave-payment-btn {
                padding: 12px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="logo-container me-2">
                    <div class="logo-circle">
                        <img src="images/Aadelice_logo.png" alt="Logo Aadelice">
                    </div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#produits">Bonbons</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#categories">Catégories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#about">À propos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="cart.php" class="cart-icon me-4">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="cart-count">0</span>
                    </a>
                    <a href="admin.php" class="btn btn-primary-custom">Admin</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Panier -->
    <section class="cart-page">
        <div class="container">
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <h1 class="cart-page-title">Mon panier</h1>
                    
                    <div class="cart-items-container">
                        <div id="cartItemsList">
                            <!-- Les articles du panier seront chargés ici -->
                    </div>
                    
                        <div id="emptyCart" class="cart-empty">
                            <div class="cart-empty-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h3 class="cart-empty-title">Votre panier est vide</h3>
                            <p class="text-muted mb-4">Ajoutez des délices pour les voir apparaître ici</p>
                            <a href="index.php" class="btn btn-primary-custom">Découvrir nos bonbons</a>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4" id="cartActions">
                        <a href="index.php" class="btn-continue-shopping">
                            <i class="fas fa-arrow-left me-2"></i>Continuer mes achats
                        </a>
                        <button class="btn btn-outline-primary-custom" id="clearCartBtn">
                            <i class="fas fa-trash me-2"></i>Vider le panier
                        </button>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h3 class="summary-title">Récapitulatif</h3>
                        
                        <div class="summary-row">
                            <span class="summary-label">Sous-total</span>
                            <span class="summary-value" id="subtotal">0 FCFA</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Livraison</span>
                            <span class="summary-value" id="shippingCost">0 FCFA</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Réduction</span>
                            <span class="summary-value" id="discount">-0 FCFA</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Total TTC</span>
                            <span class="summary-value summary-total" id="total">0 FCFA</span>
                        </div>
                        
                        <p class="summary-shipping">
                            <i class="fas fa-truck me-2"></i>
                            Livraison offerte à partir de 10.000 FCFA d'achat
                        </p>
                        
                        <!-- Options de livraison - Zones de Dakar -->
                        <div class="mb-4">
                            <h6 class="mb-3">Choisissez votre zone de livraison Dakar</h6> 
                            
                            <div class="shipping-zone" data-zone="Zone 1" data-price="1000">
                                <div class="shipping-zone-content">
                                    <div class="shipping-zone-details">
                                        <h6>Zone 1 - Liberté / Ouest Foire</h6>
                                        <p>Liberté 5, Liberté 6, HLM, Ouest Foire, Nord Foir, Grand Yoff</p>
                                        <span class="delivery-time">Livraison: 2-3h</span>
                                    </div>
                                    <div class="shipping-zone-price">1.000 FCFA</div>
                                </div>
                            </div>
                            
                            <div class="shipping-zone" data-zone="Zone 2" data-price="1500">
                                <div class="shipping-zone-content">
                                    <div class="shipping-zone-details">
                                        <h6>Zone 2 - Almadies / Mermoz</h6>
                                        <p>Almadies, Mermoz, Sacré-Cœur, Ngor, Ouakam</p>
                                        <span class="delivery-time">Livraison: 3-4h</span>
                                    </div>
                                    <div class="shipping-zone-price">1.500 FCFA</div>
                                </div>
                            </div>

                            <div class="shipping-zone" data-zone="Zone 3" data-price="2000">
                                <div class="shipping-zone-content">
                                    <div class="shipping-zone-details">
                                        <h6>Zone 3 - Centre Ville</h6>
                                        <p>Plateau, Médina, Gueule Tapée, Colobane</p>
                                        <span class="delivery-time">Livraison: 1-2h</span>
                                    </div>
                                    <div class="shipping-zone-price">2.000 FCFA</div>
                                </div>
                            </div>
                            
                            <div class="shipping-zone" data-zone="Zone 4" data-price="2000">
                                <div class="shipping-zone-content">
                                    <div class="shipping-zone-details">
                                        <h6>Zone 4 - Parcelles / Nord</h6>
                                        <p>Parcelles Assainies, Cambérène, Patte d'Oie, Dieuppeul</p>
                                        <span class="delivery-time">Livraison: 4-5h</span>
                                    </div>
                                    <div class="shipping-zone-price">2.000 FCFA</div>
                                </div>
                            </div>
                            
                            <div class="shipping-zone" data-zone="Zone 5" data-price="2500">
                                <div class="shipping-zone-content">
                                    <div class="shipping-zone-details">
                                        <h6>Zone 5 - Pikine / Guédiawaye</h6>
                                        <p>Pikine, Guédiawaye, Thiaroye, Keur Massar</p>
                                        <span class="delivery-time">Livraison: 5-6h</span>
                                    </div>
                                    <div class="shipping-zone-price">2.500 FCFA</div>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Les zones sont basées sur la distance depuis notre boutique à Ouest Foire
                                </small>
                            </div>
                        </div>
                        
                        <!-- Code promo -->
                        <div class="mb-4">
                            <label for="promoCode" class="form-label">Code promo</label>
                            <div class="input-group">
                                <input type="text" class="form-control-address" id="promoCode" placeholder="Entrez votre code">
                                <button class="btn btn-outline-primary-custom" type="button" id="applyPromoBtn">
                                    Appliquer
                                </button>
                            </div>
                            <div id="promoMessage" class="small mt-1"></div>
                        </div>
                        
                        <!-- Options de paiement -->
                        <div class="mb-4">
                            <h6 class="mb-3">Mode de paiement</h6>
                            
                            <div class="payment-options">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="cashOnDelivery" checked>
                                    <label class="form-check-label" for="cashOnDelivery">
                                        <i class="fas fa-money-bill-wave me-2"></i>Paiement à la livraison
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="paymentMethod" id="wavePayment">
                                    <label class="form-check-label" for="wavePayment">
                                        <i class="fas fa-mobile-alt me-2 wave-logo"></i>Payer avec Wave
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Informations de paiement -->
                            <div id="cashOnDeliveryInfo" class="mt-3">
                                <div class="payment-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Le paiement s'effectue en espèces à la livraison. Préparez le montant exact si possible.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Formulaire d'adresse -->
                        <form id="addressForm" class="address-form" method="POST" action="cart.php">
                            <input type="hidden" name="submit_order" value="1">
                            <input type="hidden" name="cart_items" id="cartItemsInput">
                            <input type="hidden" name="subtotal" id="subtotalInput">
                            <input type="hidden" name="shipping" id="shippingInput">
                            <input type="hidden" name="discount" id="discountInput">
                            <input type="hidden" name="total" id="totalInput">
                            <input type="hidden" name="zone" id="zoneInput">
                            <input type="hidden" name="instructions" id="instructionsInput">
                            <input type="hidden" name="payment_method" id="paymentMethodInput">
                        
                            <h6 class="address-form-title mb-3">
                                <i class="fas fa-home me-2"></i>
                                Informations de livraison
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="text" class="form-control-address" id="firstName" name="first_name" placeholder="Prénom *" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control-address" id="lastName" name="last_name" placeholder="Nom *" required>
                                </div>
                                <div class="col-12">
                                    <input type="email" class="form-control-address" id="email" name="email" placeholder="Email *" required>
                                </div>
                                <div class="col-12">
                                    <input type="tel" class="form-control-address" id="phone" name="phone" placeholder="Téléphone *" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label small">Zone de livraison sélectionnée :</label>
                                    <div class="alert alert-info py-2" id="selectedZoneDisplay">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <span id="zoneText">Aucune zone sélectionnée</span>
                                        <span class="float-end" id="zonePrice">0 FCFA</span>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <textarea class="form-control-address form-textarea-address" id="address" name="address" rows="3" placeholder="Adresse détaillée * (Rue, bâtiment, repères...)" required></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <input type="text" class="form-control-address" id="city" name="city" placeholder="Ville *" value="Dakar" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control-address" id="quartier" name="quartier" placeholder="Quartier *" required>
                                </div>
                                
                                <div class="col-12">
                                    <textarea class="form-control-address form-textarea-address" id="deliveryInstructions" name="instructions" rows="2" placeholder="Instructions pour le livreur (optionnel)"></textarea>
                                </div>
                            </div>
                            
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="saveAddress">
                                <label class="form-check-label" for="saveAddress">
                                    Enregistrer ces informations pour mes prochaines commandes
                                </label>
                            </div>
                            
                            <div class="cart-actions mt-4">
                                <!-- Le bouton sera généré dynamiquement selon le mode de paiement -->
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-logo">Aa<span>delice</span></div>
                    <p>Depuis 2023, Aadelice réinvente l'art de la confiserie avec des créations uniques qui ravissent petits et grands.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                        <a href="mailto:contact@aadelice.sn"><i class="fas fa-envelope"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-heading">Boutique</h5>
                    <ul class="footer-links">
                        <li><a href="index.php#produits">Tous les bonbons</a></li>
                        <li><a href="#">Nouveautés</a></li>
                        <li><a href="#">Best-sellers</a></li>
                        <li><a href="#">Offres spéciales</a></li>
                        <li><a href="#">Cadeaux</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-heading">Informations</h5>
                    <ul class="footer-links">
                        <li><a href="#">À propos</a></li>
                        <li><a href="#">Livraison</a></li>
                        <li><a href="#">Paiement sécurisé</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4 mb-4">
                    <h5 class="footer-heading">Contact</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Dakar Ouest Foire, Sénégal</li>
                        <li><i class="fas fa-phone me-2"></i> +221 77 163 58 58</li>
                        <li><i class="fas fa-envelope me-2"></i> contact@aadelice.sn</li>
                        <li><i class="fas fa-clock me-2"></i> Lun-Dim: 9h-20h</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 Aadelice. Tous droits réservés. | <a href="#" class="text-white">Mentions légales</a> | <a href="#" class="text-white">Politique de confidentialité</a></p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du panier
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        let selectedZone = null;
        let shippingCost = 0;
        let discount = 0;
        let promoApplied = false;
        let paymentMethod = 'cash';

        // Mettre à jour le compteur du panier
        function updateCartCount() {
            const count = cart.reduce((total, item) => total + item.quantity, 0);
            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = count;
            }
            localStorage.setItem('cart', JSON.stringify(cart));
        }

        // Afficher les articles du panier
        function displayCartItems() {
            const cartItemsList = document.getElementById('cartItemsList');
            const emptyCart = document.getElementById('emptyCart');
            const cartActions = document.getElementById('cartActions');
            const cartActionsDiv = document.querySelector('.cart-actions');
            
            if (cart.length === 0) {
                cartItemsList.innerHTML = '';
                emptyCart.classList.remove('d-none');
                if (cartActions) cartActions.style.display = 'none';
                if (cartActionsDiv) cartActionsDiv.innerHTML = '';
                return;
            }
            
            emptyCart.classList.add('d-none');
            if (cartActions) cartActions.style.display = 'flex';

            cartItemsList.innerHTML = cart.map(item => `
                <div class="cart-item-modern" data-id="${item.id}">
                    <div class="cart-item-image-modern">
                        <img src="${item.image || 'https://via.placeholder.com/120'}" 
                             alt="${item.name}" 
                             onerror="this.src='https://via.placeholder.com/120'">
                        <div class="image-overlay"></div>
                    </div>
                    <div class="cart-item-content">
                        <div class="cart-item-header">
                            <h5 class="cart-item-title-modern">${item.name}</h5>
                            <button class="btn-remove-modern" data-id="${item.id}" title="Supprimer">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="cart-item-info">
                            <div class="cart-item-price-modern">
                                <span class="price-label">Prix unitaire:</span>
                                <span class="price-value">${item.price.toLocaleString('fr-FR')} FCFA</span>
                            </div>
                        </div>
                        <div class="cart-item-controls">
                            <div class="quantity-controls">
                                <button class="quantity-btn-modern quantity-decrease" data-id="${item.id}" title="Diminuer">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <div class="quantity-display">
                                    <span class="quantity-value">${item.quantity}</span>
                                    <span class="quantity-label">unité(s)</span>
                                </div>
                                <button class="quantity-btn-modern quantity-increase" data-id="${item.id}" title="Augmenter">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="cart-item-total-modern">
                                <span class="total-label">Total:</span>
                                <span class="total-value">${(item.price * item.quantity).toLocaleString('fr-FR')} FCFA</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            updateCartSummary();
            updatePaymentButton();
        }

        // Mettre à jour le récapitulatif
        function updateCartSummary() {
            const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            
            // Appliquer la livraison gratuite pour les commandes > 10.000 FCFA
            if (subtotal >= 10000) {
                shippingCost = 0;
            } else if (selectedZone) {
                shippingCost = selectedZone.price;
            } else {
                shippingCost = 0;
            }

            // Calculer le total
            const total = subtotal + shippingCost - discount;

            // Mettre à jour l'affichage
            const subtotalEl = document.getElementById('subtotal');
            const shippingEl = document.getElementById('shippingCost');
            const discountEl = document.getElementById('discount');
            const totalEl = document.getElementById('total');
            
            if (subtotalEl) subtotalEl.textContent = subtotal.toLocaleString() + ' FCFA';
            if (shippingEl) shippingEl.textContent = shippingCost.toLocaleString() + ' FCFA';
            if (discountEl) discountEl.textContent = '-' + discount.toLocaleString() + ' FCFA';
            if (totalEl) totalEl.textContent = total.toLocaleString() + ' FCFA';

            // Afficher la zone sélectionnée
            const zoneDisplay = document.getElementById('selectedZoneDisplay');
            const zoneText = document.getElementById('zoneText');
            const zonePrice = document.getElementById('zonePrice');

            if (selectedZone && zoneDisplay && zoneText && zonePrice) {
                zoneText.textContent = selectedZone.name;
                zonePrice.textContent = selectedZone.price.toLocaleString() + ' FCFA';
                zoneDisplay.classList.remove('alert-warning');
                zoneDisplay.classList.add('alert-info');
            } else if (zoneDisplay && zoneText && zonePrice) {
                zoneText.textContent = 'Sélectionnez une zone de livraison';
                zonePrice.textContent = '0 FCFA';
                zoneDisplay.classList.remove('alert-info');
                zoneDisplay.classList.add('alert-warning');
            }
        }

        // Mettre à jour le bouton de paiement selon le mode choisi
        function updatePaymentButton() {
            const cartActionsDiv = document.querySelector('.cart-actions');
            if (!cartActionsDiv) return;
            
            if (!selectedZone || cart.length === 0) {
                cartActionsDiv.innerHTML = `
                    <button type="button" class="btn-checkout" disabled style="opacity: 0.7;">
                        <i class="fas fa-lock me-2"></i>Complétez les informations
                    </button>
                `;
                return;
            }
            
            // Calculer le total pour l'affichage
            const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            const total = subtotal + shippingCost - discount;
            
            if (paymentMethod === 'cash') {
                cartActionsDiv.innerHTML = `
                    <button type="submit" class="btn-checkout">
                        <i class="fas fa-lock me-2"></i>Commander (${total.toLocaleString('fr-FR')} FCFA)
                    </button>
                    <div class="payment-info text-center mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Vous paierez à la livraison
                    </div>
                `;
            } else {
                cartActionsDiv.innerHTML = `
                    <button type="submit" class="btn-checkout">
                        <i class="fas fa-mobile-alt me-2"></i>Payer avec Wave (${total.toLocaleString('fr-FR')} FCFA)
                    </button>
                    <div class="payment-info text-center mt-2">
                        <i class="fas fa-lock me-1"></i>
                        Redirection vers Wave pour un paiement sécurisé
                    </div>
                `;
            }
        }

        // Gérer les zones de livraison
        function setupShippingZones() {
            document.querySelectorAll('.shipping-zone').forEach(zone => {
                zone.addEventListener('click', function() {
                    // Retirer la sélection précédente
                    document.querySelectorAll('.shipping-zone').forEach(z => {
                        z.classList.remove('selected');
                    });

                    // Sélectionner la nouvelle zone
                    this.classList.add('selected');

                    // Enregistrer la zone sélectionnée
                    selectedZone = {
                        name: this.dataset.zone,
                        price: parseInt(this.dataset.price)
                    };

                    // Mettre à jour le récapitulatif et le bouton
                    updateCartSummary();
                    updatePaymentButton();
                });
            });
        }

        // Gérer les options de paiement
        function setupPaymentOptions() {
            document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.id === 'wavePayment') {
                        paymentMethod = 'wave';
                    } else {
                        paymentMethod = 'cash';
                    }
                    
                    // Mettre à jour le bouton de paiement
                    updatePaymentButton();
                });
            });
        }

        // Gérer le code promo
        function setupPromoCode() {
            const applyPromoBtn = document.getElementById('applyPromoBtn');
            if (applyPromoBtn) {
                applyPromoBtn.addEventListener('click', function() {
                    const promoCode = document.getElementById('promoCode').value.trim();
                    const promoMessage = document.getElementById('promoMessage');

                    // Codes promo disponibles
                    const promoCodes = {
                        'AADELICE10': 10, // 10% de réduction
                        'BIENVENUE': 15,  // 15% de réduction
                        'LIVRAISON': 1000 // 1000 FCFA de réduction
                    };

                    if (promoCode in promoCodes) {
                        const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
                        
                        if (promoCode === 'LIVRAISON') {
                            discount = 1000;
                        } else {
                            discount = (subtotal * promoCodes[promoCode]) / 100;
                        }

                        if (promoMessage) {
                            promoMessage.textContent = `Code promo appliqué : ${discount.toLocaleString()} FCFA de réduction`;
                            promoMessage.style.color = 'green';
                        }
                        promoApplied = true;
                    } else {
                        discount = 0;
                        if (promoMessage) {
                            promoMessage.textContent = 'Code promo invalide';
                            promoMessage.style.color = 'red';
                        }
                        promoApplied = false;
                    }

                    updateCartSummary();
                    updatePaymentButton();
                });
            }
        }

        // Gérer les événements
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            displayCartItems();
            setupShippingZones();
            setupPaymentOptions();
            setupPromoCode();

            // Gérer les changements de quantité
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('quantity-btn-modern') || e.target.closest('.quantity-btn-modern')) {
                    const btn = e.target.classList.contains('quantity-btn-modern') ? e.target : e.target.closest('.quantity-btn-modern');
                    const id = parseInt(btn.dataset.id);
                    const item = cart.find(item => item.id === id);
                    
                    if (item) {
                        if (btn.classList.contains('quantity-increase')) {
                            item.quantity++;
                        } else if (btn.classList.contains('quantity-decrease') && item.quantity > 1) {
                            item.quantity--;
                        }
                        
                        updateCartCount();
                        displayCartItems();
                    }
                }

                // Supprimer un article
                if (e.target.classList.contains('btn-remove-modern') || e.target.closest('.btn-remove-modern')) {
                    const btn = e.target.classList.contains('btn-remove-modern') ? e.target : e.target.closest('.btn-remove-modern');
                    const id = parseInt(btn.dataset.id);
                    cart = cart.filter(item => item.id !== id);
                    
                    updateCartCount();
                    displayCartItems();
                }
            });

            // Vider le panier
            const clearCartBtn = document.getElementById('clearCartBtn');
            if (clearCartBtn) {
                clearCartBtn.addEventListener('click', function() {
                    if (confirm('Voulez-vous vraiment vider votre panier ?')) {
                        cart = [];
                        updateCartCount();
                        displayCartItems();
                    }
                });
            }

            // Soumission du formulaire - Envoyer au serveur PHP
            const addressForm = document.getElementById('addressForm');
            if (addressForm) {
                addressForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (cart.length === 0) {
                        alert('Votre panier est vide !');
                        return;
                    }

                    if (!selectedZone) {
                        alert('Veuillez sélectionner une zone de livraison.');
                        return;
                    }

                    // Valider tous les champs requis
                    const requiredFields = ['firstName', 'lastName', 'email', 'phone', 'address', 'city', 'quartier'];
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        const input = document.getElementById(field);
                        if (!input || !input.value.trim()) {
                            isValid = false;
                            input.classList.add('is-invalid');
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    });

                    if (!isValid) {
                        alert('Veuillez remplir tous les champs obligatoires.');
                        return;
                    }

                    // Calculer les totaux
                    const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
                    const total = subtotal + shippingCost - discount;

                    // Préparer les données pour le formulaire PHP
                    const cartItemsInput = document.getElementById('cartItemsInput');
                    const subtotalInput = document.getElementById('subtotalInput');
                    const shippingInput = document.getElementById('shippingInput');
                    const discountInput = document.getElementById('discountInput');
                    const totalInput = document.getElementById('totalInput');
                    const zoneInput = document.getElementById('zoneInput');
                    const instructionsInput = document.getElementById('instructionsInput');
                    const paymentMethodInput = document.getElementById('paymentMethodInput');

                    if (cartItemsInput) cartItemsInput.value = JSON.stringify(cart);
                    if (subtotalInput) subtotalInput.value = subtotal;
                    if (shippingInput) shippingInput.value = shippingCost;
                    if (discountInput) discountInput.value = discount;
                    if (totalInput) totalInput.value = total;
                    if (zoneInput) zoneInput.value = selectedZone.name;
                    if (instructionsInput) instructionsInput.value = document.getElementById('deliveryInstructions').value;
                    if (paymentMethodInput) paymentMethodInput.value = paymentMethod;

                    // Afficher un message de chargement
                    const checkoutBtn = document.querySelector('.btn-checkout');
                    if (checkoutBtn) {
                        const originalText = checkoutBtn.innerHTML;
                        checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement en cours...';
                        checkoutBtn.disabled = true;
                        
                        // Soumettre le formulaire après un court délai
                        setTimeout(() => {
                            this.submit();
                        }, 500);
                    } else {
                        this.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>