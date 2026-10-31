# Monitor — Backend

API en PHP para el proyecto Monitor. Sigue la estructura y estándares
pedidos en la actividad de Backend (PSR-1, PSR-4 y PSR-12 de PHP-FIG).

## Integrantes

* **Axel Edrey Zaragoza Bravo**
* *Nombre de tu compañero/a*

## Estructura del repositorio

```
backend/
├── composer.json            # Autoload PSR-4: Monitor\ => src/
├── bootstrap.php             # Autoload + manejadores de error + CORS (lo usa todo endpoint)
├── src/
│   ├── Errors.php             # Enum de errores {Type, Category, Description} + responder() + manejadores globales
│   ├── Tools.php               # Auxiliares de petición/respuesta: leerJsonBody, bien, enviarJson, CORS
│   ├── ValueValidation.php     # Validaciones reutilizables
│   ├── Connection.php          # Conexión PDO a MySQL
│   ├── Sessions/Session.php    # Sesión y cookie de sesión
│   ├── Repositories/           # TODO el SQL (Usuario, Host, Lectura, Alerta)
│   └── Services/               # Lógica de cada endpoint (Auth, Host, Metrica, Alerta)
├── config/config.php          # Configuración leída de variables de entorno
├── database/                  # Esquema y datos de prueba
├── python/                    # Colector de métricas
├── auth/                     # login.php, registro.php, logout.php, sesion.php
├── hosts/  metricas/  alertas/                       # Endpoints por dominio
├── Dockerfile
└── docker-compose.yml
```

**Flujo de una petición:** endpoint (`hosts/listar.php`) → `Services\*` → `ValueValidation` → `Sessions\Session` (si aplica) → `Repositories\*` → `Connection` → BD → `Tools::bien()` / `Errors::X->responder()`.

### Responsabilidad de cada archivo/carpeta

- **`src/Connection.php`** — únicamente abre y reutiliza la conexión PDO a MySQL
  (host, puerto, usuario, contraseña y nombre de BD vienen de `config/config.php`).
  Si la conexión falla, responde `Errors::ERROR_BASE_DATOS` sin exponer el
  detalle real del error al cliente (ese detalle sí queda en el log del servidor).
- **`src/Errors.php`** — un *enum* de PHP con un caso por cada tipo de error posible.
  Cada caso guarda un JSON `{Type, Category, Description}` (`Type`: 1 = error de
  cliente, 2 = error de servidor) y sabe su propio código HTTP (`httpCode()`).
  Además concentra la **respuesta de error** (`Errors::X->responder()`) y los
  **manejadores globales** de excepciones/errores de PHP (`registrarManejadores()`),
  para que el Backend nunca responda HTML.
- **`src/Tools.php`** — solo auxiliares de petición/respuesta: enviar el JSON con
  el formato `{estado, respuesta, error}` (`enviarJson`, `bien`), leer el body JSON
  (`leerJsonBody`) y configurar CORS (`configurarCors`).
- **`src/ValueValidation.php`** — cada función valida un tipo de dato y **devuelve
  el valor ya tipado** (ej. `validarId($valor): int`); si el valor es inválido,
  ella misma corta la petición con el error correspondiente, para que el código
  que la llama no tenga que revisar un booleano de retorno.
- **`src/Sessions/Session.php`** — crea la sesión de PHP, guarda `id_usuario` en
  `$_SESSION`, valida si hay sesión activa y la destruye en logout.
- **`src/Repositories/*`** — **todo el SQL** del proyecto, una clase por tabla
  (`UsuarioRepository`, `HostRepository`, `LecturaRepository`, `AlertaRepository`).
  No validan ni responden: solo consultan y devuelven datos.
- **`src/Services/*`** — la lógica de cada endpoint, una clase por dominio
  (`AuthService`, `HostService`, `MetricaService`, `AlertaService`). Siguen siempre
  el mismo flujo: validar con `ValueValidation` → revisar sesión con `Session`
  (si aplica) → consultar el `Repository` → responder con `Tools`/`Errors`.
- **`auth/`, `hosts/`, `metricas/`, `alertas/`** — los archivos de entrada
  (routers) de cada módulo. Son intencionalmente delgados: solo revisan el método
  HTTP y llaman al método correspondiente de `Services`.
- **`config/`** — valores de configuración generales. **No contiene contraseñas
  ni tokens reales**: todo se lee de variables de entorno (ver "Variables de
  entorno" abajo).
- **`python/`** — el colector que lee CPU/RAM/Disco del equipo y se los manda
  al Backend (ver sección Python más abajo).

### Por qué los endpoints no están dentro de `src/`

`src/` contiene solo clases (se cargan por autoload PSR-4: `Monitor\Services\AuthService`
está en `src/Services/AuthService.php`). Los endpoints (`auth/`, `hosts/`, ...) quedan
fuera porque son los archivos que el servidor web expone por URL y que el Frontend y
el colector de Python llaman directamente (por ejemplo `/hosts/listar.php`).

## Catálogo de endpoints

Todos los endpoints responden JSON. La URL base es `http://localhost:8080`.

| Endpoint | Método | Body / Parámetros | Sesión | Éxito | Descripción |
|---|---|---|---|---|---|
| `/auth/login.php` | POST | `{"email", "contrasena"}` | Pública | 200 | Valida credenciales e inicia sesión |
| `/auth/registro.php` | POST | `{"nombre", "email", "contrasena"}` | Pública | 201 | Crea una cuenta nueva |
| `/auth/logout.php` | POST | — | Requerida | 200 | Cierra y destruye la sesión |
| `/auth/sesion.php` | GET | — | Requerida | 200 | Devuelve el usuario de la sesión activa |
| `/hosts/listar.php` | GET | — | Requerida | 200 | Lista los equipos del usuario |
| `/hosts/registrar.php` | POST | `{"nombre_host", "ip_direccion", "ip_mac"}` | Requerida | 201 | Registra un equipo |
| `/hosts/actualizar.php` | PUT/POST | `{"id_host", "nombre_host", "ip_direccion", "ip_mac"}` | Requerida | 200 | Edita un equipo propio |
| `/metricas/actual.php` | GET | `id_host` | Requerida | 200 | Última lectura del equipo |
| `/metricas/historial.php` | GET | `id_host`, `inicio`, `fin` (Y-m-d), `max_puntos` (opcional) | Requerida | 200 | Promedios por intervalo en un rango |
| `/metricas/ingresar.php` | POST | `{"id_host", "mac", "cpu", "ram", "disco"}` | Colector (id_host + MAC) | 201 | Guarda una lectura |
| `/alertas/listar.php` | GET | `id_host` | Requerida | 200 | Lista los umbrales del equipo |
| `/alertas/crear.php` | POST | `{"id_host", "metrica", "valor_limite"}` | Requerida | 201 | Crea un umbral (`metrica`: cpu, ram o disco) |
| `/alertas/actualizar.php` | PUT/POST | `{"id_alerta", "valor_limite", "activo"}` | Requerida | 200 | Modifica un umbral propio |
| `/alertas/eliminar.php` | POST/DELETE | `{"id_alerta"}` | Requerida | 200 | Elimina un umbral propio |

## Formato de las respuestas

Respuesta exitosa (200 / 201):

```json
{ "estado": "bien", "respuesta": { "...": "..." } }
```

Respuesta de error:

```json
{
  "estado": "mal",
  "respuesta": null,
  "error": { "Type": 1, "Category": 3, "Description": "No hay una sesion iniciada." }
}
```

| Error (`Errors::`) | HTTP | Cuándo ocurre |
|---|---|---|
| `VALIDACION`, `PARAMETRO_FALTANTE` | 422 | Campos inválidos o faltantes (incluye contraseña incorrecta en login) |
| `NO_AUTENTICADO`, `SESION_INVALIDA` | 401 | No hay sesión iniciada o ya expiró |
| `SIN_PERMISO` | 403 | El host/alerta existe pero pertenece a otro usuario |
| `NO_ENCONTRADO` | 404 | El registro no existe (incluye usuario inexistente en login) |
| `SERVICIO_NO_ENCONTRADO` | 404 | Método HTTP no permitido en ese endpoint |
| `YA_EXISTE` | 409 | Correo o MAC ya registrados |
| `ERROR_BASE_DATOS`, `ERROR_INTERNO` | 500 | Fallo de MySQL o error no controlado |

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

Esto levanta: `db` (MySQL con el esquema y el usuario administrador) y
`backend` (API PHP en `http://localhost:8080`). Ya no se siembran equipos:
cada persona crea su cuenta y registra los suyos (ver siguiente sección).

Usuario administrador: **admin@monitor.com** / **admin123**

Los 3 colectores simulados (`equipo-1/2/3`) quedaron como opcionales, solo
para demostraciones: `docker compose --profile demo up -d` (y cargar antes
`database/demo_equipos.sql`).

**Si tu base de datos ya existía** (el seed solo corre con la BD vacía) y quieres
quitar los 3 equipos de prueba que estaban a nombre del administrador:

```bash
docker compose exec -T db mysql -uroot -proot_pass monitor_db < database/limpiar_demo.sql
```

## Cuentas y equipos propios

- **Crear cuenta:** pantalla `/registro` del Frontend (usa `auth/registro.php`).
- **Aislamiento:** cada equipo pertenece a un solo usuario. Todos los endpoints
  (`hosts/listar`, `metricas/*`, `alertas/*`) filtran o validan contra el
  `id_usuario` de la sesión (`exigirHostDelUsuario`), así que una cuenta nunca
  ve ni consulta equipos de otra, incluyendo la del administrador. Pedir un
  `id_host` ajeno responde 403.
- **Registrar un equipo real** (sin Docker, en la máquina a monitorear):
  1. `cd Backend/python && pip install -r requirements.txt`
  2. `python colector.py --info` → muestra nombre, IP y MAC.
  3. En el sitio, pantalla **Equipos** → capturar esos datos → aparece el `ID`.
  4. Correr el colector con ese ID:
     `ID_HOST=<id> BACKEND_URL=http://<ip-del-backend>:8080/metricas/ingresar.php python colector.py`
     (en PowerShell: `$env:ID_HOST="<id>"; $env:BACKEND_URL="http://<ip>:8080/metricas/ingresar.php"; python colector.py`).
     Si el colector está en otra computadora que el Backend, usar la IP de la
     máquina del Backend (y permitir el puerto 8080 en su firewall).

## Historial con muchos datos

`metricas/historial.php` no envía las lecturas crudas (un equipo con lectura
cada 5 s genera ~17,000 filas al día). Divide el rango pedido en máximo
`max_puntos` intervalos (600 por defecto, entre 50 y 2000) y devuelve el
promedio de cada uno:

```json
{ "intervalo_segundos": 144, "total_lecturas": 17280,
  "datos": [ { "fecha_hora": "...", "cpu": "12.50", "ram": "48.10", "disco": "61.00" } ] }
```

Se cubre siempre todo el rango, pero la respuesta pesa unos KB. El Frontend
muestra esos puntos con una barra inferior para acercarse y recorrer el periodo.

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

## Postman

Colección: `[Link o archivo .json de la colección de Postman — pendiente de agregar]`

Casos de prueba y resultado esperado (marca la columna "Resultado" al probarlos
y guarda la captura en `[carpeta o link pendiente]`):

| # | Caso | Endpoint | Entrada | HTTP esperado | Resultado |
|---|---|---|---|---|---|
| 1 | Login correcto | POST `/auth/login.php` | email y contraseña válidos | 200 | [ ] |
| 2 | Contraseña incorrecta | POST `/auth/login.php` | email válido, contraseña errónea | 422 | [ ] |
| 3 | Campos vacíos en login | POST `/auth/login.php` | email o contraseña faltantes | 422 | [ ] |
| 4 | Usuario inexistente | POST `/auth/login.php` | email no registrado | 404 | [ ] |
| 5 | Endpoint protegido sin sesión | GET `/hosts/listar.php` | sin cookie de sesión | 401 | [ ] |
| 6 | Lectura actual | GET `/metricas/actual.php?id_host=<id>` | con sesión | 200 | [ ] |
| 7 | Host ajeno | GET `/metricas/actual.php?id_host=<id de otro usuario>` | con sesión | 403 | [ ] |
| 8 | Historial válido | GET `/metricas/historial.php` | `id_host`, `inicio`, `fin` válidos | 200 | [ ] |
| 9 | Historial con rango inválido | GET `/metricas/historial.php` | `inicio` mayor que `fin` | 422 | [ ] |
| 10 | Alta de alerta | POST `/alertas/crear.php` | `metrica` y `valor_limite` válidos | 201 | [ ] |
| 11 | Alerta con formato inválido | POST `/alertas/crear.php` | `valor_limite` como texto | 422 | [ ] |
| 12 | Modificar alerta | POST `/alertas/actualizar.php` | `id_alerta` propio | 200 | [ ] |
| 13 | Eliminar alerta | POST `/alertas/eliminar.php` | `id_alerta` propio | 200 | [ ] |

## Problemas conocidos / consideraciones especiales

- No se permite crear dos umbrales activos para la misma métrica en el mismo
  equipo; el Frontend valida esto antes de llamar a `alertas/crear.php`, pero
  el Backend no lo rechaza a nivel de base de datos (no hay una restricción
  `UNIQUE` sobre `(id_host, metrica)` en `alertas_umbrales`, ya que sí se
  permite tener el umbral inactivo y uno nuevo activo). Queda documentado
  como una decisión de diseño, no como una limitación de la base de datos.
- El puerto publicado de MySQL (`3306` por defecto en `docker-compose.yml`)
  puede chocar con otro MySQL corriendo en la misma máquina; si eso pasa,
  cambia el primer número del mapeo de puertos (por ejemplo a `3307:3306`)
  sin tocar nada más.

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

## Preguntas teóricas para la defensa del Backend

- **¿Cómo sabe el Backend que una petición viene de un usuario con sesión iniciada?**
  El navegador envía la cookie `PHPSESSID`; `Session::exigirSesion()` busca el
  archivo de sesión correspondiente en el servidor y revisa que tenga `id_usuario`.
  Si no existe, responde `Errors::NO_AUTENTICADO` (401) y corta la ejecución.
- **¿Por qué se usa PDO y no SQL concatenado?** PDO usa sentencias preparadas con
  parámetros enlazados (`:id_host`), por lo que los datos del usuario nunca se
  mezclan con el texto del SQL y se evita la inyección SQL. Todo el SQL vive en
  `Repositories/`.
- **¿Por qué se separó `services.php` en Services y Repositories?** Porque mezclaba
  validación, sesión, SQL, procesamiento y respuestas. Ahora cada capa tiene una
  sola responsabilidad: el Service decide qué hacer y el Repository solo habla con la BD.
- **¿Cómo se evita que un usuario vea equipos de otro?** `HostService::exigirHostDelUsuario()`
  compara el dueño del host con el usuario de la sesión: 404 si no existe, 403 si es de otro.
- **¿Cómo se comunica Python con el Backend?** El colector lee CPU/RAM/Disco con
  `psutil` y hace un POST a `metricas/ingresar.php` con `id_host` y su MAC; el Backend
  solo guarda la lectura si esa pareja coincide con la tabla `hosts`.
- **¿Por qué el historial devuelve promedios y no todas las lecturas?** Un equipo genera
  ~17,000 lecturas al día; el Backend divide el rango en máximo `max_puntos` intervalos
  y promedia cada uno, así la respuesta pesa pocos KB.
- **¿Qué ganó el proyecto con la reorganización?** Código sin repetir, un archivo por
  responsabilidad, autoload PSR-4 con una sola regla y cambios más fáciles de ubicar:
  si cambia una tabla se toca un Repository, si cambia una regla de negocio un Service.
