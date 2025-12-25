// admin.js - Gestion de l'interface administrateur
// admin.js - Gestion de l'interface administrateur
class AdminManagerDakar {
    constructor() {
        // Vérifier l'authentification
        if (!AuthManager.isAuthenticated()) {
            window.location.href = '../index.html';
            return;
        }
        
        this.currentPage = 'dashboard';
        this.currentProductId = null;
        this.currentOrderId = null;
        this.productsPerPage = 10;
        this.ordersPerPage = 10;
        this.customersPerPage = 10;
        this.currentProductsPage = 1;
        this.currentOrdersPage = 1;
        this.currentCustomersPage = 1;
        
        this.init();
    }
    
    init() {
        // Mettre à jour l'interface avec les infos admin
        this.updateAdminInfo();
        
        // Initialiser les événements
        this.initSidebar();
        this.initNavigation();
        this.initProductForm();
        this.initOrderHandlers();
        this.initSettings();
        this.initWhatsApp();
        
        // Charger les données
        this.loadProducts();
        this.loadOrders();
        this.loadCustomers();
        
        // Afficher le tableau de bord
        this.showSection('dashboard');
        
        // Journaliser l'accès
        this.logAccess();
    }
    
    updateAdminInfo() {
        const admin = AuthManager.getCurrentAdmin();
        if (admin) {
            // Mettre à jour l'affichage admin
            const avatar = document.querySelector('.admin-avatar span');
            if (avatar) {
                avatar.textContent = admin.username.substring(0, 2).toUpperCase();
            }
            
            const adminName = document.querySelector('.admin-user h6');
            if (adminName) {
                adminName.textContent = admin.username;
            }
        }
    }
    
    logAccess() {
        const admin = AuthManager.getCurrentAdmin();
        if (admin) {
            console.log(`Admin ${admin.username} a accédé à l'interface à ${new Date().toLocaleString()}`);
            
            // Enregistrer l'accès dans les logs
            const accessLogs = JSON.parse(localStorage.getItem('adminAccessLogs')) || [];
            accessLogs.push({
                username: admin.username,
                timestamp: new Date().getTime(),
                page: this.currentPage
            });
            
            // Garder seulement les 50 derniers logs
            if (accessLogs.length > 50) {
                accessLogs.splice(0, accessLogs.length - 50);
            }
            
            localStorage.setItem('adminAccessLogs', JSON.stringify(accessLogs));
        }
    }
    
    // ... reste du code inchangé ...
    
    // Dans la méthode initSidebar(), mettre à jour le logout
    initSidebar() {
        // ... code existant ...
        
        // Logout
        document.getElementById('logoutBtn').addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                AuthManager.logout();
                window.location.href = '../index.html';
            }
        });
    }
}
class AdminManager {
    constructor() {
        this.currentPage = 'dashboard';
        this.currentProductId = null;
        this.productsPerPage = 10;
        this.currentPageNumber = 1;
        
        this.init();
    }
    
    init() {
        // Initialiser les événements
        this.initSidebar();
        this.initNavigation();
        this.initProductForm();
        this.loadProducts();
        this.loadCategories();
        
        // Afficher le tableau de bord par défaut
        this.showSection('dashboard');
    }
    
    initSidebar() {
        // Toggle sidebar
        document.getElementById('toggleSidebar').addEventListener('click', () => {
            document.getElementById('adminSidebar').classList.toggle('collapsed');
            document.getElementById('adminMain').classList.toggle('collapsed');
        });
        
        // Navigation sidebar
        document.querySelectorAll('.admin-menu-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                
                const target = item.getAttribute('href').substring(1);
                if (target !== this.currentPage) {
                    this.showSection(target);
                    
                    // Mettre à jour l'état actif
                    document.querySelectorAll('.admin-menu-item').forEach(i => {
                        i.classList.remove('active');
                    });
                    item.classList.add('active');
                    
                    this.currentPage = target;
                }
            });
        });
        
        // Logout
        document.getElementById('logoutBtn').addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                window.location.href = '../index.html';
            }
        });
    }
    
    initNavigation() {
        // Retour à la liste des produits
        document.getElementById('backToListBtn').addEventListener('click', () => {
            this.showProductList();
        });
        
        // Annuler l'ajout/modification
        document.getElementById('cancelProductBtn').addEventListener('click', () => {
            if (confirm('Les modifications non enregistrées seront perdues. Continuer ?')) {
                this.showProductList();
            }
        });
        
        // Ajouter un produit
        document.getElementById('addProductBtn').addEventListener('click', () => {
            this.showProductForm();
        });
    }
    
    initProductForm() {
        const form = document.getElementById('productForm');
        const imageUpload = document.getElementById('imageUploadArea');
        const fileInput = document.getElementById('productImageFile');
        const imagePreview = document.getElementById('productImagePreview');
        const addTagBtn = document.getElementById('addTagBtn');
        const newTagInput = document.getElementById('newTagInput');
        const tagsContainer = document.getElementById('tagsContainer');
        
        // Upload d'image
        imageUpload.addEventListener('click', () => {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    alert('Le fichier est trop volumineux (max 2MB)');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = (event) => {
                    imagePreview.src = event.target.result;
                    imagePreview.classList.remove('d-none');
                    document.getElementById('productImageUrl').value = '';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // URL d'image
        document.getElementById('productImageUrl').addEventListener('input', (e) => {
            if (e.target.value) {
                imagePreview.src = e.target.value;
                imagePreview.classList.remove('d-none');
                fileInput.value = '';
            }
        });
        
        // Gestion des tags
        addTagBtn.addEventListener('click', () => {
            const tag = newTagInput.value.trim();
            if (tag) {
                this.addTagToForm(tag);
                newTagInput.value = '';
            }
        });
        
        newTagInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTagBtn.click();
            }
        });
        
        // Soumission du formulaire
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveProduct();
        });
    }
    
    addTagToForm(tag) {
        const tagsContainer = document.getElementById('tagsContainer');
        const tagId = `tag-${Date.now()}`;
        
        const tagElement = document.createElement('div');
        tagElement.className = 'tag-item';
        tagElement.innerHTML = `
            ${tag}
            <span class="remove-tag" data-tag="${tagId}">
                <i class="fas fa-times"></i>
            </span>
            <input type="hidden" name="tags[]" value="${tag}">
        `;
        
        tagsContainer.appendChild(tagElement);
        
        // Supprimer le tag
        tagElement.querySelector('.remove-tag').addEventListener('click', () => {
            tagElement.remove();
        });
    }
    
    showSection(sectionId) {
        // Masquer toutes les sections
        document.querySelectorAll('.admin-section').forEach(section => {
            section.classList.add('d-none');
        });
        
        // Afficher la section demandée
        const section = document.getElementById(sectionId);
        if (section) {
            section.classList.remove('d-none');
            
            // Mettre à jour le titre du header
            if (sectionId !== 'productFormSection') {
                const headerTitle = document.querySelector('.admin-header h1');
                const sectionTitle = section.querySelector('h2, h3');
                if (sectionTitle) {
                    headerTitle.textContent = sectionTitle.textContent;
                }
            }
        }
    }
    
    showProductList() {
        this.currentProductId = null;
        this.showSection('products');
        document.querySelector('.admin-header h1').textContent = 'Gestion des produits';
    }
    
    showProductForm(productId = null) {
        const form = document.getElementById('productForm');
        const formTitle = document.getElementById('productFormTitle');
        const tagsContainer = document.getElementById('tagsContainer');
        const imagePreview = document.getElementById('productImagePreview');
        
        // Réinitialiser le formulaire
        form.reset();
        tagsContainer.innerHTML = '';
        imagePreview.classList.add('d-none');
        document.getElementById('productId').value = '';
        
        if (productId) {
            // Mode édition
            formTitle.textContent = 'Modifier le produit';
            
            // Charger les données du produit
            const products = JSON.parse(localStorage.getItem('aadeliceProducts')) || { products: [] };
            const product = products.products.find(p => p.id == productId);
            
            if (product) {
                document.getElementById('productId').value = product.id;
                document.getElementById('productName').value = product.name;
                document.getElementById('productCategory').value = product.category;
                document.getElementById('productPrice').value = product.price;
                document.getElementById('productStock').value = product.stock;
                document.getElementById('productDescription').value = product.description || '';
                document.getElementById('productImageUrl').value = product.image || '';
                
                if (product.image) {
                    imagePreview.src = product.image;
                    imagePreview.classList.remove('d-none');
                }
                
                // Ajouter les tags
                if (product.tags && Array.isArray(product.tags)) {
                    product.tags.forEach(tag => {
                        this.addTagToForm(tag);
                    });
                }
            }
        } else {
            // Mode création
            formTitle.textContent = 'Nouveau produit';
        }
        
        this.showSection('productFormSection');
        document.querySelector('.admin-header h1').textContent = formTitle.textContent;
    }
    
    async loadProducts() {
        try {
            // Charger depuis le fichier JSON
            const response = await fetch('data/products.json');
            const data = await response.json();
            
            // Stocker dans localStorage pour la simulation
            localStorage.setItem('aadeliceProducts', JSON.stringify(data));
            
            // Afficher les produits dans le tableau
            this.displayProducts(data.products);
            
            // Afficher les produits récents dans le dashboard
            this.displayRecentProducts(data.products.slice(0, 5));
        } catch (error) {
            console.error('Erreur lors du chargement des produits:', error);
            
            // Charger depuis localStorage en cas d'erreur
            const localData = JSON.parse(localStorage.getItem('aadeliceProducts'));
            if (localData) {
                this.displayProducts(localData.products);
                this.displayRecentProducts(localData.products.slice(0, 5));
            }
        }
    }
    
    async loadCategories() {
        try {
            const response = await fetch('data/products.json');
            const data = await response.json();
            const select = document.getElementById('productCategory');
            
            select.innerHTML = '<option value="">Sélectionner une catégorie</option>';
            
            data.categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.name;
                option.textContent = category.name;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Erreur lors du chargement des catégories:', error);
        }
    }
    
    displayProducts(products) {
        const tableBody = document.getElementById('productsTableBody');
        const pagination = document.getElementById('productsPagination');
        
        if (!tableBody) return;
        
        // Calculer la pagination
        const totalPages = Math.ceil(products.length / this.productsPerPage);
        const startIndex = (this.currentPageNumber - 1) * this.productsPerPage;
        const endIndex = startIndex + this.productsPerPage;
        const pageProducts = products.slice(startIndex, endIndex);
        
        // Afficher les produits
        tableBody.innerHTML = '';
        
        pageProducts.forEach(product => {
            const row = document.createElement('tr');
            
            // Déterminer le statut du stock
            let stockStatus = 'status-active';
            let stockText = 'En stock';
            
            if (product.stock === 0) {
                stockStatus = 'status-inactive';
                stockText = 'Rupture';
            } else if (product.stock < 10) {
                stockStatus = 'status-low';
                stockText = 'Stock faible';
            }
            
            row.innerHTML = `
                <td>#${product.id.toString().padStart(3, '0')}</td>
                <td>
                    <img src="${product.image}" alt="${product.name}" class="product-thumbnail">
                </td>
                <td>${product.name}</td>
                <td>
                    <span class="badge-custom badge-secondary-custom">
                        ${product.category}
                    </span>
                </td>
                <td>${product.price.toFixed(2)} €</td>
                <td>${product.stock}</td>
                <td>
                    <span class="status-badge ${stockStatus}">${stockText}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-action btn-edit edit-product" data-id="${product.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete delete-product" data-id="${product.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                        <a href="../product.html?id=${product.id}" class="btn-action btn-view" target="_blank">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
        
        // Générer la pagination
        this.generatePagination(pagination, totalPages);
        
        // Ajouter les événements
        this.addProductEvents();
    }
    
    displayRecentProducts(products) {
        const container = document.getElementById('recentProductsTable');
        if (!container) return;
        
        let html = `
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prix</th>
                        <th>Stock</th>
                        <th>Date d'ajout</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        products.forEach(product => {
            html += `
                <tr>
                    <td>${product.name}</td>
                    <td>${product.price.toFixed(2)} €</td>
                    <td>${product.stock}</td>
                    <td>${new Date().toLocaleDateString('fr-FR')}</td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
        `;
        
        container.innerHTML = html;
    }
    
    generatePagination(container, totalPages) {
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '';
        
        // Bouton précédent
        if (this.currentPageNumber > 1) {
            html += `<a href="#" class="page-link prev-page" data-page="${this.currentPageNumber - 1}">
                <i class="fas fa-chevron-left"></i>
            </a>`;
        }
        
        // Pages
        for (let i = 1; i <= totalPages; i++) {
            if (i === this.currentPageNumber) {
                html += `<a href="#" class="page-link active" data-page="${i}">${i}</a>`;
            } else if (
                i === 1 ||
                i === totalPages ||
                (i >= this.currentPageNumber - 1 && i <= this.currentPageNumber + 1)
            ) {
                html += `<a href="#" class="page-link" data-page="${i}">${i}</a>`;
            } else if (i === this.currentPageNumber - 2 || i === this.currentPageNumber + 2) {
                html += `<span class="page-link disabled">...</span>`;
            }
        }
        
        // Bouton suivant
        if (this.currentPageNumber < totalPages) {
            html += `<a href="#" class="page-link next-page" data-page="${this.currentPageNumber + 1}">
                <i class="fas fa-chevron-right"></i>
            </a>`;
        }
        
        container.innerHTML = html;
        
        // Ajouter les événements de pagination
        container.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.getAttribute('data-page'));
                if (page && page !== this.currentPageNumber) {
                    this.currentPageNumber = page;
                    this.loadProducts();
                }
            });
        });
    }
    
    addProductEvents() {
        // Édition
        document.querySelectorAll('.edit-product').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const productId = e.currentTarget.getAttribute('data-id');
                this.showProductForm(productId);
            });
        });
        
        // Suppression
        document.querySelectorAll('.delete-product').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const productId = e.currentTarget.getAttribute('data-id');
                this.deleteProduct(productId);
            });
        });
    }
    
    async saveProduct() {
        // Récupérer les données du formulaire
        const productId = document.getElementById('productId').value;
        const name = document.getElementById('productName').value;
        const category = document.getElementById('productCategory').value;
        const price = parseFloat(document.getElementById('productPrice').value);
        const stock = parseInt(document.getElementById('productStock').value);
        const description = document.getElementById('productDescription').value;
        const imageUrl = document.getElementById('productImageUrl').value;
        const fileInput = document.getElementById('productImageFile');
        
        // Récupérer les tags
        const tags = [];
        document.querySelectorAll('input[name="tags[]"]').forEach(input => {
            tags.push(input.value);
        });
        
        // Validation
        if (!name || !category || !price || !stock) {
            alert('Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        try {
            // Charger les produits existants
            const data = JSON.parse(localStorage.getItem('aadeliceProducts')) || { products: [] };
            
            let productData;
            
            if (productId) {
                // Mettre à jour un produit existant
                const index = data.products.findIndex(p => p.id == productId);
                if (index !== -1) {
                    productData = {
                        ...data.products[index],
                        name,
                        category,
                        price,
                        stock,
                        description,
                        tags
                    };
                    
                    // Mettre à jour l'image si une nouvelle a été fournie
                    if (fileInput.files.length > 0) {
                        // En production, vous uploaderiez le fichier sur un serveur
                        productData.image = URL.createObjectURL(fileInput.files[0]);
                    } else if (imageUrl) {
                        productData.image = imageUrl;
                    }
                    
                    data.products[index] = productData;
                }
            } else {
                // Créer un nouveau produit
                productData = {
                    id: data.products.length > 0 ? 
                        Math.max(...data.products.map(p => p.id)) + 1 : 1,
                    name,
                    category,
                    price,
                    stock,
                    description,
                    tags,
                    image: imageUrl || 'https://images.unsplash.com/photo-1582058091505-f87a2e55a40f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80',
                    rating: 4.0,
                    reviews: 0
                };
                
                data.products.push(productData);
            }
            
            // Sauvegarder
            localStorage.setItem('aadeliceProducts', JSON.stringify(data));
            
            // Afficher un message de succès
            alert(productId ? 'Produit mis à jour avec succès!' : 'Produit ajouté avec succès!');
            
            // Retourner à la liste
            this.showProductList();
            this.loadProducts();
            
        } catch (error) {
            console.error('Erreur lors de la sauvegarde:', error);
            alert('Une erreur est survenue lors de la sauvegarde');
        }
    }
    
    async deleteProduct(productId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
            return;
        }
        
        try {
            const data = JSON.parse(localStorage.getItem('aadeliceProducts')) || { products: [] };
            const index = data.products.findIndex(p => p.id == productId);
            
            if (index !== -1) {
                data.products.splice(index, 1);
                localStorage.setItem('aadeliceProducts', JSON.stringify(data));
                
                // Recharger la liste
                this.loadProducts();
                alert('Produit supprimé avec succès!');
            }
        } catch (error) {
            console.error('Erreur lors de la suppression:', error);
            alert('Une erreur est survenue lors de la suppression');
        }
    }
}

// Initialiser l'admin lorsque la page est chargée
document.addEventListener('DOMContentLoaded', () => {
    window.adminManager = new AdminManager();
});