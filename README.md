# FoTeam PHP - Fotografía de Maratones

Esta es la versión PHP de la aplicación FoTeam, diseñada para ser alojada en Hostgator. La aplicación permite a los fotógrafos subir, organizar y vender fotos de maratones a los corredores.

## Características Principales

- Registro y autenticación de usuarios
- Creación y gestión de maratones
- Subida de fotos con detección de números de corredor
- Galería de fotos con filtros por maratón y números
- Carrito de compras para seleccionar múltiples fotos
- Proceso de pago y gestión de pedidos
- Descarga de fotos compradas

## Requisitos del Sistema

- PHP 8.0 o superior
- MySQL 5.7+ o MariaDB 10.3+
- Extensiones PHP: pdo, pdo_mysql, gd, json, session, fileinfo, openssl
- Servidor web (Apache o Nginx)
- Node.js 16+ y NPM 7+
- Composer 2.0+
- Credenciales de Google Cloud Vision API
- Al menos 1GB de espacio en disco para almacenamiento de fotos
- Memoria PHP recomendada: 256M o superior

## Instalación en Hostgator

1. **Subir archivos al servidor**:
   - Sube todos los archivos de la carpeta `php_version` a tu directorio público en Hostgator (generalmente `public_html`).

2. **Configurar permisos de directorios**:
   - Asegúrate de que los siguientes directorios tengan permisos de escritura (755 o 775):
     ```
     /uploads
     /uploads/thumbnails
     /database
     ```
   - La base de datos SQLite se creará automáticamente en el directorio `/database` la primera vez que se acceda a la aplicación.

3. **Configurar la aplicación**:
   - Edita el archivo `includes/config.php` para actualizar la URL base:
     ```php
     // Actualiza la URL base
     define('BASE_URL', 'http://tudominio.com');
     ```

4. **Crear cuenta de administrador**:
   - Regístrate en la aplicación como un usuario normal.
   - Para convertir tu cuenta en administrador, necesitarás acceder a la base de datos SQLite y ejecutar:
     ```sql
     UPDATE users SET is_admin = 1 WHERE id = 1; -- Cambia 1 por tu ID de usuario
     ```
   - Puedes usar una herramienta como SQLite Browser (https://sqlitebrowser.org/) para editar la base de datos localmente y luego subirla, o usar una extensión de SQLite para PHP en el servidor.

## Estructura de Directorios

- `/` - Archivos principales de la aplicación
- `/includes` - Archivos de configuración y funciones
- `/admin` - Panel de administración
- `/assets` - Archivos CSS, JavaScript e imágenes
- `/uploads` - Directorio para almacenar las fotos subidas
- `/uploads/thumbnails` - Directorio para miniaturas

## Personalización

### Precios

Para cambiar el precio de las fotos, edita la variable `$price_per_item` en los siguientes archivos:
- `includes/functions.php` (función `create_order`)
- `checkout.php`

### Estilos

Los estilos principales se definen en `includes/header.php`. Puedes modificar las variables CSS:
```css
:root {
    --primary-color: #2c3e50;
    --secondary-color: #3498db;
    --spacing-unit: 8px;
}
```

## Solución de Problemas

### Permisos de Archivos
Si tienes problemas para subir imágenes o con la base de datos, verifica que los directorios `/uploads`, `/uploads/thumbnails` y `/database` tengan permisos de escritura correctos (755 o 775).

### Error de Base de Datos
Si aparecen errores relacionados con SQLite, asegúrate de que la extensión `sqlite3` esté habilitada en tu servidor PHP. Puedes verificar esto creando un archivo `phpinfo.php` con el siguiente contenido y accediendo a él desde tu navegador:
```php
<?php phpinfo(); ?>
```

### Imágenes No Visibles
Si las imágenes se suben pero no se muestran, verifica las rutas en `includes/functions.php` y asegúrate de que `$_SERVER['DOCUMENT_ROOT']` esté funcionando correctamente en tu entorno.

## Seguridad

La aplicación incluye:
- Protección CSRF en todos los formularios
- Contraseñas hasheadas con password_hash()
- Sanitización de salida con htmlspecialchars()
- Consultas preparadas para prevenir inyección SQL
- Almacenamiento seguro de credenciales de API mediante variables de entorno

### Gestión Segura de Credenciales

La aplicación utiliza un sistema de variables de entorno para gestionar credenciales de API de forma segura:

1. **Archivo .env**: Almacena las credenciales de API en un archivo `.env` en la raíz del proyecto.
   ```
   # Ejemplo de formato .env
   GOOGLE_CLOUD_API_KEY=tu_api_key
   GOOGLE_CLOUD_VISION_PROJECT_ID=tu_project_id
   ```

2. **Configuración**: Asegúrate de que el archivo `.env` esté incluido en `.gitignore` para evitar que se suba al control de versiones.

3. **Instalación en producción**:
   - Crea manualmente el archivo `.env` en el servidor de producción
   - Establece permisos restrictivos: `chmod 600 .env`
   - Alternativamente, configura las variables de entorno directamente en el panel de control de tu hosting

## Contacto

Si tienes preguntas o problemas con la instalación, contacta al desarrollador.

---

Desarrollado para FoTeam © 2025
