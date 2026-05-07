<div align="center">
  <img src="html/imgs/logo.png" alt="ServiJam 3D" width="100"/>
  <h1>ServiJam 3D</h1>
  <p>Panel web para gestión de solicitudes de impresión 3D, pensado para correr en una Raspberry Pi junto a Klipper/Moonraker.</p>

  ![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)
  ![MariaDB](https://img.shields.io/badge/MariaDB-11-003545?style=flat&logo=mariadb&logoColor=white)
  ![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat&logo=docker&logoColor=white)
  ![Apache](https://img.shields.io/badge/Apache-2-D22128?style=flat&logo=apache&logoColor=white)
</div>

---

## ✨ Características

- 📤 **Subida de archivos G-code** con validación de tipo y tamaño (hasta 512 MB)
- 👤 **Sistema de usuarios** con registro, login y sesiones seguras
- 🔐 **Panel de administración** para gestionar solicitudes (aceptar, rechazar, marcar como completado)
- 📧 **Notificaciones por email** automáticas con plantillas HTML via PHPMailer + Gmail SMTP
- 🖨️ **Control de impresora** integrado con Moonraker/Klipper via API REST
- 🎨 **Interfaz oscura moderna** con tema morado, responsive y sin dependencias JS externas
- 🐋 **Despliegue con Docker Compose** en un solo comando

---

## 🧱 Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.2 + Apache |
| Base de datos | MariaDB 11 |
| Email | PHPMailer + Gmail SMTP |
| Impresora | Moonraker API (Klipper) |
| Infraestructura | Docker Compose |
| Frontend | HTML + CSS puro (sin frameworks) |

---

## 🚀 Instalación

### Requisitos

- Docker + Docker Compose
- Klipper con Moonraker corriendo (en la misma máquina o accesible en red)

### Pasos

```bash
# 1. Clona el repositorio
git clone https://github.com/JuanCarlosloss/ServiJam3D.git
cd ServiJam3D

# 2. Crea tu configuración a partir del ejemplo
cp html/config.example.php html/config.php

# 3. Edita config.php con tus credenciales de base de datos y SMTP
nano html/config.php

# 4. Levanta los contenedores
docker compose up -d --build
```

La aplicación estará disponible en `http://localhost:8081`  
phpMyAdmin en `http://localhost:8082`

---

## ⚙️ Configuración

Edita `html/config.php` (no se sube al repositorio):

```php
define('DB_PASS',   'tu_contraseña_bd');
define('SMTP_USER', 'tu_correo@gmail.com');
define('SMTP_PASS', 'tu_app_password_gmail');  // Contraseña de aplicación de Google
define('ADMIN_EMAIL', 'tu_correo@gmail.com');
```

> Para el SMTP necesitas una **contraseña de aplicación** de Google (no tu contraseña normal).  
> Actívala en: Google Account → Seguridad → Verificación en dos pasos → Contraseñas de aplicación

---

## 📁 Estructura del proyecto

```
ServiJam3D/
├── docker-compose.yaml       # Servicios: web, db, phpmyadmin
├── Dockerfile                # PHP 8.2 + Apache + Composer
├── html/
│   ├── config.example.php    # Plantilla de configuración (sin credenciales)
│   ├── index.php             # Login
│   ├── register.php          # Registro de usuarios
│   ├── user.php              # Panel de usuario
│   ├── admin.php             # Panel de administración
│   ├── upload.php            # Subida de G-code
│   ├── reprint.php           # Solicitar reimpresión
│   ├── action.php            # API: aceptar/rechazar/completar trabajos
│   ├── printer_control.php   # API: control de impresora via Moonraker
│   ├── printer_status.php    # API: estado de la impresora
│   ├── style.css             # Estilos globales
│   ├── imgs/                 # Logos e imágenes
│   └── uploads/              # Archivos G-code subidos (ignorado en git)
└── db/                       # Scripts de inicialización de la BD
```

---

## 🖼️ Capturas

> *Próximamente*

---

## 📄 Licencia

Este proyecto es de uso personal/privado. Contacta con el autor para cualquier uso.