<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------------
    | Session Configuration for Internal Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración de sesiones optimizada para sistema interno de clínica
    | con seguridad apropiada para entorno médico
    */

    'driver' => 'database', // Database para persistencia en sistema interno

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime - Optimized for Medical Environment
    |--------------------------------------------------------------------------
    | Configuración para jornada laboral de clínica (4 horas por sesión)
    */

    'lifetime' => 240, // 4 horas para jornada laboral de clínica
    'expire_on_close' => true, // Expirar al cerrar navegador por seguridad

    /*
    |--------------------------------------------------------------------------
    | Session Encryption - Enabled for Medical Data
    |--------------------------------------------------------------------------
    | Encriptación habilitada por seguridad de datos médicos
    */

    'encrypt' => true, // Obligatorio para datos médicos

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | When utilizing the "file" session driver, the session files are placed
    | on disk. The default storage location is defined here; however, you
    | are free to provide another location where they should be stored.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    |
    | When using the "database" or "redis" session drivers, you may specify a
    | connection that should be used to manage these sessions. This should
    | correspond to a connection in your database configuration options.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | When using the "database" session driver, you may specify the table to
    | be used to store sessions. Of course, a sensible default is defined
    | for you; however, you're welcome to change this to another table.
    |
    */

    'table' => 'sessions',

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | Para sistema médico usamos cache de archivos como respaldo
    |
    */

    'store' => null,

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Some session drivers must manually sweep their storage location to get
    | rid of old sessions from storage. Here are the chances that it will
    | happen on a given request. By default, the odds are 2 out of 100.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the session cookie that is created by
    | the framework. Typically, you should not need to change this value
    | since doing so does not grant a meaningful security improvement.
    |
    */

    'cookie' => 'clinica_dental_session',

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | Ruta específica para el sistema de clínica dental
    |
    */

    'path' => '/',

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | Para sistema interno local no especificamos dominio
    |
    */

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | Configurado para ambiente médico seguro
    |
    */

    'secure' => true,

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | Prevenir acceso JavaScript para mayor seguridad médica
    |
    */

    'http_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | Configuración estricta para sistema médico interno
    |
    */

    'same_site' => 'strict',

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | No necesario para sistema interno
    |
    */

    'partitioned' => false,

];
