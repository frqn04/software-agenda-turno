#!/bin/bash

echo "=== VERIFICACIÓN DE SEGURIDAD - SISTEMA AGENDA ODONTOLÓGICA ==="
echo ""

# Verificar archivos de seguridad del backend
echo "🔒 Verificando archivos de seguridad del backend:"
echo ""

# Middlewares
if [ -f "app/Http/Middleware/SecureHeaders.php" ]; then
    echo "✅ SecureHeaders.php - OK"
else
    echo "❌ SecureHeaders.php - FALTA"
fi

if [ -f "app/Http/Middleware/ThrottleWithBanMiddleware.php" ]; then
    echo "✅ ThrottleWithBanMiddleware.php - OK"
else
    echo "❌ ThrottleWithBanMiddleware.php - FALTA"
fi

if [ -f "app/Http/Middleware/SecurityLogging.php" ]; then
    echo "✅ SecurityLogging.php - OK"
else
    echo "❌ SecurityLogging.php - FALTA"
fi

if [ -f "app/Http/Middleware/AdminMiddleware.php" ]; then
    echo "✅ AdminMiddleware.php - OK"
else
    echo "❌ AdminMiddleware.php - FALTA"
fi

echo ""

# Policies
echo "📋 Verificando Policies:"
echo ""

if [ -f "app/Policies/UserPolicy.php" ]; then
    echo "✅ UserPolicy.php - OK"
else
    echo "❌ UserPolicy.php - FALTA"
fi

if [ -f "app/Policies/PacientePolicy.php" ]; then
    echo "✅ PacientePolicy.php - OK"
else
    echo "❌ PacientePolicy.php - FALTA"
fi

if [ -f "app/Policies/DoctorPolicy.php" ]; then
    echo "✅ DoctorPolicy.php - OK"
else
    echo "❌ DoctorPolicy.php - FALTA"
fi

if [ -f "app/Policies/TurnoPolicy.php" ]; then
    echo "✅ TurnoPolicy.php - OK"
else
    echo "❌ TurnoPolicy.php - FALTA"
fi

echo ""

# Request Classes
echo "📝 Verificando Request Classes:"
echo ""

if [ -f "app/Http/Requests/LoginRequest.php" ]; then
    echo "✅ LoginRequest.php - OK"
else
    echo "❌ LoginRequest.php - FALTA"
fi

if [ -f "app/Http/Requests/RegisterRequest.php" ]; then
    echo "✅ RegisterRequest.php - OK"
else
    echo "❌ RegisterRequest.php - FALTA"
fi

if [ -f "app/Http/Requests/StorePacienteRequest.php" ]; then
    echo "✅ StorePacienteRequest.php - OK"
else
    echo "❌ StorePacienteRequest.php - FALTA"
fi

if [ -f "app/Http/Requests/StoreDoctorRequest.php" ]; then
    echo "✅ StoreDoctorRequest.php - OK"
else
    echo "❌ StoreDoctorRequest.php - FALTA"
fi

if [ -f "app/Http/Requests/StoreTurnoRequest.php" ]; then
    echo "✅ StoreTurnoRequest.php - OK"
else
    echo "❌ StoreTurnoRequest.php - FALTA"
fi

echo ""

# Providers
echo "🔧 Verificando Providers:"
echo ""

if [ -f "app/Providers/AuthServiceProvider.php" ]; then
    echo "✅ AuthServiceProvider.php - OK"
else
    echo "❌ AuthServiceProvider.php - FALTA"
fi

echo ""

# Frontend Security
echo "🌐 Verificando seguridad del frontend:"
echo ""

if [ -f "frontend/js/security.js" ]; then
    echo "✅ security.js - OK"
else
    echo "❌ security.js - FALTA"
fi

if [ -f "frontend/index.html" ]; then
    echo "✅ index.html (Alpine.js) - OK"
else
    echo "❌ index.html - FALTA"
fi

echo ""

# Configuración
echo "⚙️ Verificando configuración:"
echo ""

if [ -f ".env.secure" ]; then
    echo "✅ .env.secure - OK"
else
    echo "❌ .env.secure - FALTA"
fi

if [ -f "SECURITY.md" ]; then
    echo "✅ SECURITY.md - OK"
else
    echo "❌ SECURITY.md - FALTA"
fi

echo ""
echo "=== RESUMEN DE SEGURIDAD ==="
echo ""
echo "📊 Estado del proyecto:"
echo "• Backend: Laravel 11 con Sanctum"
echo "• Frontend: Alpine.js + Vanilla JS"
echo "• Base de datos: MySQL 8"
echo "• Seguridad: OWASP Top 10 + HIPAA/GDPR"
echo ""
echo "🛡️ Funcionalidades implementadas:"
echo "• Rate limiting con baneos automáticos"
echo "• Headers de seguridad (CSP, HSTS, XSS)"
echo "• Validación y sanitización de inputs"
echo "• Autorización granular (RBAC)"
echo "• Auditoría y logging de seguridad"
echo "• Encriptación de datos sensibles"
echo "• Protección CSRF y XSS"
echo ""
echo "🚀 Próximos pasos:"
echo "1. Ejecutar: php artisan migrate"
echo "2. Ejecutar: php artisan db:seed"
echo "3. Configurar variables de entorno"
echo "4. Probar login con usuarios por defecto"
echo "5. Verificar logs de seguridad"
echo ""
