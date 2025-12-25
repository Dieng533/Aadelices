// cart.js - Gestion de la page panier

class CartPage {
    constructor() {
        this.cart = JSON.parse(localStorage.getItem('aadeliceCart')) || [];
        this.shippingCost = 0;
        this.discount = 0;
        this.selectedShipping = 'standard';
        
        this.init();
    }
    
    init() {
        this.updateCartDisplay();
        this.initEvents();
        this.updateCartCount();
        this.selectShipping('standard');
    }
    
    updateCartDisplay() {
        const cartItemsList = document.getElementById('cartItemsList');
        const emptyCart = document.getElementById('emptyCart');
        const cartActions = document.getElementById('cartActions');
        
        if (this.cart.length === 0) {
            cartItemsList.innerHTML = '';
            emptyCart.classList.remove('d-none');
            cartActions.classList.add('d-none');
            this.updateSummary();
            return;
        }
        
        emptyCart.classList.add('d-none');
        cartActions.classList.remove('d-none');
        
        let html = '';
        let subtotal = 0;
        
        this.cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            
            html += `
                <div class="cart-item" data-index="${index}">
                    <img src="${item.image || 'https://images.unsplash.com/photo-1582058091505-f87a2e55a40f?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80'}" 
                         class="cart-item-image" 
                         alt="${item.name}">
                    
                    <div class="cart-item-details">
                        <h6 class="cart-item-title">${item.name}</h6>
                        <div class="cart-item-description">Délicieux bonbons pour toutes les occasions</div>
                        <div class="cart-item-category">Bonbons</div>
                    </div>
                    
                    <div class="cart-item-price">
                        ${item.price.toFixed(2).replace('.', ',')} €
                    </div>
                    
                    <div class="cart-item-quantity">
                        <button class="quantity-btn decrease-quantity" data-index="${index}">-</button>
                        <input type="text" class="quantity-input" value="${item.quantity}" readonly>
                        <button class="quantity-btn increase-quantity" data-index="${index}">+</button>
                    </div>
                    
                    <div class="cart-item-total">
                        ${itemTotal.toFixed(2).replace('.', ',')} €
                    </div>
                    
                    <button class="cart-item-remove remove-item" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        });
        
        cartItemsList.innerHTML = html;
        this.updateSummary(subtotal);
    }
    
    updateSummary(subtotal = 0) {
        const freeShippingThreshold = 30;
        const totalBeforeDiscount = subtotal + this.shippingCost;
        const total = totalBeforeDiscount - this.discount;
        
        // Mettre à jour les valeurs
        document.getElementById('subtotal').textContent = subtotal.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('shippingCost').textContent = this.shippingCost.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('discount').textContent = this.discount.toFixed(2).replace('.', ',') + ' €';
        document.getElementById('total').textContent = total.toFixed(2).replace('.', ',') + ' €';
        
        // Vérifier la livraison gratuite
        const shippingText = document.querySelector('.summary-shipping');
        if (subtotal >= freeShippingThreshold) {
            this.shippingCost = 0;
            document.getElementById('shippingCost').textContent = '0,00 €';
            shippingText.innerHTML = '<i class="fas fa-check-circle me-2 text-success"></i> Livraison offerte !';
        } else {
            const remaining = freeShippingThreshold - subtotal;
            shippingText.innerHTML = `<i class="fas fa-truck me-2"></i> Ajoutez ${remaining.toFixed(2).replace('.', ',')}€ pour la livraison offerte`;
        }
        
        // Activer/désactiver le bouton de commande
        const checkoutBtn = document.getElementById('checkoutBtn');
        checkoutBtn.disabled = this.cart.length === 0;
    }
    
    selectShipping(type) {
        this.selectedShipping = type;
        
        // Mettre à jour l'interface
        document.querySelectorAll('.shipping-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        const selectedOption = document.querySelector(`.shipping-option[data-type="${type}"]`);
        if (selectedOption) {
            selectedOption.classList.add('selected');
            this.shippingCost = parseFloat(selectedOption.getAttribute('data-cost'));
        }
        
        this.updateSummary(this.getSubtotal());
    }
    
    getSubtotal() {
        return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    }
    
    initEvents() {
        // Augmenter la quantité
        document.addEventListener('click', (e) => {
            if (e.target.closest('.increase-quantity')) {
                const button = e.target.closest('.increase-quantity');
                const index = parseInt(button.getAttribute('data-index'));
                this.updateQuantity(index, this.cart[index].quantity + 1);
            }
            
            if (e.target.closest('.decrease-quantity')) {
                const button = e.target.closest('.decrease-quantity');
                const index = parseInt(button.getAttribute('data-index'));
                this.updateQuantity(index, this.cart[index].quantity - 1);
            }
            
            if (e.target.closest('.remove-item')) {
                const button = e.target.closest('.remove-item');
                const index = parseInt(button.getAttribute('data-index'));
                this.removeItem(index);
            }
        });
        
        // Options de livraison
        document.querySelectorAll('.shipping-option').forEach(option => {
            option.addEventListener('click', () => {
                const type = option.getAttribute('data-type');
                this.selectShipping(type);
            });
        });
        
        // Code promo
        document.getElementById('applyPromoBtn').addEventListener('click', () => {
            this.applyPromoCode();
        });
        
        document.getElementById('promoCode').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.applyPromoCode();
            }
        });
        
        // Vider le panier
        document.getElementById('clearCartBtn').addEventListener('click', () => {
            this.clearCart();
        });
        
        // Formulaire de commande
        document.getElementById('addressForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.processCheckout();
        });
    }
    
    updateQuantity(index, newQuantity) {
        if (newQuantity < 1) {
            this.removeItem(index);
            return;
        }
        
        if (newQuantity > 99) {
            newQuantity = 99;
        }
        
        this.cart[index].quantity = newQuantity;
        this.saveCart();
        this.updateCartDisplay();
    }
    
    removeItem(index) {
        if (confirm('Êtes-vous sûr de vouloir retirer cet article du panier ?')) {
            this.cart.splice(index, 1);
            this.saveCart();
            this.updateCartDisplay();
            this.updateCartCount();
        }
    }
    
    clearCart() {
        if (this.cart.length === 0) return;
        
        if (confirm('Êtes-vous sûr de vouloir vider complètement votre panier ?')) {
            this.cart = [];
            this.saveCart();
            this.updateCartDisplay();
            this.updateCartCount();
            this.showNotification('Panier vidé');
        }
    }
    
    applyPromoCode() {
        const promoCode = document.getElementById('promoCode').value.trim();
        const promoMessage = document.getElementById('promoMessage');
        
        if (!promoCode) {
            promoMessage.textContent = 'Veuillez entrer un code promo';
            promoMessage.className = 'small mt-1 text-danger';
            return;
        }
        
        // Codes promo valides (simulation)
        const validCodes = {
            'AADELICE10': 10, // 10% de réduction
            'DOUCEUR5': 5,    // 5€ de réduction
            'LIVRAISONOFFERTE': 'freeShipping' // Livraison offerte
        };
        
        if (validCodes[promoCode]) {
            const discount = validCodes[promoCode];
            
            if (discount === 'freeShipping') {
                this.shippingCost = 0;
                promoMessage.textContent = 'Code appliqué : Livraison offerte !';
                promoMessage.className = 'small mt-1 text-success';
            } else if (typeof discount === 'number') {
                if (promoCode === 'AADELICE10') {
                    // 10% de réduction
                    const subtotal = this.getSubtotal();
                    this.discount = subtotal * 0.1;
                } else {
                    // Réduction fixe
                    this.discount = discount;
                }
                promoMessage.textContent = `Code appliqué : ${discount}${promoCode === 'AADELICE10' ? '%' : '€'} de réduction !`;
                promoMessage.className = 'small mt-1 text-success';
            }
            
            this.updateSummary(this.getSubtotal());
        } else {
            promoMessage.textContent = 'Code promo invalide ou expiré';
            promoMessage.className = 'small mt-1 text-danger';
        }
    }
    
    async processCheckout() {
        // Validation du formulaire
        const requiredFields = ['firstName', 'lastName', 'email', 'address', 'zipCode', 'city'];
        let isValid = true;
        
        requiredFields.forEach(field => {
            const input = document.getElementById(field);
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            this.showNotification('Veuillez remplir tous les champs obligatoires', 'error');
            return;
        }
        
        // Préparer les données de commande
        const orderData = {
            cart: this.cart,
            shipping: this.selectedShipping,
            shippingCost: this.shippingCost,
            discount: this.discount,
            subtotal: this.getSubtotal(),
            total: this.getSubtotal() + this.shippingCost - this.discount,
            customer: {
                firstName: document.getElementById('firstName').value,
                lastName: document.getElementById('lastName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                address: document.getElementById('address').value,
                zipCode: document.getElementById('zipCode').value,
                city: document.getElementById('city').value,
                country: document.getElementById('country').value,
                instructions: document.getElementById('deliveryInstructions').value
            },
            date: new Date().toISOString(),
            orderId: 'CMD-' + Date.now()
        };
        
        // Sauvegarder la commande dans localStorage (simulation)
        const orders = JSON.parse(localStorage.getItem('aadeliceOrders')) || [];
        orders.push(orderData);
        localStorage.setItem('aadeliceOrders', JSON.stringify(orders));
        
        // Sauvegarder l'adresse si demandé
        if (document.getElementById('saveAddress').checked) {
            localStorage.setItem('aadeliceCustomerAddress', JSON.stringify(orderData.customer));
        }
        
        // Vider le panier
        this.cart = [];
        this.saveCart();
        
        // Afficher la confirmation
        this.showOrderConfirmation(orderData);
    }
    
    showOrderConfirmation(orderData) {
        // Créer une modal de confirmation
        const modalHtml = `
            <div class="modal fade" id="orderConfirmationModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-check-circle me-2"></i>
                                Commande confirmée !
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <h4>Merci pour votre commande !</h4>
                                <p class="text-muted">Votre commande a été enregistrée avec succès.</p>
                            </div>
                            
                            <div class="order-summary p-3 bg-light rounded">
                                <h6>Récapitulatif de la commande :</h6>
                                <div class="d-flex justify-content-between">
                                    <span>N° de commande :</span>
                                    <strong>${orderData.orderId}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total :</span>
                                    <strong>${orderData.total.toFixed(2).replace('.', ',')} €</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Mode de livraison :</span>
                                    <strong>${this.getShippingLabel(orderData.shipping)}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Date :</span>
                                    <strong>${new Date(orderData.date).toLocaleDateString('fr-FR')}</strong>
                                </div>
                            </div>
                            
                            <p class="mt-3">
                                Un email de confirmation a été envoyé à <strong>${orderData.customer.email}</strong>
                            </p>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Votre commande sera expédiée sous 24h ouvrées.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                            <a href="index.html" class="btn btn-primary-custom">
                                Retour à la boutique
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Ajouter la modal au DOM
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHtml;
        document.body.appendChild(modalContainer);
        
        // Afficher la modal
        const modal = new bootstrap.Modal(document.getElementById('orderConfirmationModal'));
        modal.show();
        
        // Nettoyer après fermeture
        document.getElementById('orderConfirmationModal').addEventListener('hidden.bs.modal', () => {
            modalContainer.remove();
            window.location.href = 'index.html';
        });
    }
    
    getShippingLabel(type) {
        const labels = {
            'standard': 'Livraison Standard',
            'express': 'Livraison Express',
            'point-relais': 'Point Relais'
        };
        return labels[type] || 'Standard';
    }
    
    saveCart() {
        localStorage.setItem('aadeliceCart', JSON.stringify(this.cart));
        this.updateCartCount();
    }
    
    updateCartCount() {
        const totalItems = this.cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartCountElements = document.querySelectorAll('.cart-count');
        
        cartCountElements.forEach(element => {
            element.textContent = totalItems;
        });
    }
    
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `position-fixed top-0 end-0 m-3 p-3 rounded-3 shadow`;
        notification.style.zIndex = '1060';
        
        if (type === 'success') {
            notification.classList.add('bg-success', 'text-white');
            notification.innerHTML = `<i class="fas fa-check-circle me-2"></i>${message}`;
        } else {
            notification.classList.add('bg-danger', 'text-white');
            notification.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${message}`;
        }
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}

// Initialiser la page panier
document.addEventListener('DOMContentLoaded', () => {
    window.cartPage = new CartPage();
});