# Seguridad

## Medidas aplicadas

| Riesgo | Medida |
|---|---|
| Inyección SQL | Consultas preparadas con PDO |
| Contraseñas | `password_hash`, mínimo de 12 caracteres |
| Fuerza bruta | Bloqueo tras 5 fallos en 15 minutos por correo e IP |
| Enumeración de cuentas | Mismo mensaje y verificación de hash aunque el correo no exista |
| Sesiones | Tokens aleatorios guardados como SHA-256, con caducidad de 8 horas y revocación |
| Acceso a datos ajenos | Comprobación por rol y propietario; un ticket ajeno responde 404 |
| Claves de agente | Una por equipo, guardadas como SHA-256 y mostradas una sola vez |
| XSS y clickjacking | Plantillas Vue con escape, CSP y `X-Frame-Options` |
| Métricas | Token obligatorio y bloqueo de `/metrics` en el proxy |
| Secretos | Variables de entorno, fuera del repositorio |
| Infraestructura | SSH limitado a una IP, IMDSv2 y disco cifrado |

## Análisis dinámico

El flujo `Análisis OWASP ZAP` analiza la aplicación desplegada en un entorno temporal. Se lanza a mano desde la pestaña Actions.

**Estado:** pendiente de la primera ejecución. Aquí se documentarán los hallazgos y sus correcciones.
