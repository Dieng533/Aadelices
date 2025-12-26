<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les détails de la commande
$order_id = $_GET['id'] ?? '';

try {
    $query = "SELECT o.*, c.first_name, c.last_name, c.phone, c.email, c.address, c.city, c.quartier 
              FROM orders o 
              LEFT JOIN customers c ON o.customer_id = c.id 
              WHERE o.id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die('Commande non trouvée');
    }
    
    // Récupérer les articles de la commande avec tous les détails
    $query = "SELECT oi.*, p.name, p.image_url, p.weight 
              FROM order_items oi 
              LEFT JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':order_id' => $order_id]);
    $items = $stmt->fetchAll();
    
} catch(PDOException $e) {
    die('Erreur: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmation de commande - Aadelice</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Quicksand:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="images/Aadelice_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --primary: #E6007E;
            --secondary: #00BFA6;
            --tertiary: #333;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .confirmation-container {
            padding: 60px 0;
        }

        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon i {
            font-size: 60px;
            color: white;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .confirmation-title {
            font-family: 'Quicksand', sans-serif;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 2.5rem;
        }

        .confirmation-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 40px;
        }

        .order-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .order-card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%);
            color: white;
            padding: 25px 30px;
            border: none;
        }

        .order-card-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .order-card-body {
            padding: 30px;
        }

        .info-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .info-section h5 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1.2rem;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }

        .info-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .info-item i {
            color: var(--secondary);
            margin-right: 12px;
            font-size: 1.1rem;
            width: 25px;
        }

        .info-item strong {
            color: var(--tertiary);
            margin-right: 8px;
            min-width: 120px;
        }

        .info-item span {
            color: #555;
        }

        .order-table {
            margin-top: 25px;
        }

        .order-table thead {
            background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%);
            color: white;
        }

        .order-table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .order-table tbody td {
            padding: 20px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        .order-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .order-table tfoot {
            background: #f8f9fa;
        }

        .order-table tfoot td {
            padding: 15px;
            font-weight: 600;
            border-top: 2px solid var(--primary);
        }

        .order-table tfoot .total-row {
            background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%);
            color: white;
            font-size: 1.2rem;
        }

        .order-table tfoot .total-row td {
            border: none;
            padding: 20px 15px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .alert-info-custom {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: none;
            border-left: 4px solid var(--primary);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }

        .alert-info-custom i {
            color: var(--primary);
            font-size: 1.3rem;
            margin-right: 10px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, #d40071 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(230, 0, 126, 0.3);
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230, 0, 126, 0.4);
            color: white;
        }

        .btn-success-custom {
            background: linear-gradient(135deg, var(--secondary) 0%, #00a693 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 191, 166, 0.3);
        }

        .btn-success-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 191, 166, 0.4);
            color: white;
        }

        .order-id-badge {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 1px;
        }

        .payment-method-card {
            transition: all 0.3s ease;
        }

        .payment-method-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .delivery-summary-item {
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .delivery-summary-item:hover {
            border-color: var(--primary);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .product-image-large {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #f0f0f0;
        }

        @media (max-width: 768px) {
            .confirmation-title {
                font-size: 2rem;
            }

            .order-card-body {
                padding: 20px;
            }

            .info-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="container">
            <div class="text-center mb-5">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="confirmation-title">Commande confirmée !</h1>
                <p class="confirmation-subtitle">Merci pour votre commande chez Aadelice Dakar 🍬</p>
            </div>
        
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="order-card">
                        <div class="order-card-header">
                            <h3><i class="fas fa-receipt me-2"></i>Récapitulatif de votre commande</h3>
                        </div>
                        <div class="order-card-body">
                            <div class="row mb-4">
                                <div class="col-md-6 mb-4 mb-md-0">
                                    <div class="info-section">
                                        <h5><i class="fas fa-shopping-bag me-2"></i>Informations de commande</h5>
                                        <div class="info-item">
                                            <i class="fas fa-hashtag"></i>
                                            <strong>N° Commande:</strong>
                                            <span class="order-id-badge"><?php echo htmlspecialchars($order['id']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-calendar-alt"></i>
                                            <strong>Date:</strong>
                                            <span><?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Statut:</strong>
                                            <span class="status-badge status-pending">En traitement</span>
                                        </div>
                                        <?php if ($order['shipping_zone']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <strong>Zone:</strong>
                                            <span><?php echo htmlspecialchars($order['shipping_zone']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-section">
                                        <h5><i class="fas fa-user me-2"></i>Informations client</h5>
                                        <div class="info-item">
                                            <i class="fas fa-user-circle"></i>
                                            <strong>Nom:</strong>
                                            <span><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-phone"></i>
                                            <strong>Téléphone:</strong>
                                            <span><?php echo htmlspecialchars($order['phone']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-envelope"></i>
                                            <strong>Email:</strong>
                                            <span><?php echo htmlspecialchars($order['email']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Adresse de livraison complète -->
                            <div class="info-section mb-4">
                                <h5><i class="fas fa-truck me-2"></i>Informations de livraison</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <strong>Adresse complète:</strong>
                                            <span><?php echo htmlspecialchars($order['delivery_address'] ?? $order['address'] ?? 'Non spécifiée'); ?></span>
                                        </div>
                                        <?php if ($order['quartier']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-building"></i>
                                            <strong>Quartier:</strong>
                                            <span><?php echo htmlspecialchars($order['quartier']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($order['city']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-city"></i>
                                            <strong>Ville:</strong>
                                            <span><?php echo htmlspecialchars($order['city']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($order['shipping_zone']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-map"></i>
                                            <strong>Zone de livraison:</strong>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($order['shipping_zone']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($order['delivery_instructions']): ?>
                                        <div class="info-item">
                                            <i class="fas fa-sticky-note"></i>
                                            <strong>Instructions:</strong>
                                            <span><?php echo htmlspecialchars($order['delivery_instructions']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="info-item">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <strong>Frais de livraison:</strong>
                                            <span><?php echo number_format($order['shipping_amount'], 0, ',', ' '); ?> FCFA</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Mode de paiement -->
                            <div class="info-section mb-4">
                                <h5><i class="fas fa-credit-card me-2"></i>Mode de paiement</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <?php 
                                        $payment_method = $order['payment_method'] ?? 'cash';
                                        $isCash = ($payment_method === 'cash' || $payment_method === 'Paiement à la livraison');
                                        $isWave = ($payment_method === 'wave' || $payment_method === 'Wave');
                                        ?>
                                        <div class="payment-method-card p-3 rounded" style="background: <?php echo $isCash ? '#e8f5e9' : '#e3f2fd'; ?>; border: 2px solid <?php echo $isCash ? '#4caf50' : '#2196F3'; ?>;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="paymentMethodDisplay" 
                                                       id="cashPayment" <?php echo $isCash ? 'checked' : ''; ?> disabled>
                                                <label class="form-check-label" for="cashPayment">
                                                    <i class="fas fa-money-bill-wave me-2"></i>
                                                    <strong>Paiement à la livraison</strong>
                                                </label>
                                            </div>
                                            <?php if ($isCash): ?>
                                            <p class="mt-2 mb-0 small">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Le paiement s'effectuera en espèces à la livraison. Montant à préparer: 
                                                <strong><?php echo number_format($order['final_amount'], 0, ',', ' '); ?> FCFA</strong>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="payment-method-card p-3 rounded" style="background: <?php echo $isWave ? '#e8f5e9' : '#f5f5f5'; ?>; border: 2px solid <?php echo $isWave ? '#4caf50' : '#ddd'; ?>;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="paymentMethodDisplay" 
                                                       id="wavePayment" <?php echo $isWave ? 'checked' : ''; ?> disabled>
                                                <label class="form-check-label" for="wavePayment">
                                                    <i class="fas fa-mobile-alt me-2"></i>
                                                    <strong>Paiement par Wave</strong>
                                                </label>
                                            </div>
                                            <?php if ($isWave): ?>
                                            <p class="mt-2 mb-0 small">
                                                <i class="fas fa-check-circle me-1 text-success"></i>
                                                Paiement effectué via Wave Business
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                            <h5 class="mb-3" style="color: var(--primary); font-weight: 600;">
                                <i class="fas fa-shopping-cart me-2"></i>Articles commandés
                            </h5>
                            <div class="table-responsive order-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th class="text-center">Quantité</th>
                                            <th class="text-end">Prix unitaire</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                Aucun article dans cette commande
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($item['image_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name'] ?? 'Produit'); ?>" 
                                                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px;"
                                                         onerror="this.src='https://via.placeholder.com/60'">
                                                    <?php else: ?>
                                                    <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-image text-muted"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($item['name'] ?? 'Produit supprimé'); ?></strong>
                                                        <?php if (!empty($item['weight'])): ?>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-weight me-1"></i>
                                                            <?php echo htmlspecialchars($item['weight']); ?>
                                                        </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary" style="font-size: 1.1rem; padding: 10px 15px; min-width: 50px;">
                                                    <i class="fas fa-shopping-cart me-1"></i>
                                                    <?php echo $item['quantity']; ?>
                                                </span>
                                                <br><small class="text-muted">unité(s)</small>
                                            </td>
                                            <td class="text-end">
                                                <strong><?php echo number_format($item['unit_price'], 0, ',', ' '); ?> FCFA</strong>
                                                <br><small class="text-muted">par unité</small>
                                            </td>
                                            <td class="text-end">
                                                <strong style="font-size: 1.2rem; color: var(--primary);">
                                                    <?php echo number_format($item['total_price'], 0, ',', ' '); ?> FCFA
                                                </strong>
                                                <br><small class="text-muted">
                                                    (<?php echo $item['quantity']; ?> × <?php echo number_format($item['unit_price'], 0, ',', ' '); ?>)
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Sous-total:</strong></td>
                                            <td class="text-end"><strong><?php echo number_format($order['total_amount'], 0, ',', ' '); ?> FCFA</strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Livraison:</strong></td>
                                            <td class="text-end"><strong><?php echo number_format($order['shipping_amount'], 0, ',', ' '); ?> FCFA</strong></td>
                                        </tr>
                                        <?php if ($order['discount_amount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Réduction:</strong></td>
                                            <td class="text-end text-danger"><strong>-<?php echo number_format($order['discount_amount'], 0, ',', ' '); ?> FCFA</strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr class="total-row">
                                            <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                                            <td class="text-end"><strong><?php echo number_format($order['final_amount'], 0, ',', ' '); ?> FCFA</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <!-- Résumé pour la livraison -->
                            <div class="info-section" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 4px solid var(--secondary);">
                                <h5 style="color: var(--secondary);"><i class="fas fa-clipboard-check me-2"></i>Résumé pour la livraison</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="delivery-summary-item p-3 bg-white rounded">
                                            <strong><i class="fas fa-user me-2 text-primary"></i>Client:</strong><br>
                                            <span><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span><br>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($order['phone']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="delivery-summary-item p-3 bg-white rounded">
                                            <strong><i class="fas fa-map-marker-alt me-2 text-primary"></i>Adresse:</strong><br>
                                            <span><?php echo htmlspecialchars($order['delivery_address'] ?? $order['address'] ?? 'Non spécifiée'); ?></span><br>
                                            <?php if ($order['quartier']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($order['quartier']); ?>, 
                                                <?php echo htmlspecialchars($order['city'] ?? 'Dakar'); ?>
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="delivery-summary-item p-3 bg-white rounded">
                                            <strong><i class="fas fa-shopping-bag me-2 text-primary"></i>Articles:</strong><br>
                                            <span><?php echo count($items); ?> produit(s) commandé(s)</span><br>
                                            <small class="text-muted">
                                                Total: <strong><?php echo number_format($order['final_amount'], 0, ',', ' '); ?> FCFA</strong>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="delivery-summary-item p-3 bg-white rounded">
                                            <strong><i class="fas fa-credit-card me-2 text-primary"></i>Paiement:</strong><br>
                                            <?php if ($isCash): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-money-bill-wave me-1"></i>À la livraison
                                            </span>
                                            <br><small class="text-muted">Préparer: <?php echo number_format($order['final_amount'], 0, ',', ' '); ?> FCFA</small>
                                            <?php else: ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-mobile-alt me-1"></i>Wave (Payé)
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($order['delivery_instructions']): ?>
                                <div class="mt-3 p-3 bg-white rounded">
                                    <strong><i class="fas fa-sticky-note me-2 text-warning"></i>Instructions spéciales:</strong><br>
                                    <span><?php echo htmlspecialchars($order['delivery_instructions']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="alert alert-info-custom">
                                <i class="fas fa-info-circle"></i>
                                <strong>Prochaines étapes :</strong><br>
                                Notre équipe vous contactera dans les plus brefs délais pour confirmer la livraison.
                                Pour toute question, appelez-nous au <strong>77 163 58 58</strong> ou envoyez un email à <strong>contact@aadelice.sn</strong>.
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="index.php" class="btn btn-primary-custom">
                                    <i class="fas fa-home me-2"></i>Retour à l'accueil
                                </a>
                                <a href="tel:+221771635858" class="btn btn-success-custom ms-2">
                                    <i class="fas fa-phone me-2"></i>Nous contacter
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>