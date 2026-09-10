# Monitor — Backend

API en PHP para el proyecto Monitor. Sigue la estructura y estándares
pedidos en la actividad de Backend (PSR-1, PSR-4 y PSR-12 de PHP-FIG).

## Estructura del repositorio

```
backend/
├── composer.json          # Autoload PSR-4 + classmap
├── bootstrap.php           # Autoload + manejadores de error + CORS (usado por todos los endpoints)
├── connection.php          # Conexión PDO a MySQL (clase Connection)
├── Errors.php               # Enum con el catálogo de errores {Type, Category, Description}
├── Tools.php                 # Funciones auxiliares de petición/respuesta (clase Tools)
├── ValueValidation.php        # Validaciones reutilizables (clase ValueValidation)
├── services.php                # Lógica de negocio de todos los endpoints (clase Services)
├── sessions/
│   └── Session.php               # Manejo de sesión y cookie (clase Session)
├── config/
│   └── config.php                  # Configuración leída de variables de entorno (sin secretos)
├── database/
│   ├── 01_schema.sql                 # Creación de la BD y tablas
│   └── 02_seed.sql                    # Usuario, hosts y alertas de prueba
├── python/
│   ├── colector.py                      # Colector de métricas (ver sección Python)
│   ├── requirements.txt
│   └── Dockerfile
├── login.php, registro.php, logout.php, sesion.php   # Endpoints raíz
├── hosts/       (listar.php, registrar.php)
├── metricas/    (actual.php, historial.php, ingresar.php)
├── alertas/     (listar.php, crear.php, actualizar.php, eliminar.php)
├── Dockerfile
└── docker-compose.yml
```

### Responsabilidad de cada archivo/carpeta

- **`connection.php`** — únicamente abre y reutiliza la conexión PDO a MySQL
  (host, puerto, usuario, contraseña y nombre de BD vienen de `config/config.php`).
  Si la conexión falla, responde `Errors::ERROR_BASE_DATOS` sin exponer el
  detalle real del error al cliente (ese detalle sí queda en el log del servidor).
- **`Errors.php`** — un *enum* de PHP con un caso por cada tipo de error posible.
  Cada caso guarda un JSON `{Type, Category, Description}` (`Type`: 1 = error de
  cliente, 2 = error de servidor) y sabe su propio código HTTP (`httpCode()`).
  Así, cualquier archivo que necesite reportar "sesión no iniciada" usa
  `Errors::NO_AUTENTICADO` en vez de inventar un string distinto cada vez.
- **`Tools.php`** — solo mecánica de petición/respuesta: enviar el JSON con el
  formato `{estado, respuesta, error}`, leer el body JSON, configurar CORS y
  registrar los manejadores globales de errores no controlados de PHP.
- **`ValueValidation.php`** — cada función valida un tipo de dato y **devuelve
  el valor ya tipado** (ej. `validarId($valor): int`); si el valor es inválido,
  ella misma corta la petición con el error correspondiente, para que el código
  que la llama no tenga que revisar un booleano de retorno.
- **`services.php`** — un método público por endpoint. Sigue siempre el mismo
  flujo: recibe la petición → valida con `ValueValidation` → revisa la sesión
  con `Session` (si aplica) → consulta `Connection` → responde con `Tools`.
- **`sessions/Session.php`** — crea la sesión de PHP, guarda `id_usuario` en
  `$_SESSION`, valida si hay sesión activa y la destruye en logout.
- **`config/`** — valores de configuración generales. **No contiene contraseñas
  ni tokens reales**: todo se lee de variables de entorno (ver "Variables de
  entorno" abajo).
- **`hosts/`, `metricas/`, `alertas/`** — los archivos de entrada (routers) de
  cada módulo. Son intencionalmente delgados: solo revisan el método HTTP y
  llaman al método correspondiente de `Services`.
- **`python/`** — el colector que lee CPU/RAM/Disco del equipo y se los manda
  al Backend (ver sección Python más abajo).

### Por qué `connection.php` y `services.php` no siguen el nombre-de-clase-igual-a-archivo de PSR-4

PSR-4 espera que el nombre del archivo coincida exactamente con el de la clase
que contiene (por eso `Errors.php` contiene `class Errors`, `Tools.php`
contiene `class Tools`, etc.). Como la actividad pide los archivos
`connection.php` y `services.php` en minúscula, esas dos clases
(`Connection` y `Services`) se autoloadean con la sección `classmap` de
`composer.json` en vez de `psr-4` — Composer las encuentra igual, solo que
por un mecanismo distinto (escanea el archivo en vez de derivar la ruta del
nombre de la clase).

## Variables de entorno

Ninguna contraseña ni token está escrito en el código. `docker-compose.yml`
ya trae valores de desarrollo/prueba para todas estas variables, así que
**no es necesario crear ningún archivo `.env` para levantar el proyecto tal
como está**. Si en algún momento se necesita apuntar a una base de datos
distinta (por ejemplo, en un servidor real):

| Variable | Dónde se usa | Qué contiene |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `config/config.php` | Datos de conexión a MySQL |
| `FRONTEND_ORIGIN` | `config/config.php` (CORS) | URL exacta donde corre el Frontend |

Para cambiarlas: edítalas directamente en `docker-compose.yml` (entorno de
prueba) o defínelas como variables de entorno del sistema/servidor real
donde se despliegue — nunca subiendo esos valores reales a GitHub.

## Cómo levantarlo

```bash
cd backend
docker compose up -d --build
```

Esto levanta: `db` (MySQL con el esquema y datos de prueba ya cargados),
`backend` (API PHP en `http://localhost:8080`) y tres colectores de Python
que simulan `equipo-1`, `equipo-2` y `equipo-3`.

Usuario de prueba: **admin@monitor.com** / **admin123**

## Python — colector de métricas

- **Qué hace:** lee el uso de CPU, RAM y disco del contenedor donde corre.
- **Qué recibe:** nada del Backend; se configura por variables de entorno
  (`ID_HOST`, `BACKEND_URL`, `INTERVALO_SEG`).
- **Qué genera:** un POST cada `INTERVALO_SEG` segundos a
  `metricas/ingresar.php` con `{id_host, mac, cpu, ram, disco}`.
- **Cómo se comunica con el Backend:** HTTP simple (librería `requests`).
  Se identifica con `id_host` + su propia dirección MAC (calculada con
  `uuid.getnode()`), que el Backend valida contra la tabla `hosts` antes de
  guardar la lectura. La MAC del contenedor se fija con `mac_address:` en
  `docker-compose.yml` para que siempre coincida con la sembrada en
  `database/02_seed.sql`.
- **Cómo se ejecuta:** dentro de su propio contenedor Docker (ver
  `docker-compose.yml`, servicios `colector-equipo1/2/3`).

## Preguntas de investigación (PSR) — resumen para la revisión

- **¿Qué es PHP-FIG?** Un grupo de proyectos y frameworks PHP que se ponen de
  acuerdo en estándares comunes (PHP Framework Interop Group).
- **¿Qué es un PSR?** "PHP Standard Recommendation": una recomendación
  publicada por PHP-FIG.
- **¿Para qué sirven?** Para que el código de distintos desarrolladores/equipos
  sea consistente, interoperable y fácil de leer para cualquiera.
- **¿Qué problema resuelven?** Que cada quien nombre clases, indente y organice
  el autoload de forma distinta, dificultando compartir o combinar código.
- **PSR-1:** reglas básicas — tags `<?php`, codificación UTF-8, una
  responsabilidad por archivo (o efectos secundarios o declaraciones, no
  ambos), `StudlyCaps` para clases, `camelCase` para métodos, constantes en
  mayúsculas.
- **PSR-4:** cómo mapear namespaces a rutas de archivos para el autoload,
  reemplazando los `require`/`include` manuales.
- **PSR-12:** estilo de código extendido (indentación de 4 espacios, llaves,
  espacios, orden de `declare`/`namespace`/`use`, visibilidad explícita,
  límite sugerido de 120 caracteres por línea). Reemplazó a PSR-2, que quedó
  marcado como obsoleto (`deprecated`).
- **Estándar elegido para este proyecto:** PSR-12 (con PSR-1 y PSR-4 como
  base), aplicado en todas las clases de este repo.
- **Cambios aplicados:** `declare(strict_types=1);` en todos los archivos,
  tipado explícito en parámetros y retornos, un espacio después de `if`/`foreach`,
  llaves en línea propia para clases y métodos, 4 espacios de indentación,
  y separación de responsabilidades entre `Tools`, `Errors`, `ValueValidation`,
  `Connection` y `Services`.

## Preguntas sobre `Session.php` — resumen

- **¿Qué contiene la cookie?** Solo el ID de sesión (`PHPSESSID`), un valor
  aleatorio — nunca el `id_usuario`, nombre o contraseña.
- **¿Qué identifica la cookie?** Qué archivo de sesión, del lado del servidor,
  corresponde a este navegador.
- **¿Dónde vive realmente la información de la sesión?** En el servidor
  (dentro del contenedor `backend`), no en el navegador.
- **¿Qué pasa al iniciar sesión?** Se regenera el ID de sesión
  (`session_regenerate_id`, evita *session fixation*) y se guardan
  `id_usuario`, `nombre` y `email` en `$_SESSION`.
- **¿Qué pasa al cerrar sesión?** Se vacía `$_SESSION`, se expira la cookie
  en el navegador y se destruye el archivo de sesión del servidor.
- **¿Qué pasa si la sesión no existe?** `Session::exigirSesion()` responde
  `Errors::NO_AUTENTICADO` (401) y corta la ejecución ahí mismo.
- **¿Cómo se evita una sesión inválida?** `httponly` impide que JavaScript
  lea la cookie (mitiga XSS), `samesite=Lax` limita el envío entre sitios
  (mitiga CSRF básico), y la regeneración de ID en login evita que un ID de
  sesión robado/adivinado antes del login siga siendo válido después.
