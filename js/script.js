// script.js - Script principal mis à jour pour utiliser JSON

class AadeliceApp {
    constructor() {
        this.cart = JSON.parse(localStorage.getItem('aadeliceCart')) || [];
        this.products = [];
        
        this.init();
    }
    
    async init() {
        await this.loadProducts();
        this.initCart();
        this.initEvents();
        this.displayProducts();
        this.updateCartCount();
    }
    
    async loadProducts() {
        try {
            const response = await fetch('data/products.json');
            const data = await response.json();
            this.products = data.products;
        } catch (error) {
            console.error('Erreur lors du chargement des produits:', error);
            // Utiliser des données par défaut en cas d'erreur
            this.products = [
                {
                    id: 1,
                    name: "Hitschies Acidulés Halal 100g",
                    category: "Acidulé",
                    price: 2.50,
                    description: "Des bonbons acidulés avec une explosion de saveurs fruitées. Certifiés halal.",
                    image: "https://images.unsplash.com/photo-1582058091505-f87a2e55a40f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80",
                    stock: 50,
                    rating: 4.5,
                    reviews: 48,
                    tags: ["acidulé", "halal", "fruité"]
                }
            ];
        }
    }
    
    displayProducts() {
        const container = document.getElementById('productsContainer');
        if (!container) return;
        
        let html = '';
        
        this.products.forEach(product => {
            const stockClass = this.getStockClass(product.stock);
            const stockText = this.getStockText(product.stock);
            
            html += `
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="product-card">
                        <img src="${product.image}" class="product-image" alt="${product.name}">
                        <div class="p-3">
                            <div class="product-category" style="color: ${this.getCategoryColor(product.category)};">
                                ${product.category}
                            </div>
                            <h5 class="product-title">${product.name}</h5>
                            <p class="product-description">${product.description}</p>
                            
                            <div class="product-rating">
                                ${this.generateStarRating(product.rating)}
                                <span>(${product.reviews})</span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="product-price">${product.price.toFixed(2).replace('.', ',')} €</div>
                                <div class="product-stock ${stockClass}">${stockText}</div>
                            </div>
                            
                            <button class="btn btn-add-to-cart mt-3" 
                                    data-id="${product.id}" 
                                    data-name="${product.name}" 
                                    data-price="${product.price}"
                                    ${product.stock === 0 ? 'disabled' : ''}>
                                <i class="fas fa-cart-plus me-2"></i>
                                ${product.stock === 0 ? 'Rupture' : 'Ajouter'}
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    getCategoryColor(category) {
        const colors = {
            'Acidulé': '#008D36',
            'Fruité': '#FF9800',
            'Dragées': '#9C27B0',
            'Sucettes': '#2196F3',
            'Chocolat': '#8B4513',
            'Guimave': '#E6007E'
        };
        
        return colors[category] || '#008D36';
    }
    
    generateStarRating(rating) {
        let stars = '';
        const fullStars = Math.floor(rating);
        const hasHalfStar = rating % 1 >= 0.5;
        
        for (let i = 0; i < 5; i++) {
            if (i < fullStars) {
                stars += '<i class="fas fa-star"></i>';
            } else if (i === fullStars && hasHalfStar) {
                stars += '<i class="fas fa-star-half-alt"></i>';
            } else {
                stars += '<i class="far fa-star"></i>';
            }
        }
        
        return stars;
    }
    
    getStockClass(stock) {
        if (stock === 0) return 'out-of-stock';
        if (stock < 10) return 'low-stock';
        return 'in-stock';
    }
    
    getStockText(stock) {
        if (stock === 0) return 'Rupture';
        if (stock < 10) return `Plus que ${stock}`;
        return 'En stock';
    }
    
    initCart() {
        this.updateCartDisplay();
    }
    
    updateCartCount() {
        const totalItems = this.cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartCountElements = document.querySelectorAll('.cart-count');
        
        cartCountElements.forEach(element => {
            element.textContent = totalItems;
        });
    }
    
    updateCartDisplay() {
        // Implémentation existante du panier...
    }
    
    initEvents() {
        // Gestion des boutons "Ajouter au panier"
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-add-to-cart')) {
                const button = e.target.closest('.btn-add-to-cart');
                const productId = button.getAttribute('data-id');
                const productName = button.getAttribute('data-name');
                const productPrice = parseFloat(button.getAttribute('data-price'));
                
                this.addToCart(productId, productName, productPrice);
            }
        });
        
        // Newsletter
        const newsletterForm = document.querySelector('.newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const email = newsletterForm.querySelector('input[type="email"]').value;
                this.subscribeNewsletter(email);
            });
        }
    }
    
    addToCart(productId, productName, productPrice) {
        // Implémentation existante...
    }
    
    subscribeNewsletter(email) {
        // Implémentation de la newsletter...
    }
}

// Initialiser l'application
document.addEventListener('DOMContentLoaded', () => {
    window.aadeliceApp = new AadeliceApp();
});