# 🚀 Guía de Despliegue: De XAMPP a un Hosting en Producción

Esta guía contiene los pasos necesarios para migrar el **Sistema de Gestión Documental Universitaria** desde tu entorno local (XAMPP) a un servidor de hosting en internet (usualmente con panel de control **cPanel**).

---

## 📋 Resumen del Proceso

El despliegue consta de 5 fases principales:

1. **Exportar la Base de Datos Local** (desde phpMyAdmin de XAMPP).
2. **Crear la Base de Datos y Usuario en el Hosting** (desde cPanel).
3. **Importar el SQL en el Hosting** (desde phpMyAdmin del Hosting).
4. **Configurar el archivo `config/database.php`** (con los datos del hosting).
5. **Subir los Archivos y Ajustar Permisos** (en la carpeta `public_html`).

---

## 🗄️ Fase 1: Exportar la Base de Datos desde XAMPP

Para llevar la base de datos local a internet, primero debemos obtener una copia en formato `.sql`.

1. Abre tu navegador y accede a **phpMyAdmin** local: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).
2. En la barra lateral izquierda, selecciona la base de datos `gestion_documentos`.
3. Haz clic en la pestaña **Exportar** (en la parte superior).
4. Elige el método de exportación **Rápido** (Quick) y formato **SQL**.
5. Haz clic en **Exportar** (o *Go*). Se descargará un archivo (ej: `gestion_documentos.sql`) a tu computadora.

> **Nota:** En la carpeta del proyecto también tienes un archivo llamado `database/database_export.sql` que contiene la estructura básica y tablas necesarias, pero exportarlo directamente desde tu phpMyAdmin local garantiza que lleves contigo todos los usuarios y documentos que hayas creado en tus pruebas.

---

## 🌐 Fase 2: Configurar la Base de Datos en el Hosting (cPanel)

Una vez comprado el hosting (por ejemplo en Hostinger, GoDaddy, Neolo, etc.) y teniendo acceso a su panel de administración (cPanel):

### 1. Crear la Base de Datos y Usuario
1. Inicia sesión en tu **cPanel**.
2. Busca la sección **Bases de datos** y haz clic en **Asistente de bases de datos MySQL** (MySQL Database Wizard).
3. **Paso 1: Crear una base de datos.** Escribe un nombre para tu base de datos (por ejemplo, `gestion_doc`). 
   * *Nota:* El hosting le añadirá un prefijo (ej: `miusuario_gestion_doc`). Anota este nombre completo.
4. **Paso 2: Crear usuarios de base de datos.** Escribe un nombre de usuario (ej: `user_doc`) y una contraseña fuerte. **Guarda muy bien esta contraseña**.
   * *Nota:* El usuario también tendrá prefijo (ej: `miusuario_user_doc`).
5. **Paso 3: Añadir usuario a la base de datos.** Marca la casilla **TODOS LOS PRIVILEGIOS** (ALL PRIVILEGES) para que tu usuario pueda hacer consultas, insertar y modificar tablas.
6. Haz clic en **Siguiente paso** para completar la creación.

### 2. Importar el archivo SQL
1. Regresa al menú principal de **cPanel** y busca **phpMyAdmin**.
2. En la barra lateral izquierda de phpMyAdmin, haz clic en la base de datos que acabas de crear (ej: `miusuario_gestion_doc`).
3. Ve a la pestaña **Importar** (Import) en la barra superior.
4. Haz clic en **Seleccionar archivo** y elige el archivo `.sql` que exportaste en la *Fase 1* (o en su defecto `database/database_export.sql`).
5. Haz clic en **Importar** (o *Go*) al final de la página. Verás un mensaje verde indicando que la importación fue exitosa.

---

## 🛠️ Fase 3: Modificar la Configuración en el Código

Antes de subir el código al servidor, debes indicarle a PHP cómo conectarse a la nueva base de datos del hosting.

Edita el archivo `config/database.php` (puedes hacerlo en tu editor local antes de comprimir el proyecto):

```php
<?php

// Reemplaza con los datos que creaste en el cPanel de tu hosting
$dbname = "miusuario_gestion_doc"; // Nombre completo con prefijo
$dbuser = "miusuario_user_doc";   // Usuario completo con prefijo
$dbpass = "TuContrasenaSegura";    // Contraseña que asignaste al usuario

try {
    // La mayoría de hostings usan "localhost", pero si te dan un host específico de base de datos, cámbialo aquí
    $conn = new PDO("mysql:host=localhost" . ";dbname=" . $dbname, $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
    die();
}
?>
```

---

## 📦 Fase 4: Subir los Archivos al Hosting

### 1. Comprimir el proyecto
1. Ve a la carpeta `c:\xampp\htdocs\gestionDoc-main` en tu computadora.
2. Selecciona todos los archivos y carpetas del proyecto.
3. Haz clic derecho y selecciona **Enviar a > Carpeta comprimida (en zip)**. Nómbralo `proyecto.zip`.
   * *Evita incluir carpetas como `.git` si quieres ahorrar espacio, aunque no afecta el funcionamiento.*

### 2. Subir y Extraer en el Servidor
1. Entra a tu **cPanel** y haz clic en **Administrador de Archivos** (File Manager).
2. Dirígete a la carpeta **`public_html`** (esta es la carpeta raíz pública de tu sitio web).
   * *Si quieres que el sistema se abra directamente al entrar a tu dominio (ej: `www.tudominio.com`), súbelo aquí.*
   * *Si prefieres que esté en una subcarpeta (ej: `www.tudominio.com/gestion`), crea una carpeta llamada `gestion` dentro de `public_html` e ingresa a ella.*
3. Haz clic en el botón **Subir** (Upload) en la barra superior.
4. Selecciona tu archivo `proyecto.zip` y espera a que la barra de carga llegue al 100% (se ponga verde).
5. Regresa al Administrador de Archivos, selecciona `proyecto.zip`, haz clic derecho y elige **Extraer** (Extract).
6. Elimina el archivo `proyecto.zip` para limpiar espacio en el servidor.

---

## 🔑 Fase 5: Permisos de Escritura y Seguridad

El sistema requiere escribir archivos temporales o almacenar documentos cargados en el servidor.

1. En el Administrador de Archivos de cPanel, busca la carpeta **`uploads/`**.
2. Haz clic derecho sobre ella y selecciona **Change Permissions** (Cambiar permisos).
3. Asegúrate de que tenga permisos **`755`** (o en algunos hostings muy restrictivos, **`777`**). Esto permite al servidor PHP guardar los PDFs y archivos que suban los docentes.
4. Repite el mismo proceso para la carpeta **`logs/`** si existe en el servidor.

---

## 📧 Módulo de Correo y Tareas Programadas (Opcional)

Si utilizas el módulo de **Informe Mensual Automatizado** y **Envío de Correos**:

### 1. Configuración de SMTP
El sistema utiliza PHPMailer para mandar correos. La configuración de conexión no está en los archivos de código, sino que se almacena en la base de datos (tabla `config_envio_informes`).
* Inicia sesión en el sistema web como **Administrador**.
* Dirígete a la configuración de correo/informes y actualiza los parámetros SMTP (servidor, puerto, usuario, contraseña) con una cuenta de correo corporativa creada en tu hosting (ej: `no-reply@tudominio.com`).

### 2. Configurar la Tarea Programada (Cron Job)
Para que los informes se envíen automáticamente cada mes sin que un humano deba presionar un botón:
1. En **cPanel**, busca la herramienta **Tareas cron** (Cron Jobs).
2. En la frecuencia, configúralo para que corra, por ejemplo, una vez al día o una vez al mes.
3. En el campo de comando, ingresa la ruta de ejecución de PHP apuntando al script del cron:
   ```bash
   /usr/local/bin/php /home/miusuario/public_html/cron_enviar_informes.php
   ```
   *(Nota: La ruta `/home/miusuario/public_html/` varía dependiendo de tu hosting. cPanel usualmente te muestra tu ruta de inicio en la barra lateral del Administrador de archivos).*

---

## 💡 Consejos de Resolución de Problemas

* **Error 500 (Internal Server Error):** Generalmente se debe a una versión incorrecta de PHP en el hosting. Asegúrate de que el hosting esté configurado con **PHP 7.4 o superior (PHP 8.1 o 8.2 es altamente recomendado)**. Puedes cambiar la versión de PHP en cPanel en la opción **Seleccionar versión de PHP** (Select PHP Version).
* **Error de Conexión a Base de Datos:** Verifica dos veces las credenciales en `config/database.php`. Revisa que los prefijos del usuario y base de datos estén correctos y que la contraseña no contenga caracteres especiales conflictivos.
