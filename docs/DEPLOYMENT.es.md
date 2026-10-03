# Despliegue en producción

🇬🇧 [English version](DEPLOYMENT.md)

**El setup de Docker de este repositorio es para desarrollo local**: el código se monta como volumen, Symfony se ejecuta en el entorno `dev` y hay una cuenta de reclutador de demo. Esta página enumera lo que necesitaría un despliegue en producción. Nada de esto hace falta para ejecutar o evaluar la PoC.

## Piezas en ejecución

| Pieza | Función | Escalado |
|---|---|---|
| **app** | HTTP: páginas, commands y queries | Sin estado una vez compartidas las sesiones y los contadores del rate limit (ver abajo): se añaden instancias detrás de un balanceador |
| **worker** | `messenger:consume async`: el enriquecimiento con IA y los eventos entre contextos | Uno o más procesos; se añaden más cuando crece la cola |
| **PostgreSQL 16** | Datos de la aplicación y el failure transport | Necesita la extensión `pg_trgm` (la crean las migraciones; una base de datos gestionada debe permitirla) |
| **RabbitMQ** | Eventos entre bounded contexts | Un broker gestionado o un clúster |

La app y el worker usan **la misma imagen** con un comando distinto.

## Construcción

Una imagen de producción se diferencia de la de desarrollo en que:

- **copia el código dentro** en lugar de montarlo, e instala las dependencias con `composer install --no-dev --optimize-autoloader`;
- se ejecuta con `APP_ENV=prod` y `APP_DEBUG=0`, con la caché precalentada al construir (`cache:warmup`);
- **compila los assets**: `tailwind:build --minify` y `asset-map:compile`, para servirlos como ficheros estáticos.

## Configuración y secretos

| Variable | Valor en producción |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `prod` / `0` |
| `APP_SECRET` | Un valor aleatorio largo, guardado como secreto |
| `DATABASE_URL` | La base de datos de producción, con las credenciales como secreto |
| `MESSENGER_TRANSPORT_DSN` | El broker de producción, con su propio usuario (no `guest`) |
| `SYMFONY_TRUSTED_PROXIES` | El rango de direcciones del balanceador, para detectar bien la IP del cliente (la usa el rate limiter) y el HTTPS |
| `APPLY_RATE_LIMIT` | Umbral contra abusos del formulario de candidatura |

Los secretos vienen de la plataforma (variables de entorno, gestor de secretos) o del vault de secretos de Symfony, nunca de un fichero `.env` versionado.

## Pasos de cada release

En cada despliegue:

1. **Construir y publicar** la imagen.
2. **Ejecutar las migraciones** antes de que el código nuevo reciba tráfico: `bin/console doctrine:migrations:migrate --no-interaction`. Deben seguir siendo compatibles con la versión que todavía está en marcha durante el despliegue.
3. **Preparar el broker** en el primer despliegue, o cuando cambien los transportes: `bin/console messenger:setup-transports`.
4. **Desplegar** las instancias de la app.
5. **Reiniciar los workers** para que ejecuten el código nuevo: `bin/console messenger:stop-workers` hace que cada uno termine el mensaje en curso y salga, y el gestor de procesos lo vuelve a arrancar.

## Workers

- Ejecutarlos bajo un gestor de procesos (systemd, Supervisor, Kubernetes) que los reinicie cuando terminen.
- Reciclarlos con regularidad con `--time-limit` y `--memory-limit`, como ya hace el setup de desarrollo con `--time-limit=3600`.
- **Vigilar el failure transport**: un mensaje ahí significa que un análisis falló después de sus reintentos. Conviene una alerta cuando no esté vacío; se inspecciona con `messenger:failed:show` y se reintenta con `messenger:failed:retry`.

## Varias instancias de la app

- **Las sesiones** se guardan por defecto en ficheros de cada instancia: hay que moverlas a un almacén compartido (Redis o la base de datos), o los reclutadores perderían la sesión cuando el balanceador cambie de instancia.
- **Los contadores del rate limit** viven en la caché de la aplicación: el pool `cache.rate_limiter` debe apuntar a Redis para que todas las instancias los compartan.
- **Health check**: `/health` responde `{"status":"ok"}` para el balanceador. Solo comprueba que PHP responde, no la base de datos ni el broker.
- **Los logs** van a la salida del contenedor (stderr), listos para el recolector de logs de la plataforma.

## Antes de salir a producción

Son carencias del producto más que pasos de despliegue; se explican en [Arquitectura → Trade-offs y próximos pasos](ARCHITECTURE.es.md#trade-offs-y-próximos-pasos):

- **Un almacén de usuarios real** (tabla de usuarios o SSO) en lugar de la cuenta de demo en memoria, y quitar las credenciales de demo que muestra la página de login.
- **Un adaptador de LLM real** que implemente `CvAnalyzer`, con su API key como secreto.
- **Un transactional outbox**, para no perder ningún evento si el broker cae justo después de un commit.
- **HTTPS** (FrankenPHP puede obtener los certificados automáticamente, o lo termina el balanceador) y **copias de seguridad de la base de datos**, que cubren también el failure transport.
