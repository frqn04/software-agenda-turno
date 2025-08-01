const API_BASE = 'http://localhost:8000/api';

// Inicializar SecurityManager
let securityManager;

document.addEventListener('alpine:init', () => {
    // Inicializar seguridad
    securityManager = new SecurityManager();
    
    Alpine.store('auth', {
        token: localStorage.getItem('authToken'),
        user: JSON.parse(localStorage.getItem('currentUser') || '{}'),
        
        setAuth(token, user) {
            this.token = token;
            this.user = user;
            localStorage.setItem('authToken', token);
            localStorage.setItem('currentUser', JSON.stringify(user));
            // Configurar token en SecurityManager
            securityManager.setToken(token);
        },
        
        clearAuth() {
            this.token = null;
            this.user = {};
            localStorage.removeItem('authToken');
            localStorage.removeItem('currentUser');
            // Limpiar token en SecurityManager
            securityManager.clearToken();
        }
    });

    Alpine.store('modals', {
        currentView: 'agenda',
        showPatientModal: false,
        showDoctorModal: false,
        showAppointmentModal: false,
        editingItem: null
    });

    Alpine.store('calendar', {
        selectedDate: new Date().toISOString().split('T')[0],
        selectedDoctor: '',
        selectedSlot: null
    });
});

function app() {
    return {
        loginForm: {
            email: '',
            password: ''
        },
        
        init() {
            // Configurar SecurityManager si hay token
            if (this.$store.auth.token) {
                securityManager.setToken(this.$store.auth.token);
            }
            
            if (this.$store.auth.token && this.$store.auth.user.id) {
                this.$store.modals.currentView = 'agenda';
            }
        },
        
        async login() {
            try {
                // Sanitizar datos de entrada
                const sanitizedForm = {
                    email: securityManager.sanitizeInput(this.loginForm.email),
                    password: this.loginForm.password // Las contraseñas no se sanitizan
                };
                
                const response = await securityManager.secureRequest('/login', {
                    method: 'POST',
                    body: JSON.stringify(sanitizedForm)
                });
                
                if (response.success) {
                    this.$store.auth.setAuth(response.token, response.user);
                    this.$store.modals.currentView = 'agenda';
                    showAlert('Login exitoso', 'success');
                } else {
                    showAlert(response.message || 'Error en el login', 'error');
                }
            } catch (error) {
                showAlert(error.message || 'Error de conexión', 'error');
            }
        },
        
        async logout() {
            try {
                await securityManager.secureRequest('/logout', {
                    method: 'POST'
                });
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                this.$store.auth.clearAuth();
                location.reload();
            }
        }
    };
}

function showAlert(message, type = 'success') {
    Swal.fire({
        icon: type,
        title: type === 'success' ? 'Éxito' : 'Error',
        text: message,
        timer: 3000,
        showConfirmButton: false
    });
}
