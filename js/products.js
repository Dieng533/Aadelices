// products.js - Gestion des produits (partagé entre frontend et admin)

class ProductManager {
    constructor() {
        this.products = [];
        this.categories = [];
    }
    
    async loadProducts() {
        try {
            const response = await fetch('data/products.json');
            const data = await response.json();
            this.products = data.products || [];
            this.categories = data.categories || [];
            
            // Sauvegarder dans localStorage pour la simulation
            localStorage.setItem('aadeliceProducts', JSON.stringify(data));
            
            return data;
        } catch (error) {
            console.error('Erreur lors du chargement des produits:', error);
            
            // Essayer de charger depuis localStorage
            const localData = JSON.parse(localStorage.getItem('aadeliceProducts'));
            if (localData) {
                this.products = localData.products || [];
                this.categories = localData.categories || [];
                return localData;
            }
            
            return { products: [], categories: [] };
        }
    }
    
    async getProducts() {
        if (this.products.length === 0) {
            await this.loadProducts();
        }
        return this.products;
    }
    
    async getProductById(id) {
        await this.getProducts();
        return this.products.find(product => product.id == id);
    }
    
    async getProductsByCategory(category) {
        await this.getProducts();
        return this.products.filter(product => product.category === category);
    }
    
    async getCategories() {
        if (this.categories.length === 0) {
            await this.loadProducts();
        }
        return this.categories;
    }
    
    async addProduct(productData) {
        const data = JSON.parse(localStorage.getItem('aadeliceProducts')) || { products: [], categories: [] };
        
        // Générer un nouvel ID
        const newId = data.products.length > 0 ? 
            Math.max(...data.products.map(p => p.id)) + 1 : 1;
        
        const newProduct = {
            id: newId,
            ...productData,
            rating: 4.0,
            reviews: 0
        };
        
        data.products.push(newProduct);
        localStorage.setItem('aadeliceProducts', JSON.stringify(data));
        
        // Mettre à jour le cache
        this.products = data.products;
        
        return newProduct;
    }
    
    async updateProduct(id, productData) {
        const data = JSON.parse(localStorage.getItem('aadeliceProducts')) || { products: [], categories: [] };
        const index = data.products.findIndex(p => p.id == id);
        
        if (index !== -1) {
            data.products[index] = {
                ...data.products[index],
                ...productData
            };
            
            localStorage.setItem('aadeliceProducts', JSON.stringify(data));
            this.products = data.products;
            
            return data.products[index];
        }
        
        return null;
    }
    
    async deleteProduct(id) {
        const data = JSON.parse(localStorage.getItem('aadeliceProducts')) || { products: [], categories: [] };
        const index = data.products.findIndex(p => p.id == id);
        
        if (index !== -1) {
            const deletedProduct = data.products.splice(index, 1)[0];
            localStorage.setItem('aadeliceProducts', JSON.stringify(data));
            this.products = data.products;
            
            return deletedProduct;
        }
        
        return null;
    }
    
    async searchProducts(query) {
        await this.getProducts();
        const searchLower = query.toLowerCase();
        
        return this.products.filter(product => 
            product.name.toLowerCase().includes(searchLower) ||
            product.description.toLowerCase().includes(searchLower) ||
            product.tags.some(tag => tag.toLowerCase().includes(searchLower))
        );
    }
}

// Exporter pour utilisation globale
window.ProductManager = ProductManager;