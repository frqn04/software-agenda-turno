# Sistema de Agenda Odontológica

Sistema completo de gestión de turnos odontológicos desarrollado con Laravel 11 y frontend vanilla JavaScript.

## 🚀 Características

- **Backend**: Laravel 11 + PostgreSQL/MySQL
- **Frontend**: HTML5 + JavaScript Vanilla + Tailwind CSS
- **Autenticación**: Laravel Sanctum (API Token)
- **Arquitectura**: MVC + Service + Repository + Observer
- **Soft Deletes**: En todas las tablas
- **Historia Clínica**: Campo de texto libre manual
- **Gestión de Contratos**: Contratos eventuales y horarios mañana/tarde

## 📋 Funcionalidades

### Gestión de Usuarios
- Login/Logout con roles (admin, doctor, recepcionista)
- Solo admin puede registrar nuevos usuarios

### Gestión de Doctores
- CRUD completo de doctores
- Especialidades médicas
- Contratos eventuales con fechas
- Horarios de trabajo (mañana/tarde) con slots de 30 minutos
- Matrícula única por especialidad

### Gestión de Pacientes
- CRUD completo de pacientes
- DNI único
- Datos de obra social
- Historia clínica con evoluciones

### Gestión de Turnos
- Calendario visual por doctor y turno (mañana/tarde)
- Validaciones automáticas:
  - No superposición de horarios
  - Doctor debe tener contrato activo
  - Slot disponible según horarios configurados
- Estados: pendiente, cancelado, realizado
- Generación de PDF de agenda

### Auditoría
- Log automático de todas las operaciones (Observer pattern)
- Registro de usuario, IP y timestamp

## 🛠 Instalación

### Requisitos
- PHP 8.2+
- Composer
- Node.js (opcional, para desarrollo)
- Base de datos (MySQL/PostgreSQL/SQLite)

### Pasos de Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/tu-usuario/software-agenda-turnos.git
   cd software-agenda-turnos
   ```

2. **Instalar dependencias**
   ```bash
   composer install
   ```

3. **Configurar variables de entorno**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configurar base de datos en .env**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=agenda_turnos
   DB_USERNAME=tu_usuario
   DB_PASSWORD=tu_password
   ```

5. **Ejecutar migraciones y seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Iniciar servidor de desarrollo**
   ```bash
   php artisan serve
   ```

7. **Acceder al frontend**
   - Abrir `frontend/index.html` en un navegador
   - O servir con un servidor local

## 👥 Usuarios por Defecto

| Rol | Email | Contraseña |
|-----|-------|------------|
| Admin | admin@agenda.com | 123456 |
| Recepcionista | recepcionista@agenda.com | 123456 |

## 📁 Estructura del Proyecto

```
├── app/
│   ├── Http/Controllers/Api/     # Controladores API
│   ├── Models/                   # Modelos Eloquent
│   ├── Http/Middleware/          # Middleware personalizado
├── database/
│   ├── migrations/               # Migraciones de base de datos
│   ├── seeders/                  # Seeders de datos iniciales
├── frontend/                     # Frontend JavaScript vanilla
│   ├── index.html               # Aplicación principal
│   └── app.js                   # Lógica JavaScript
├── routes/
│   └── api.php                  # Rutas API
└── README.md
```

## 🔧 API Endpoints

### Autenticación
- `POST /api/login` - Iniciar sesión
- `POST /api/logout` - Cerrar sesión
- `GET /api/user` - Obtener usuario actual
- `POST /api/register` - Registrar usuario (solo admin)

### Pacientes
- `GET /api/pacientes` - Listar pacientes
- `POST /api/pacientes` - Crear paciente
- `GET /api/pacientes/{id}` - Obtener paciente
- `PUT /api/pacientes/{id}` - Actualizar paciente
- `DELETE /api/pacientes/{id}` - Eliminar paciente
- `GET /api/pacientes/{id}/historia-clinica` - Historia clínica
- `POST /api/pacientes/{id}/evoluciones` - Agregar evolución

### Doctores
- `GET /api/doctores` - Listar doctores
- `POST /api/doctores` - Crear doctor
- `GET /api/doctores/{id}` - Obtener doctor
- `PUT /api/doctores/{id}` - Actualizar doctor
- `DELETE /api/doctores/{id}` - Eliminar doctor
- `GET /api/doctores/{id}/contratos` - Contratos del doctor
- `POST /api/doctores/{id}/contratos` - Crear contrato
- `GET /api/doctores/{id}/horarios` - Horarios del doctor
- `POST /api/doctores/{id}/horarios` - Crear horario

### Turnos
- `GET /api/turnos` - Listar turnos
- `POST /api/turnos` - Crear turno
- `GET /api/turnos/{id}` - Obtener turno
- `PUT /api/turnos/{id}` - Actualizar turno
- `DELETE /api/turnos/{id}` - Eliminar turno
- `PATCH /api/turnos/{id}/cancelar` - Cancelar turno
- `PATCH /api/turnos/{id}/realizar` - Marcar como realizado

### Agenda
- `GET /api/agenda/doctor/{id}` - Agenda por doctor
- `GET /api/agenda/fecha/{fecha}` - Agenda por fecha
- `GET /api/agenda/pdf` - Generar PDF de agenda

## 🧪 Testing

Ejecutar tests:
```bash
php artisan test
```

Tests implementados:
- Validación de no superposición de turnos
- Validación de contratos activos
- Autenticación y autorización

## 🚀 Deploy en Render

### Variables de Entorno Requeridas

```env
APP_NAME="Agenda Odontológica"
APP_ENV=production
APP_KEY=base64:tu_key_generada
APP_DEBUG=false
APP_URL=https://tu-app.onrender.com

DB_CONNECTION=pgsql
DB_HOST=tu_host_postgresql
DB_PORT=5432
DB_DATABASE=tu_database
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

SANCTUM_STATEFUL_DOMAINS=tu-app.onrender.com
SESSION_DOMAIN=.tu-app.onrender.com
```

### Pasos para Deploy

1. **Conectar repositorio a Render**
2. **Configurar Build Command**:
   ```bash
   composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
3. **Configurar Start Command**:
   ```bash
   php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT
   ```
4. **Agregar variables de entorno**
5. **Deploy**

## 📝 Notas de Desarrollo

### Reglas de Negocio Implementadas

1. **DNI único** en pacientes
2. **Matrícula única por especialidad** en doctores
3. **Estados de turno**: pendiente → realizado/cancelado
4. **Validaciones de turnos**:
   - No superposición de horarios
   - Doctor con contrato activo en la fecha
   - Slot disponible según configuración
5. **Auditoría automática** vía Observer pattern
6. **Soft deletes** en todas las entidades

### Próximas Mejoras

- [ ] Recordatorios automáticos por email/SMS
- [ ] Cálculo automático de honorarios
- [ ] Dashboard con estadísticas
- [ ] Reportes avanzados
- [ ] Integración con sistemas de facturación
- [ ] App móvil

## 🤝 Contribución

1. Fork el proyecto
2. Crear rama para feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Para soporte técnico o consultas:
- Email: soporte@agenda.com
- Issues: [GitHub Issues](https://github.com/tu-usuario/software-agenda-turnos/issues)
