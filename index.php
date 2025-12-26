<?php
require_once 'config/database.php';

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Fonction pour compter les visites
function countVisit($db) {
    $today = date('Y-m-d');
    
    try {
        // Vérifier si une entrée existe pour aujourd'hui
        $query = "SELECT * FROM site_stats WHERE date = :date";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':date', $today);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Incrémenter les visites
            $query = "UPDATE site_stats SET visits = visits + 1 WHERE date = :date";
        } else {
            // Créer une nouvelle entrée
            $query = "INSERT INTO site_stats (date, visits) VALUES (:date, 1)";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':date', $today);
        $stmt->execute();
        
    } catch(PDOException $e) {
        error_log("Erreur compteur visites: " . $e->getMessage());
    }
}

// Compter cette visite
countVisit($db);

// Récupérer tous les produits
function getProducts($db) {
    try {
        $query = "SELECT * FROM products WHERE stock > 0 ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur récupération produits: " . $e->getMessage());
        return [];
    }
}

$products = getProducts($db);

// Fonction pour obtenir la couleur d'une catégorie
function getCategoryColor($category) {
    $colors = [
        'Sucres' => '#E6007E',
        'Sans sucre' => '#00BFA6',
        'Box' => '#9C27B0',
        'Boisson' => '#2196F3',
        'Divers' => '#E6007E'
    ];
    return $colors[$category] ?? '#E6007E';
}

// Fonction pour générer les étoiles de notation
function generateRatingStars($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $hasHalfStar = ($rating % 1) >= 0.5;
    
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star"></i>';
    }
    
    if ($hasHalfStar) {
        $stars .= '<i class="fas fa-star-half-alt"></i>';
    }
    
    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="far fa-star"></i>';
    }
    
    return $stars;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aadelice - Douceurs & Gourmandises Dakar</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Quicksand:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="shortcut icon" href="images/Aadelice_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cart.css">
    <style>
        /* Filtres de catégories */
        .category-filters {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }

        .category-filter-btn {
            padding: 8px 20px;
            border-radius: 50px;
            border: 2px solid var(--primary);
            background-color: white;
            color: var(--primary);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .category-filter-btn:hover,
        .category-filter-btn.active {
            background-color: var(--primary);
            color: white;
        }

        /* Badge de poids */
        .weight-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--secondary);
            color: white;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
        }

        .fcf-price {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.5rem;
        }

        .fcf-symbol {
            font-size: 1.2rem;
        }

        /* Login Admin */
        .admin-login-modal .modal-content {
            border-radius: 15px;
        }

        .admin-login-modal .modal-header {
            background-color: var(--primary);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .login-logo {
            font-family: 'Quicksand', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary);
            text-align: center;
            margin-bottom: 20px;
        }

        .login-logo span {
            color: var(--secondary);
        }

        .btn-admin-login {
            background-color: var(--tertiary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-admin-login:hover {
            background-color: #222;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Animation d'ajout au panier */
        @keyframes addToCartAnimation {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .added-to-cart {
            animation: addToCartAnimation 0.3s ease;
        }

        /* Style pour le compteur du panier */
        .cart-icon {
            position: relative;
            text-decoration: none;
            color: var(--tertiary);
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .cart-icon:hover {
            color: var(--primary);
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Styles pour les produits dynamiques */
        .product-category {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .btn-add-to-cart {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-add-to-cart:hover {
            background-color: #d40071;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(230, 0, 126, 0.2);
        }

        .btn-add-to-cart.btn-success {
            background-color: #28a745 !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-2">
        <div class="container">
            <!-- Logo avec image et arrière-plan rose -->
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="logo-container me-2">
                    <div class="logo-circle">
                        <img src="images/Aadelice_logo.png" alt="Logo Aadelice">
                    </div>
                </div>
            </a>

            <!-- Bouton menu mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu navigation -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home d-lg-none me-2"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#produits">
                            <i class="fas fa-candy-cane d-lg-none me-2"></i>Bonbons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#categories">
                            <i class="fas fa-tags d-lg-none me-2"></i>Catégories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">
                            <i class="fas fa-info-circle d-lg-none me-2"></i>À propos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">
                            <i class="fas fa-envelope d-lg-none me-2"></i>Contact
                        </a>
                    </li>
                </ul>

                <!-- Actions utilisateur -->
                <div class="d-flex align-items-center">
                    <a href="cart.php" class="cart-icon me-4 position-relative">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="cart-count">0</span>
                    </a>
                    <button class="btn btn-admin-login" data-bs-toggle="modal" data-bs-target="#adminLoginModal">
                        <i class="fas fa-lock me-2"></i>Admin
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                <h1 class="hero-title">Découvrez la <span>douceur</span> sénégalaise <span class="halal-badge">Halal</span></h1>
<p class="hero-subtitle">Aadelice vous propose une sélection exclusive de <strong>bonbons 100% Halal</strong> à Dakar. Des
    saveurs acidulées, sucrées et fruitées certifiées Halal pour émerveiller vos papilles en toute sérénité. Livraison rapide dans tout
    Dakar!</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#produits" class="btn btn-primary-custom">Explorer la collection</a>
                        <a href="#contact" class="btn btn-secondary-custom">
                            <i class="fas fa-phone me-2"></i>Nous contacter
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="images/hero-image.jpg" class="img-fluid rounded-3 shadow-lg" alt="Bonbons colorés">
                        <div class="position-absolute bottom-0 start-0 bg-white p-3 rounded-3 shadow m-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary rounded-circle p-2 me-2">
                                    <i class="fas fa-truck text-white"></i>
                                </div>
                                <div>
                                    <p class="mb-0 fw-bold">Livraison rapide Dakar</p>
                                    <p class="mb-0 small text-muted">À partir de 1000 FCFA</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Produits populaires avec filtres -->
    <section class="py-5" id="produits">
        <div class="container">
            <h2 class="section-title">Nos délices populaires</h2>

            <!-- Filtres de catégories -->
            <div class="category-filters">
                <button class="category-filter-btn active" data-filter="all">Tous</button>
                <button class="category-filter-btn" data-filter="Sucres">Sucrés</button>
                <button class="category-filter-btn" data-filter="Sans sucre">Sans sucre</button>
                <button class="category-filter-btn" data-filter="Box">Box</button>
                <button class="category-filter-btn" data-filter="Boisson">Boissons</button>
                <button class="category-filter-btn" data-filter="Divers">Divers</button>
            </div>

            <div class="row g-4" id="productsContainer">
                <?php foreach ($products as $product): ?>
                <div class="col-md-6 col-lg-4 col-xl-3 product-item" data-category="<?php echo $product['category']; ?>">
                    <div class="product-card">
                        <div class="weight-badge"><?php echo $product['weight']; ?></div>
                        <img src="<?php echo $product['image_url']; ?>" 
                             class="product-image" 
                             alt="<?php echo $product['name']; ?>"
                             onerror="this.src='https://via.placeholder.com/300x200?text=Aadelice'">
                        <div class="p-3">
                            <div class="product-category" style="color: <?php echo getCategoryColor($product['category']); ?>;">
                                <?php echo $product['category']; ?>
                            </div>
                            <h5 class="product-title"><?php echo $product['name']; ?></h5>
                            <div class="product-rating">
                                <?php echo generateRatingStars($product['rating']); ?>
                                <span>(<?php echo $product['reviews']; ?>)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fcf-price"><?php echo number_format($product['price'], 0, ',', ' '); ?> <span class="fcf-symbol">FCFA</span></div>
                                <button class="btn btn-add-to-cart" 
                                        data-id="<?php echo $product['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-price="<?php echo $product['price']; ?>"
                                        data-image="<?php echo $product['image_url']; ?>"
                                        data-weight="<?php echo $product['weight']; ?>">
                                    <i class="fas fa-cart-plus me-2"></i>Ajouter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="#contact" class="btn btn-secondary-custom">Voir tous nos produits</a>
                <a href="tel:+221771635858" class="btn btn-primary-custom ms-3">
                    <i class="fas fa-phone me-2"></i>Commander par téléphone
                </a>
            </div>
        </div>
    </section>

    <!-- Catégories -->
    <section class="py-5 bg-light" id="categories">
        <div class="container">
            <h2 class="section-title">Nos Catégories</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon feature-icon-1">
                            <i class="fas fa-candy-cane"></i>
                        </div>
                        <h4 class="feature-title">Sucrés</h4>
                        <p>Des bonbons classiques pour les amateurs de sensations sucrées réconfortantes.</p>
                        <a href="#" class="text-primary fw-bold" data-filter="Sucres">Découvrir →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon feature-icon-2">
                            <i class="fas fa-apple-alt"></i>
                        </div>
                        <h4 class="feature-title">Sans sucre</h4>
                        <p>Des alternatives diététiques pour profiter des bonbons sans culpabilité.</p>
                        <a href="#" class="text-primary fw-bold" data-filter="Sans sucre">Découvrir →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon" style="background-color: #9C27B0;">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h4 class="feature-title">Box Cadeaux</h4>
                        <p>Des assortiments variés pour offrir ou se faire plaisir en grande quantité.</p>
                        <a href="#" class="text-primary fw-bold" data-filter="Box">Découvrir →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon" style="background-color: #2196F3;">
                            <i class="fas fa-wine-bottle"></i>
                        </div>
                        <h4 class="feature-title">Boissons</h4>
                        <p>Des sodas et boissons aromatisées aux saveurs de bonbons préférés.</p>
                        <a href="#" class="text-primary fw-bold" data-filter="Boisson">Découvrir →</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- À propos -->
    <section class="py-5" id="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="section-title text-start">À propos d'Aadelice Dakar</h2>
                    <p class="mb-4">Bienvenue chez <strong>Aadelice</strong>, votre boutique de bonbons préférée à Dakar Ouest Foire !</p>
                    <p>Depuis notre création, nous nous engageons à vous offrir les meilleures douceurs du marché. Notre sélection rigoureuse comprend des produits de qualité, adaptés à tous les goûts et besoins.</p>

                    <div class="mt-4">
                        <h5>Pourquoi choisir Aadelice ?</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Produits frais et de qualité supérieure</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Large choix de catégories</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Livraison rapide dans Dakar</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Service client réactif</li>
                            <li><i class="fas fa-check text-success me-2"></i> Prix compétitifs en FCFA</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="images/about.jpg" class="img-fluid rounded-3 shadow-lg" alt="Boutique Aadelice">
                        <div class="position-absolute bottom-0 start-0 bg-white p-3 rounded-3 shadow m-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary rounded-circle p-2 me-2">
                                    <i class="fas fa-store text-white"></i>
                                </div>
                                <div>
                                    <p class="mb-0 fw-bold">Dakar Ouest Foire</p>
                                    <p class="mb-0 small text-muted">Ouvert 7j/7</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section class="newsletter-section" id="contact">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="newsletter-title">Contactez-nous !</h2>
                    <p class="mb-4">Vous avez des questions ou souhaitez passer commande ? Contactez-nous directement par téléphone ou email.</p>

                    <div class="row g-4 mt-4">
                        <div class="col-md-6">
                            <div class="feature-card h-100">
                                <div class="feature-icon" style="background-color: var(--tertiary);">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h4>Email</h4>
                                <p>Envoyez-nous un email pour toute demande ou commande.</p>
                                <a href="mailto:contact@aadelice.sn" class="btn btn-tertiary-custom w-100">
                                    <i class="fas fa-envelope me-2"></i>Écrire un email
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card h-100">
                                <div class="feature-icon" style="background-color: var(--primary);">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <h4>Téléphone</h4>
                                <p>Appelez-nous pour vos commandes ou renseignements.</p>
                                <a href="tel:+221771635858" class="btn btn-primary-custom w-100">
                                    <i class="fas fa-phone me-2"></i>Appeler maintenant
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <h5>Informations de livraison</h5>
                        <p class="mb-3">Livraison offerte à partir de 10.000 FCFA d'achat dans Dakar.</p>
                        <p class="small">Zone de livraison : Dakar et ses environs • Horaires : 9h-19h</p>
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
                    <p>Votre boutique de bonbons préférée à Dakar Ouest Foire. Des douceurs pour tous les goûts !</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="mailto:contact@aadelice.sn"><i class="fas fa-envelope"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="footer-heading">Boutique</h5>
                    <ul class="footer-links">
                        <li><a href="#produits">Tous les bonbons</a></li>
                        <li><a href="#categories">Catégories</a></li>
                        <li><a href="#about">À propos</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="footer-heading">Informations</h5>
                    <ul class="footer-links">
                        <li><a href="#">Livraison</a></li>
                        <li><a href="#">Moyens de paiement</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Conditions générales</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="footer-heading">Contact</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Dakar Ouest Foire, Sénégal</li>
                        <li><i class="fas fa-phone me-2"></i> +221 77 163 58 58</li>
                        <li><i class="fas fa-envelope me-2"></i> Email: contact@aadelice.sn</li>
                        <li><i class="fas fa-clock me-2"></i> Lundi-Dimanche: 9h-20h</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 Aadelice Dakar. Tous droits réservés. | <a href="#" class="text-white">Mentions légales</a> | <a href="#" class="text-white">Politique de confidentialité</a></p>
            </div>
        </div>
    </footer>

    <!-- Modal Login Admin -->
    <div class="modal fade admin-login-modal" id="adminLoginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-lock me-2"></i>Connexion Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="login-logo">Aa<span>delice</span> Admin</div>
                    <form id="adminLoginForm" method="POST" action="admin_login.php">
                        <div class="mb-3">
                            <label for="adminUsername" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="adminUsername" name="username" required>
                        </div>
                        <div class="mb-4">
                            <label for="adminPassword" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="adminPassword" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100">Se connecter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle avec Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Fonction pour obtenir la couleur d'une catégorie (JavaScript pour les filtres)
        function getCategoryColorJS(category) {
            const colors = {
                'Sucres': '#E6007E',
                'Sans sucre': '#00BFA6',
                'Box': '#9C27B0',
                'Boisson': '#2196F3',
                'Divers': '#E6007E'
            };
            return colors[category] || '#E6007E';
        }

        // Gestion du panier
        function updateCartCount() {
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            const count = cart.reduce((total, item) => total + item.quantity, 0);
            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = count;
            }
        }

        // Initialiser les événements d'ajout au panier
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            
            // Filtres de catégories
            document.querySelectorAll('.category-filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const filter = this.dataset.filter;

                    // Mettre à jour le bouton actif
                    document.querySelectorAll('.category-filter-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    this.classList.add('active');

                    // Filtrer les produits
                    document.querySelectorAll('.product-item').forEach(item => {
                        if (filter === 'all' || item.dataset.category === filter) {
                            item.style.display = 'block';
                            setTimeout(() => {
                                item.style.opacity = '1';
                            }, 50);
                        } else {
                            item.style.opacity = '0';
                            setTimeout(() => {
                                item.style.display = 'none';
                            }, 300);
                        }
                    });
                });
            });

            // Ajout au panier
            document.querySelectorAll('.btn-add-to-cart').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const id = parseInt(this.dataset.id);
                    const name = this.dataset.name;
                    const price = parseFloat(this.dataset.price);
                    const image = this.dataset.image;
                    const weight = this.dataset.weight;
                    
                    // Charger le panier actuel
                    let cart = JSON.parse(localStorage.getItem('cart')) || [];
                    
                    // Vérifier si l'article existe déjà
                    const existingItemIndex = cart.findIndex(item => item.id === id);
                    
                    if (existingItemIndex !== -1) {
                        // Incrémenter la quantité
                        cart[existingItemIndex].quantity++;
                    } else {
                        // Ajouter un nouvel article
                        cart.push({
                            id: id,
                            name: name,
                            price: price,
                            quantity: 1,
                            image: image,
                            weight: weight
                        });
                    }
                    
                    // Sauvegarder le panier
                    localStorage.setItem('cart', JSON.stringify(cart));
                    
                    // Mettre à jour le compteur
                    updateCartCount();
                    
                    // Animation
                    this.classList.add('added-to-cart');
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check me-2"></i>Ajouté';
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.remove('added-to-cart');
                    }, 1500);
                });
            });

            // Filtrage depuis les cartes de catégories
            document.querySelectorAll('.feature-card a[data-filter]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const filter = this.dataset.filter;
                    const button = document.querySelector(`.category-filter-btn[data-filter="${filter}"]`);
                    if (button) {
                        button.click();
                        document.getElementById('produits').scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });

            // Connexion admin via AJAX
            document.getElementById('adminLoginForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('admin_login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fermer le modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('adminLoginModal'));
                        modal.hide();
                        
                        // Rediriger vers l'admin
                        window.location.href = 'admin.php';
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de connexion');
                });
            });
        });
    </script>
</body>
</html>