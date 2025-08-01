# 🔒 SECURITY DOCUMENTATION

## 📋 Índice
1. [Arquitectura de Seguridad](#arquitectura-de-seguridad)
2. [Autenticación y Autorización](#autenticación-y-autorización)
3. [Protección OWASP Top 10](#protección-owasp-top-10)
4. [Middleware de Seguridad](#middleware-de-seguridad)
5. [Validación y Sanitización](#validación-y-sanitización)
6. [Logging y Auditoría](#logging-y-auditoría)
7. [Configuración de Headers](#configuración-de-headers)
8. [Rate Limiting](#rate-limiting)
9. [Políticas de Acceso](#políticas-de-acceso)
10. [Checklist de Deployment](#checklist-de-deployment)

---

## 🏗️ Arquitectura de Seguridad

### Capas de Seguridad Implementadas

```
Frontend (Vanilla JS)
    ↓ [HTTPS, CSP, CSRF]
Middleware Stack
    ↓ [Rate Limiting, Headers, Logging]
Authentication Layer
    ↓ [Sanctum, JWT, Roles]
Authorization Layer
    ↓ [Policies, Gates, RBAC]
Business Logic
    ↓ [Validation, Sanitization]
Data Layer
    ↓ [Encryption, Hashing, Audit]
```

### Componentes Principales

1. **Frontend Security Manager** (`frontend/js/security.js`)
2. **Authentication Controller** (`app/Http/Controllers/AuthController.php`)
3. **Security Middleware Stack** (`app/Http/Middleware/`)
4. **Authorization Policies** (`app/Policies/`)
5. **Request Validation** (`app/Http/Requests/`)

---

## 🔑 Autenticación y Autorización

### Sanctum Configuration
- **Token Expiration**: 24 horas (configurable)
- **Stateful Domains**: Configurados para SPA
- **CSRF Protection**: Habilitado para requests stateful

### Roles y Permisos
```php
Roles:
- admin: Acceso completo al sistema
- doctor: Gestión de pacientes asignados y agenda
- secretaria: Gestión de turnos y pacientes

Permisos por Rol:
- admin: CRUD completo, reportes, gestión de usuarios
- doctor: Ver/editar pacientes propios, agenda, historias clínicas
- secretaria: CRUD turnos, CRUD pacientes (limitado)
```

### Multi-Factor Authentication (Futuro)
- Preparado para implementar 2FA con TOTP
- Backup codes para recuperación
- SMS verification como fallback

---

## 🛡️ Protección OWASP Top 10

### A01: Broken Access Control
- **Políticas de autorización** por modelo
- **RBAC** (Role-Based Access Control)
- **Object-level authorization** en todos los endpoints
- **Validación de ownership** en recursos

### A02: Cryptographic Failures
- **Argon2ID** para hashing de passwords
- **AES-256** para datos sensibles
- **TLS 1.3** en producción
- **Secrets management** con variables de entorno

### A03: Injection
- **Eloquent ORM** previene SQL injection
- **Prepared statements** en queries custom
- **Input validation** estricta
- **Output encoding** automático

### A04: Insecure Design
- **Security by design** en arquitectura
- **Threat modeling** documentado
- **Secure defaults** en toda la configuración

### A05: Security Misconfiguration
- **Headers de seguridad** automáticos
- **Error handling** sin información sensible
- **Environment separation** estricta

### A06: Vulnerable Components
- **Dependency scanning** en CI/CD
- **Regular updates** de Laravel y packages
- **Security advisories** monitoring

### A07: Authentication Failures
- **Rate limiting** en login
- **Account lockout** temporal
- **Strong password policies**
- **Session management** seguro

### A08: Software Integrity Failures
- **Composer lock** para dependencies
- **Integrity checks** en assets
- **Secure deployment** pipeline

### A09: Logging Failures
- **Comprehensive logging** de eventos de seguridad
- **Centralized logging** con retention policies
- **Monitoring y alertas** automáticas

### A10: Server-Side Request Forgery
- **URL validation** en requests externos
- **Whitelist** de dominios permitidos
- **Network segmentation**

---

## 🔒 Middleware de Seguridad

### SecureHeaders Middleware
```php
Headers aplicados:
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- X-XSS-Protection: 1; mode=block
- Referrer-Policy: strict-origin-when-cross-origin
- Content-Security-Policy: strict policy
- Strict-Transport-Security: HSTS enabled
```

### ThrottleWithBanMiddleware
```php
Funcionalidades:
- Rate limiting per IP/user
- Automatic IP banning
- Escalating timeouts
- Security event logging
```

### SecurityLogging Middleware
```php
Eventos registrados:
- Login attempts (success/failure)
- API requests con status >= 400
- Rate limit violations
- Permission denied events
```

---

## ✅ Validación y Sanitización

### Input Validation Rules
```php
Pacientes:
- DNI: 7-8 dígitos únicos
- Email: RFC validation + DNS check
- Nombres: Solo letras y espacios
- Teléfono: Formato internacional

Usuarios:
- Password: 8+ chars, mixed case, numbers, symbols
- Email: Unique, valid format
- Role: Enum validation
```

### Sanitization Process
```javascript
Frontend:
- strip_tags() en todos los inputs
- Validation antes de envío
- XSS prevention en display

Backend:
- Automatic sanitization en FormRequests
- Output encoding en responses
- SQL injection prevention via ORM
```

---

## 📊 Logging y Auditoría

### Security Log Channels
```php
Channels configurados:
- security: Eventos de seguridad
- audit: Cambios en modelos críticos
- performance: Métricas de rendimiento
```

### Audit Trail
```php
Eventos auditados:
- Create/Update/Delete en todos los modelos
- Login/Logout de usuarios
- Permission changes
- Failed authentication attempts
- Rate limit violations
```

### Log Retention
- **Security logs**: 7 años (compliance médico)
- **Application logs**: 90 días
- **Performance logs**: 30 días

---

## 🌐 Configuración de Headers

### Content Security Policy
```
default-src 'self';
script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;
style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
font-src 'self' https://fonts.gstatic.com;
img-src 'self' data: https:;
connect-src 'self' [API_URL];
object-src 'none';
base-uri 'self';
form-action 'self';
```

### CORS Configuration
```php
Allowed Origins: Configurado por environment
Allowed Headers: Standard + custom headers
Credentials: true (para Sanctum)
Max Age: 24 horas
```

---

## ⚡ Rate Limiting

### Límites por Endpoint
```php
Login: 5 attempts per 5 minutes
API General: 120 requests per minute
Public endpoints: 60 requests per minute
Admin endpoints: 200 requests per minute
```

### Progressive Penalties
```php
1st violation: 5 minute timeout
2nd violation: 15 minute timeout
3rd violation: 1 hour ban
Persistent violations: 24 hour ban
```

---

## 👮 Políticas de Acceso

### Patient Policy
- **Admin**: Full access
- **Doctor**: Only assigned patients
- **Secretaria**: Limited CRUD access

### Appointment Policy
- **Admin**: Full management
- **Doctor**: Own appointments only
- **Secretaria**: Create/update pending only

### Clinical History Policy
- **Admin**: Full access
- **Doctor**: Own patients only
- **Secretaria**: No access

---

## 🚀 Checklist de Deployment

### ✅ Pre-Deployment Security Checklist

1. **Environment Configuration**
   - [ ] `APP_DEBUG=false` en producción
   - [ ] `APP_ENV=production`
   - [ ] Variables sensibles en `.env` únicamente
   - [ ] HTTPS configurado y funcional
   - [ ] Certificados SSL válidos

2. **Database Security**
   - [ ] Credentials de DB únicos y fuertes
   - [ ] Database user con permisos mínimos
   - [ ] Backup automático configurado
   - [ ] Encryption en rest habilitado

3. **Application Security**
   - [ ] Sanctum configurado correctamente
   - [ ] Rate limiting activo
   - [ ] CORS configurado para dominios específicos
   - [ ] CSP headers implementados
   - [ ] Error reporting deshabilitado

4. **Server Security**
   - [ ] Firewall configurado
   - [ ] SSH keys únicamente (no passwords)
   - [ ] Servicios innecesarios deshabilitados
   - [ ] Updates de seguridad aplicados
   - [ ] Monitoring activo

5. **Logging & Monitoring**
   - [ ] Logs centralizados configurados
   - [ ] Alertas de seguridad activas
   - [ ] Retention policies implementadas
   - [ ] Backup de logs configurado

6. **Code Security**
   - [ ] Dependencies actualizadas
   - [ ] Security scan pasado
   - [ ] Secrets no hardcodeados
   - [ ] Input validation completa
   - [ ] Output encoding aplicado

7. **Network Security**
   - [ ] HTTPS redirect configurado
   - [ ] HSTS headers activos
   - [ ] DNS CAA records configurados
   - [ ] CDN con protección DDoS

8. **Compliance**
   - [ ] Políticas de privacidad actualizadas
   - [ ] GDPR compliance verificado
   - [ ] Audit trails funcionando
   - [ ] Data retention policies activas

9. **Performance & Availability**
   - [ ] Load balancing configurado
   - [ ] Database optimization aplicada
   - [ ] Caching strategy implementada
   - [ ] Health checks activos

10. **Incident Response**
    - [ ] Playbooks de seguridad documentados
    - [ ] Contactos de emergencia definidos
    - [ ] Rollback procedures probados
    - [ ] Communication plan establecido

---

## 🔧 Configuración de Producción

### Variables de Entorno Críticas
```bash
# Security
APP_KEY=base64:RANDOM_32_BYTE_KEY
SANCTUM_TOKEN_EXPIRATION=1440
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

# Database
DB_PASSWORD=STRONG_RANDOM_PASSWORD
REDIS_PASSWORD=STRONG_RANDOM_PASSWORD

# External Services
ENCRYPTION_KEY=base64:RANDOM_32_BYTE_KEY
BACKUP_ENCRYPTION_KEY=base64:RANDOM_32_BYTE_KEY
```

### Nginx Configuration
```nginx
# Security headers
add_header X-Frame-Options "SAMEORIGIN";
add_header X-Content-Type-Options "nosniff";
add_header X-XSS-Protection "1; mode=block";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload";

# Rate limiting
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req zone=api burst=20 nodelay;
```

---

## 📞 Contactos de Seguridad

- **Security Lead**: [email]
- **DevOps Team**: [email]
- **Emergency Hotline**: [phone]
- **Incident Response**: [email]

## 📚 Referencias

- [OWASP Top 10 2021](https://owasp.org/Top10/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Sanctum Documentation](https://laravel.com/docs/sanctum)
- [HIPAA Compliance Guide](https://www.hhs.gov/hipaa/)
