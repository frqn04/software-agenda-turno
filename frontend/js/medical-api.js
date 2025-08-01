/**
 * Medical Appointment System - Frontend API Client
 * Optimized for Enterprise Use with Alpine.js
 */

class MedicalAppointmentAPI {
    constructor(baseURL = '/api/v1') {
        this.baseURL = baseURL;
        this.token = localStorage.getItem('auth_token');
        this.refreshToken = localStorage.getItem('refresh_token');
    }

    // Configurar headers para requests autenticados
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': this.token ? `Bearer ${this.token}` : '',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    // Manejar responses y errores
    async handleResponse(response) {
        const data = await response.json();
        
        if (!response.ok) {
            if (response.status === 401) {
                this.logout();
                throw new Error('Sesión expirada');
            }
            throw new Error(data.message || 'Error en la solicitud');
        }
        
        return data;
    }

    // ========== AUTENTICACIÓN ==========
    async login(email, password) {
        const response = await fetch(`${this.baseURL}/auth/login`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({ email, password })
        });
        
        const data = await this.handleResponse(response);
        
        if (data.success) {
            this.token = data.data.token;
            localStorage.setItem('auth_token', this.token);
            localStorage.setItem('user', JSON.stringify(data.data.user));
        }
        
        return data;
    }

    async logout() {
        try {
            await fetch(`${this.baseURL}/auth/logout`, {
                method: 'POST',
                headers: this.getHeaders()
            });
        } catch (error) {
            console.warn('Error en logout:', error);
        } finally {
            this.token = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }
    }

    async getUser() {
        const response = await fetch(`${this.baseURL}/auth/user`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    // ========== TURNOS ==========
    async getTurnos(filters = {}) {
        const params = new URLSearchParams(filters);
        const response = await fetch(`${this.baseURL}/turnos?${params}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getTurno(id) {
        const response = await fetch(`${this.baseURL}/turnos/${id}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async createTurno(turnoData) {
        const response = await fetch(`${this.baseURL}/turnos`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify(turnoData)
        });
        return await this.handleResponse(response);
    }

    async updateTurno(id, turnoData) {
        const response = await fetch(`${this.baseURL}/turnos/${id}`, {
            method: 'PUT',
            headers: this.getHeaders(),
            body: JSON.stringify(turnoData)
        });
        return await this.handleResponse(response);
    }

    async deleteTurno(id) {
        const response = await fetch(`${this.baseURL}/turnos/${id}`, {
            method: 'DELETE',
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async confirmTurno(id) {
        const response = await fetch(`${this.baseURL}/turnos/${id}/confirm`, {
            method: 'PATCH',
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async cancelTurno(id, motivo = null) {
        const response = await fetch(`${this.baseURL}/turnos/${id}/cancel`, {
            method: 'PATCH',
            headers: this.getHeaders(),
            body: JSON.stringify({ motivo })
        });
        return await this.handleResponse(response);
    }

    async completeTurno(id, observaciones = null) {
        const response = await fetch(`${this.baseURL}/turnos/${id}/complete`, {
            method: 'PATCH',
            headers: this.getHeaders(),
            body: JSON.stringify({ observaciones })
        });
        return await this.handleResponse(response);
    }

    async getAvailableSlots(doctorId, fecha) {
        const response = await fetch(`${this.baseURL}/doctores/${doctorId}/horarios-disponibles/${fecha}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    // ========== NUEVAS FUNCIONALIDADES ==========
    
    // Cambiar contraseña
    async changePassword(currentPassword, newPassword, confirmPassword) {
        const response = await fetch(`${this.baseURL}/auth/change-password`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword,
                new_password_confirmation: confirmPassword
            })
        });
        return await this.handleResponse(response);
    }

    // Obtener estadísticas del dashboard
    async getDashboardStats() {
        const response = await fetch(`${this.baseURL}/admin/system-stats`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    // Obtener logs de auditoría
    async getAuditLogs(filters = {}) {
        const params = new URLSearchParams();
        Object.keys(filters).forEach(key => {
            if (filters[key]) params.append(key, filters[key]);
        });
        
        const response = await fetch(`${this.baseURL}/admin/audit-logs?${params}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    // Cache management
    async clearCache() {
        const response = await fetch(`${this.baseURL}/cache/clear`, {
            method: 'POST',
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getCacheStats() {
        const response = await fetch(`${this.baseURL}/cache/stats`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    // ========== DOCTORES ==========
    async getDoctores(filters = {}) {
        const params = new URLSearchParams(filters);
        const response = await fetch(`${this.baseURL}/doctores?${params}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getActiveDoctores() {
        const response = await fetch(`${this.baseURL}/doctores/active`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getDoctor(id) {
        const response = await fetch(`${this.baseURL}/doctores/${id}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async createDoctor(doctorData) {
        const response = await fetch(`${this.baseURL}/doctores`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify(doctorData)
        });
        return await this.handleResponse(response);
    }

    async updateDoctor(id, doctorData) {
        const response = await fetch(`${this.baseURL}/doctores/${id}`, {
            method: 'PUT',
            headers: this.getHeaders(),
            body: JSON.stringify(doctorData)
        });
        return await this.handleResponse(response);
    }

    async getDoctoresByEspecialidad(especialidadId) {
        const response = await fetch(`${this.baseURL}/doctores/especialidad/${especialidadId}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    // ========== PACIENTES ==========
    async getPacientes(filters = {}) {
        const params = new URLSearchParams(filters);
        const response = await fetch(`${this.baseURL}/pacientes?${params}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getActivePacientes() {
        const response = await fetch(`${this.baseURL}/pacientes/active`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getPaciente(id) {
        const response = await fetch(`${this.baseURL}/pacientes/${id}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getPacienteWithTurnos(id) {
        const response = await fetch(`${this.baseURL}/pacientes/${id}/turnos`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getPacienteWithHistoriaClinica(id) {
        const response = await fetch(`${this.baseURL}/pacientes/${id}/historia-clinica`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async createPaciente(pacienteData) {
        const response = await fetch(`${this.baseURL}/pacientes`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify(pacienteData)
        });
        return await this.handleResponse(response);
    }

    async updatePaciente(id, pacienteData) {
        const response = await fetch(`${this.baseURL}/pacientes/${id}`, {
            method: 'PUT',
            headers: this.getHeaders(),
            body: JSON.stringify(pacienteData)
        });
        return await this.handleResponse(response);
    }

    async validatePacienteAvailability(email, dni, excludeId = null) {
        const response = await fetch(`${this.baseURL}/pacientes/validate-availability`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify({ email, dni, exclude_id: excludeId })
        });
        return await this.handleResponse(response);
    }

    // ========== ESTADÍSTICAS ==========
    async getSystemStats() {
        const response = await fetch(`${this.baseURL}/system-stats`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getPacienteStats() {
        const response = await fetch(`${this.baseURL}/pacientes/stats`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    async getDoctorStats(id) {
        const response = await fetch(`${this.baseURL}/doctores/${id}/stats`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    // ========== AUDITORÍA ==========
    async getAuditLogs(filters = {}) {
        const params = new URLSearchParams(filters);
        const response = await fetch(`${this.baseURL}/audit-logs?${params}`, {
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }
}

// Instancia global del API
window.medicalAPI = new MedicalAppointmentAPI();

// Alpine.js Store para manejo de estado global
document.addEventListener('alpine:init', () => {
    Alpine.store('app', {
        // Estado de autenticación
        user: JSON.parse(localStorage.getItem('user') || 'null'),
        isAuthenticated: !!localStorage.getItem('auth_token'),
        
        // Estado de la aplicación
        loading: false,
        error: null,
        
        // Datos cached
        doctores: [],
        pacientes: [],
        turnos: [],
        
        // Métodos
        async login(email, password) {
            this.loading = true;
            this.error = null;
            
            try {
                const result = await window.medicalAPI.login(email, password);
                if (result.success) {
                    this.user = result.data.user;
                    this.isAuthenticated = true;
                }
                return result;
            } catch (error) {
                this.error = error.message;
                throw error;
            } finally {
                this.loading = false;
            }
        },
        
        async logout() {
            await window.medicalAPI.logout();
            this.user = null;
            this.isAuthenticated = false;
        },
        
        async loadDoctores() {
            try {
                const result = await window.medicalAPI.getActiveDoctores();
                this.doctores = result.data;
            } catch (error) {
                this.error = error.message;
            }
        },
        
        async loadPacientes() {
            try {
                const result = await window.medicalAPI.getActivePacientes();
                this.pacientes = result.data;
            } catch (error) {
                this.error = error.message;
            }
        },
        
        async loadTurnos(filters = {}) {
            try {
                const result = await window.medicalAPI.getTurnos(filters);
                this.turnos = result.data;
            } catch (error) {
                this.error = error.message;
            }
        },
        
        clearError() {
            this.error = null;
        }
    });

    // Nuevo componente para cambio de contraseña
    Alpine.data('passwordChange', () => ({
        showModal: false,
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',
        loading: false,
        error: null,
        success: false,

        async changePassword() {
            if (this.newPassword !== this.confirmPassword) {
                this.error = 'Las contraseñas no coinciden';
                return;
            }

            this.loading = true;
            this.error = null;

            try {
                await window.medicalAPI.changePassword(
                    this.currentPassword,
                    this.newPassword,
                    this.confirmPassword
                );
                
                this.success = true;
                this.showModal = false;
                this.resetForm();
                
                // Mostrar mensaje de éxito
                setTimeout(() => {
                    this.success = false;
                }, 3000);

            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        resetForm() {
            this.currentPassword = '';
            this.newPassword = '';
            this.confirmPassword = '';
            this.error = null;
        },

        openModal() {
            this.showModal = true;
            this.resetForm();
        },

        closeModal() {
            this.showModal = false;
            this.resetForm();
        }
    }));

    // Componente para horarios disponibles
    Alpine.data('availableSlots', () => ({
        selectedDoctor: null,
        selectedDate: '',
        availableSlots: [],
        loading: false,
        error: null,

        async loadAvailableSlots() {
            if (!this.selectedDoctor || !this.selectedDate) {
                return;
            }

            this.loading = true;
            this.error = null;

            try {
                const result = await window.medicalAPI.getAvailableSlots(
                    this.selectedDoctor,
                    this.selectedDate
                );
                
                this.availableSlots = result.horarios_disponibles || [];
                
            } catch (error) {
                this.error = error.message;
                this.availableSlots = [];
            } finally {
                this.loading = false;
            }
        },

        selectSlot(slot) {
            this.$dispatch('slot-selected', {
                doctor: this.selectedDoctor,
                date: this.selectedDate,
                time: slot
            });
        }
    }));

    // Componente para dashboard de administrador
    Alpine.data('adminDashboard', () => ({
        stats: {},
        auditLogs: [],
        cacheStats: {},
        loading: false,
        error: null,

        async init() {
            await this.loadStats();
            await this.loadCacheStats();
        },

        async loadStats() {
            this.loading = true;
            try {
                const result = await window.medicalAPI.getDashboardStats();
                this.stats = result;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        async loadAuditLogs(filters = {}) {
            this.loading = true;
            try {
                const result = await window.medicalAPI.getAuditLogs(filters);
                this.auditLogs = result.data || [];
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        async loadCacheStats() {
            try {
                const result = await window.medicalAPI.getCacheStats();
                this.cacheStats = result;
            } catch (error) {
                console.error('Error loading cache stats:', error);
            }
        },

        async clearCache() {
            this.loading = true;
            try {
                await window.medicalAPI.clearCache();
                await this.loadCacheStats();
                
                this.$dispatch('show-notification', {
                    type: 'success',
                    message: 'Cache limpiado exitosamente'
                });
                
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        }
    }));

    // Componente para notificaciones
    Alpine.data('notifications', () => ({
        notifications: [],

        init() {
            this.$watch('notifications', () => {
                // Auto-remove notifications after 5 seconds
                setTimeout(() => {
                    this.notifications = this.notifications.slice(1);
                }, 5000);
            });

            // Listen for notification events
            this.$el.addEventListener('show-notification', (e) => {
                this.addNotification(e.detail);
            });
        },

        addNotification(notification) {
            this.notifications.push({
                id: Date.now(),
                type: notification.type || 'info',
                message: notification.message,
                timestamp: new Date()
            });
        },

        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }));
});
