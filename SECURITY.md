# Seguridad

## Medidas aplicadas

| Riesgo | Medida |
|---|---|
| Inyección SQL | Consultas preparadas con PDO |
| Contraseñas | `password_hash`, de 12 a 72 caracteres; cambiarla cierra las demás sesiones |
| Fuerza bruta | En 15 minutos: 5 fallos por correo e IP, 30 por IP y 20 por correo |
| Enumeración de cuentas | Mismo mensaje y verificación de hash aunque el correo no exista |
| Sesiones | Tokens aleatorios guardados como SHA-256, con caducidad de 8 horas y revocación |
| Acceso a datos ajenos | Comprobación por rol y propietario; un ticket ajeno responde 404 |
| Claves de agente | Una por equipo, guardadas como SHA-256 y mostradas una sola vez |
| XSS y clickjacking | Plantillas Vue con escape, CSP y `X-Frame-Options` |
| Métricas | Token obligatorio y bloqueo de `/metrics` en el proxy |
| Trazabilidad | Registro de actividad de altas, cambios y accesos, visible para administración |
| Agentes | HTTPS obligatorio, un informe cada 30 s y retención de 90 días |
| Copias | Archivos solo legibles por su propietario |
| Secretos | Variables de entorno, fuera del repositorio |
| Infraestructura | SSH limitado a una IP, IMDSv2 y disco cifrado |

## Análisis dinámico

El flujo `Análisis OWASP ZAP` analiza la aplicación en un entorno temporal y se lanza a mano desde la pestaña Actions. Es un análisis pasivo básico sin sesión iniciada.

Primera ejecución: 64 comprobaciones superadas, 0 fallos y 4 avisos.

| Aviso | Decisión |
|---|---|
| CSP con `style-src unsafe-inline` | Corregido: la política ya no lo permite |
| Falta `Cross-Origin-Embedder-Policy` | Corregido: se envía `require-corp` |
| Contenido almacenable en caché | Aceptado: afecta a archivos estáticos públicos; la API responde `no-store` |
| Aplicación web moderna | Informativo: indica que conviene un análisis con navegador |
