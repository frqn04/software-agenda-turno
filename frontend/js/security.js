class SecurityManager {
    constructor() {
        this.apiBase = window.API_BASE || 'http://localhost:8000/api';
        this.token = localStorage.getItem('authToken');
        this.csrfToken = null;
        this.initializeSecurity();
    }

    async initializeSecurity() {
        await this.getCsrfToken();
        this.setupRequestInterceptors();
        this.setupSecurityHeaders();
        this.monitorSecurityEvents();
    }

    async getCsrfToken() {
        try {
            const response = await fetch('/sanctum/csrf-cookie', {
                credentials: 'include'
            });
            
            const cookies = document.cookie.split(';');
            const xsrfCookie = cookies.find(c => c.trim().startsWith('XSRF-TOKEN='));
            
            if (xsrfCookie) {
                this.csrfToken = decodeURIComponent(xsrfCookie.split('=')[1]);
            }
        } catch (error) {
            console.error('Error getting CSRF token:', error);
        }
    }

    getSecureHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        if (this.csrfToken) {
            headers['X-XSRF-TOKEN'] = this.csrfToken;
        }

        return headers;
    }

    async secureApiRequest(endpoint, options = {}) {
        const config = {
            headers: this.getSecureHeaders(),
            credentials: 'include',
            ...options
        };

        if (options.headers) {
            config.headers = { ...config.headers, ...options.headers };
        }

        try {
            const response = await fetch(`${this.apiBase}${endpoint}`, config);
            
            if (response.status === 419) {
                await this.getCsrfToken();
                throw new Error('CSRF token mismatch. Please refresh and try again.');
            }

            if (response.status === 401) {
                this.handleUnauthorized();
                throw new Error('Session expired. Please login again.');
            }

            if (response.status === 429) {
                const retryAfter = response.headers.get('Retry-After');
                throw new Error(`Rate limit exceeded. Try again in ${retryAfter} seconds.`);
            }

            return await this.parseResponse(response);
        } catch (error) {
            this.logSecurityEvent('api_error', {
                endpoint,
                error: error.message,
                timestamp: new Date().toISOString()
            });
            throw error;
        }
    }

    async parseResponse(response) {
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        }
        
        return await response.text();
    }

    handleUnauthorized() {
        this.clearSession();
        window.location.href = '/login';
    }

    clearSession() {
        localStorage.removeItem('authToken');
        localStorage.removeItem('currentUser');
        this.token = null;
        
        document.cookie.split(";").forEach(cookie => {
            const eqPos = cookie.indexOf("=");
            const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
            document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=;`;
        });
    }

    setupRequestInterceptors() {
        const originalFetch = window.fetch;
        
        window.fetch = async (url, options = {}) => {
            if (typeof url === 'string' && url.startsWith(this.apiBase)) {
                options.headers = {
                    ...this.getSecureHeaders(),
                    ...options.headers
                };
                options.credentials = 'include';
            }
            
            return originalFetch(url, options);
        };
    }

    setupSecurityHeaders() {
        const meta = document.createElement('meta');
        meta.httpEquiv = 'Content-Security-Policy';
        meta.content = "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;";
        document.head.appendChild(meta);
    }

    monitorSecurityEvents() {
        window.addEventListener('error', (event) => {
            this.logSecurityEvent('js_error', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                timestamp: new Date().toISOString()
            });
        });

        window.addEventListener('unhandledrejection', (event) => {
            this.logSecurityEvent('promise_rejection', {
                reason: event.reason?.toString(),
                timestamp: new Date().toISOString()
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.tagName === 'A' && event.target.href.startsWith('javascript:')) {
                event.preventDefault();
                this.logSecurityEvent('blocked_javascript_link', {
                    href: event.target.href,
                    timestamp: new Date().toISOString()
                });
            }
        });
    }

    logSecurityEvent(type, data) {
        const event = {
            type,
            data,
            userAgent: navigator.userAgent,
            url: window.location.href,
            timestamp: new Date().toISOString()
        };

        if (typeof console.warn === 'function') {
            console.warn('Security Event:', event);
        }

        try {
            const existingLogs = JSON.parse(localStorage.getItem('securityLogs') || '[]');
            existingLogs.push(event);
            
            if (existingLogs.length > 100) {
                existingLogs.splice(0, 50);
            }
            
            localStorage.setItem('securityLogs', JSON.stringify(existingLogs));
        } catch (error) {
            console.error('Failed to log security event:', error);
        }
    }

    sanitizeInput(input) {
        if (typeof input !== 'string') return input;
        
        return input
            .replace(/[<>]/g, '')
            .replace(/javascript:/gi, '')
            .replace(/on\w+=/gi, '')
            .trim();
    }

    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email) && !/<|>|"|'/.test(email);
    }

    validateDNI(dni) {
        const dniRegex = /^[0-9]{7,8}$/;
        return dniRegex.test(dni.toString());
    }

    generateNonce() {
        const array = new Uint8Array(16);
        crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    async hashPassword(password) {
        if (!window.crypto || !window.crypto.subtle) {
            throw new Error('Web Crypto API not supported');
        }

        const encoder = new TextEncoder();
        const data = encoder.encode(password);
        const hash = await crypto.subtle.digest('SHA-256', data);
        
        return Array.from(new Uint8Array(hash))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }

    checkPasswordStrength(password) {
        const minLength = 8;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumbers = /\d/.test(password);
        const hasSymbols = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        const score = [
            password.length >= minLength,
            hasUpperCase,
            hasLowerCase,
            hasNumbers,
            hasSymbols
        ].filter(Boolean).length;

        return {
            score,
            isValid: score >= 4,
            requirements: {
                minLength: password.length >= minLength,
                hasUpperCase,
                hasLowerCase,
                hasNumbers,
                hasSymbols
            }
        };
    }

    encryptSensitiveData(data, key) {
        try {
            return btoa(JSON.stringify(data));
        } catch (error) {
            console.error('Encryption failed:', error);
            return null;
        }
    }

    decryptSensitiveData(encryptedData) {
        try {
            return JSON.parse(atob(encryptedData));
        } catch (error) {
            console.error('Decryption failed:', error);
            return null;
        }
    }
}

const securityManager = new SecurityManager();

window.apiRequest = async (endpoint, options = {}) => {
    return securityManager.secureApiRequest(endpoint, options);
};

window.sanitizeInput = (input) => securityManager.sanitizeInput(input);
window.validateEmail = (email) => securityManager.validateEmail(email);
window.validateDNI = (dni) => securityManager.validateDNI(dni);
window.checkPasswordStrength = (password) => securityManager.checkPasswordStrength(password);

if (typeof module !== 'undefined' && module.exports) {
    module.exports = SecurityManager;
}
