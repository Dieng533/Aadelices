// auth.js - Gestion de l'authentification admin pour Aadelice Dakar
class AuthManager {
    constructor() {
        this.maxAttempts = 5;
        this.lockoutTime = 15 * 60 * 1000; // 15 minutes
        this.init();
    }
    
    init() {
        // Initialiser le compteur de tentatives
        if (!localStorage.getItem('failedLoginAttempts')) {
            localStorage.setItem('failedLoginAttempts', '0');
        }
        
        if (!localStorage.getItem('lockoutUntil')) {
            localStorage.setItem('lockoutUntil', '0');
        }
    }
    
    isLockedOut() {
        const lockoutUntil = parseInt(localStorage.getItem('lockoutUntil'));
        const now = new Date().getTime();
        
        return lockoutUntil > now;
    }
    
    getRemainingLockoutTime() {
        const lockoutUntil = parseInt(localStorage.getItem('lockoutUntil'));
        const now = new Date().getTime();
        
        if (lockoutUntil > now) {
            return Math.ceil((lockoutUntil - now) / (60 * 1000)); // En minutes
        }
        
        return 0;
    }
    
    incrementFailedAttempts() {
        let attempts = parseInt(localStorage.getItem('failedLoginAttempts'));
        attempts++;
        localStorage.setItem('failedLoginAttempts', attempts.toString());
        
        // Si atteint le maximum, verrouiller
        if (attempts >= this.maxAttempts) {
            const lockoutUntil = new Date().getTime() + this.lockoutTime;
            localStorage.setItem('lockoutUntil', lockoutUntil.toString());
            
            // Réinitialiser le compteur après le verrouillage
            setTimeout(() => {
                localStorage.setItem('failedLoginAttempts', '0');
            }, this.lockoutTime);
        }
    }
    
    resetFailedAttempts() {
        localStorage.setItem('failedLoginAttempts', '0');
        localStorage.setItem('lockoutUntil', '0');
    }
    
    // Méthode d'instance pour vérifier l'authentification
    isAuthenticated() {
        const auth = localStorage.getItem('aadeliceAdminAuth');
        if (!auth) return false;
        
        try {
            const authData = JSON.parse(auth);
            const now = new Date().getTime();
            
            // Vérifier l'expiration (8 heures)
            if (now - authData.timestamp > 8 * 60 * 60 * 1000) {
                localStorage.removeItem('aadeliceAdminAuth');
                return false;
            }
            
            return true;
        } catch (error) {
            console.error('Erreur vérification auth:', error);
            return false;
        }
    }
    
    login(username, password) {
        // Vérifier si verrouillé
        if (this.isLockedOut()) {
            const minutesLeft = this.getRemainingLockoutTime();
            return {
                success: false,
                message: `Compte verrouillé. Réessayez dans ${minutesLeft} minutes.`
            };
        }
        
        // Récupérer les identifiants depuis les paramètres
        const settings = JSON.parse(localStorage.getItem('aadeliceSettings')) || {
            adminUsername: "aadelice_admin",
            adminPassword: "dakar_2023" // Mot de passe par défaut
        };
        
        console.log('Tentative de connexion avec:', { 
            username, 
            expectedUser: settings.adminUsername 
        });
        
        // Vérifier les identifiants
        if (username === settings.adminUsername && password === settings.adminPassword) {
            // Authentification réussie
            const authData = {
                username: username,
                timestamp: new Date().getTime(),
                ip: this.getClientIP() // Simulation d'IP
            };
            
            localStorage.setItem('aadeliceAdminAuth', JSON.stringify(authData));
            
            // Réinitialiser les tentatives échouées
            this.resetFailedAttempts();
            
            // Journaliser la connexion
            this.logLogin(username, true);
            
            return {
                success: true,
                message: "Connexion réussie !"
            };
        } else {
            // Échec d'authentification
            this.incrementFailedAttempts();
            
            // Journaliser l'échec
            this.logLogin(username, false);
            
            const attemptsLeft = this.maxAttempts - parseInt(localStorage.getItem('failedLoginAttempts'));
            
            if (attemptsLeft <= 0) {
                const minutesLeft = this.getRemainingLockoutTime();
                return {
                    success: false,
                    message: `Trop de tentatives. Compte verrouillé pour ${minutesLeft} minutes.`
                };
            }
            
            return {
                success: false,
                message: `Identifiants incorrects. ${attemptsLeft} tentative(s) restante(s).`
            };
        }
    }
    
    logout() {
        // Journaliser la déconnexion
        const auth = localStorage.getItem('aadeliceAdminAuth');
        if (auth) {
            try {
                const authData = JSON.parse(auth);
                console.log(`Déconnexion: ${authData.username} à ${new Date().toLocaleString()}`);
            } catch (error) {
                console.error('Erreur journalisation déconnexion:', error);
            }
        }
        
        localStorage.removeItem('aadeliceAdminAuth');
    }
    
    getClientIP() {
        // Simulation d'adresse IP
        return '127.0.0.1';
    }
    
    logLogin(username, success) {
        const logs = JSON.parse(localStorage.getItem('loginLogs')) || [];
        
        logs.push({
            username: username,
            success: success,
            timestamp: new Date().getTime(),
            ip: this.getClientIP(),
            userAgent: navigator.userAgent
        });
        
        // Garder seulement les 100 derniers logs
        if (logs.length > 100) {
            logs.splice(0, logs.length - 100);
        }
        
        localStorage.setItem('loginLogs', JSON.stringify(logs));
    }
    
    getLoginLogs() {
        return JSON.parse(localStorage.getItem('loginLogs')) || [];
    }
    
    getCurrentAdmin() {
        const auth = localStorage.getItem('aadeliceAdminAuth');
        if (!auth) return null;
        
        try {
            return JSON.parse(auth);
        } catch (error) {
            return null;
        }
    }
    
    static changePassword(newPassword) {
        // Récupérer les paramètres actuels
        const settings = JSON.parse(localStorage.getItem('aadeliceSettings')) || {
            adminUsername: "aadelice_admin",
            adminPassword: "dakar_2023"
        };
        
        // Mettre à jour le mot de passe
        settings.adminPassword = newPassword;
        localStorage.setItem('aadeliceSettings', JSON.stringify(settings));
        
        // Déconnecter tous les utilisateurs
        localStorage.removeItem('aadeliceAdminAuth');
        
        return true;
    }
}

// Initialiser et exposer l'AuthManager
window.AuthManager = new AuthManager();