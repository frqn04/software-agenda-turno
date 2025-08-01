# API Documentation - Sistema de Agenda de Turnos Médicos

## Autenticación

Todas las rutas protegidas requieren autenticación mediante Sanctum token.

### Headers requeridos:
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

## Endpoints Principales

### Autenticación

#### POST `/api/v1/auth/login`
Iniciar sesión en el sistema.

**Body:**
```json
{
  "email": "usuario@ejemplo.com",
  "password": "contraseña"
}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "token": "1|abcd1234...",
  "user": {
    "id": 1,
    "name": "Usuario",
    "email": "usuario@ejemplo.com",
    "rol": "admin"
  }
}
```

#### POST `/api/v1/auth/logout`
Cerrar sesión (requiere autenticación).

#### POST `/api/v1/auth/change-password`
Cambiar contraseña del usuario autenticado.

**Body:**
```json
{
  "current_password": "contraseña_actual",
  "new_password": "nueva_contraseña",
  "new_password_confirmation": "nueva_contraseña"
}
```

### Pacientes

#### GET `/api/v1/pacientes`
Listar pacientes con paginación.

**Query params:**
- `page`: Número de página
- `per_page`: Elementos por página (máx 100)
- `search`: Buscar por nombre, apellido o DNI

#### POST `/api/v1/pacientes`
Crear nuevo paciente.

**Body:**
```json
{
  "nombre": "Juan",
  "apellido": "Pérez",
  "dni": "12345678",
  "fecha_nacimiento": "1990-01-01",
  "telefono": "123456789",
  "email": "juan@ejemplo.com",
  "direccion": "Calle 123",
  "obra_social": "OSDE",
  "numero_afiliado": "123456"
}
```

#### GET `/api/v1/pacientes/{id}`
Obtener paciente específico.

#### PUT `/api/v1/pacientes/{id}`
Actualizar paciente.

#### DELETE `/api/v1/pacientes/{id}`
Eliminar paciente (soft delete).

### Doctores

#### GET `/api/v1/doctores`
Listar doctores activos.

#### POST `/api/v1/doctores`
Crear nuevo doctor.

#### GET `/api/v1/doctores/{id}/horarios-disponibles/{fecha}`
Obtener horarios disponibles para un doctor en una fecha específica.

**Ejemplo:** `/api/v1/doctores/1/horarios-disponibles/2025-08-15`

**Respuesta:**
```json
{
  "success": true,
  "horarios_disponibles": ["08:00", "08:30", "09:00", "09:30"],
  "fecha": "2025-08-15",
  "doctor_id": 1
}
```

### Turnos

#### GET `/api/v1/turnos`
Listar turnos con filtros.

**Query params:**
- `fecha_desde`: Filtrar desde fecha (Y-m-d)
- `fecha_hasta`: Filtrar hasta fecha (Y-m-d)
- `doctor_id`: Filtrar por doctor
- `paciente_id`: Filtrar por paciente
- `estado`: Filtrar por estado (programado, confirmado, cancelado, completado)

#### POST `/api/v1/turnos`
Crear nuevo turno.

**Body:**
```json
{
  "paciente_id": 1,
  "doctor_id": 1,
  "fecha": "2025-08-15",
  "hora_inicio": "10:00",
  "motivo": "Consulta de rutina",
  "observaciones": "Primera consulta",
  "estado": "programado"
}
```

**Validaciones:**
- Fecha debe ser futura (mín 2 horas de anticipación)
- Horario debe ser día laboral (lunes a viernes)
- Horario debe estar entre 8:00 y 18:00
- Horarios disponibles cada 30 minutos
- No debe solaparse con otros turnos del doctor

#### PUT `/api/v1/turnos/{id}`
Actualizar turno existente.

#### DELETE `/api/v1/turnos/{id}`
Cancelar turno.

### Especialidades

#### GET `/api/v1/especialidades`
Listar especialidades activas.

## Códigos de Estado

- `200`: Éxito
- `201`: Creado exitosamente
- `400`: Solicitud incorrecta
- `401`: No autenticado
- `403`: Sin permisos
- `404`: No encontrado
- `422`: Error de validación
- `429`: Demasiadas solicitudes
- `500`: Error interno del servidor

## Rate Limiting

- **Admin**: 1000 req/min
- **Doctor**: 200 req/min  
- **Secretaria**: 150 req/min
- **Otros**: 60 req/min
- **No autenticados**: 10 req/min

## Seguridad

- Autenticación mediante Sanctum tokens
- Rate limiting por rol de usuario
- Validación de permisos mediante Policies
- Logs de auditoría para todas las operaciones
- Headers de seguridad incluidos en todas las respuestas

## Auditoría

Todas las operaciones CRUD son auditadas automáticamente. Los logs incluyen:
- Usuario que ejecutó la acción
- IP y User-Agent
- Valores anteriores y nuevos
- Timestamp de la operación

Los logs se pueden consultar mediante:

#### GET `/api/v1/admin/audit-logs`
Obtener logs de auditoría (solo admin).

**Query params:**
- `model`: Filtrar por modelo (users, pacientes, doctores, turnos)
- `action`: Filtrar por acción (created, updated, deleted)
- `per_page`: Elementos por página

## Notificaciones

El sistema envía notificaciones automáticas:
- Confirmación de turno creado (email al paciente y doctor)
- Recordatorios 24h antes del turno
- Notificación de cancelación

## Cache

- Doctores activos: 1 hora
- Especialidades: 1 hora  
- Horarios disponibles: 30 minutos

Los administradores pueden limpiar el cache:

#### POST `/api/v1/cache/clear` (solo admin)
#### GET `/api/v1/cache/stats` (solo admin)

## Ejemplos de Uso

### Flujo típico para crear un turno:

1. **Autenticarse:**
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com","password":"password"}'
```

2. **Obtener doctores disponibles:**
```bash
curl -X GET http://localhost:8000/api/v1/doctores \
  -H "Authorization: Bearer {token}"
```

3. **Verificar horarios disponibles:**
```bash
curl -X GET http://localhost:8000/api/v1/doctores/1/horarios-disponibles/2025-08-15 \
  -H "Authorization: Bearer {token}"
```

4. **Crear el turno:**
```bash
curl -X POST http://localhost:8000/api/v1/turnos \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "paciente_id": 1,
    "doctor_id": 1,
    "fecha": "2025-08-15",
    "hora_inicio": "10:00",
    "motivo": "Consulta de rutina"
  }'
```

## Soporte

Para reportar problemas o solicitar nuevas funcionalidades, consulte los logs de auditoría o contacte al administrador del sistema.
