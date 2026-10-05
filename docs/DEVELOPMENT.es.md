# Guía de desarrollo

🇬🇧 [English version](DEVELOPMENT.md)

Cómo trabajar en el proyecto día a día. Para arrancarlo por primera vez, ver el [README](../README.md#quick-start); para cómo está organizado el código, ver [Arquitectura](ARCHITECTURE.es.md).

Todo se ejecuta en Docker: PHP, Composer y los tests nunca se ejecutan en el host.

## Comandos habituales

| Comando | Qué hace |
|---|---|
| `make init` | Primera vez: construye, arranca, instala dependencias, crea las bases de datos, carga los datos de demo y compila los assets |
| `make up` / `make down` | Arranca / para los contenedores |
| `make test` | Todos los tests (`make test-unit`, `make test-integration` para una sola suite) |
| `make qa` | Estándar de código (sin modificar), PHPStan nivel max y Deptrac |
| `make cs-fix` | Corrige el estándar de código |
| `make db` | Crea las bases de datos y ejecuta las migraciones pendientes, en dev **y** en test |
| `make fixtures` | Recarga los datos de demo: **borra** todas las candidaturas creadas a mano |
| `make assets` / `make css-watch` | Compila el CSS una vez / con cada cambio en las plantillas |
| `make logs` | Sigue los logs de la app y del worker (nivel info; el detalle completo, en `var/log/dev.log`) |
| `make sh` | Abre una shell en el contenedor de la aplicación |

Para ejecutar solo una parte de los tests:

```bash
docker compose exec app php bin/phpunit --filter BrowseJobApplicationsTest
```

## Configuración

Los valores por defecto están en `.env`; los cambios locales van en `.env.local` (ignorado por git). Los tests usan `.env.test`. Las variables que fija `compose.yaml` son variables de entorno reales y tienen prioridad sobre cualquier fichero `.env`.

| Variable | Por defecto | Para qué sirve |
|---|---|---|
| `DATABASE_URL` | PostgreSQL del contenedor `database` (la fija `compose.yaml`) | Base de datos de la aplicación (los tests añaden el sufijo `_test`) |
| `MESSENGER_TRANSPORT_DSN` | RabbitMQ del contenedor `rabbitmq` (la fija `compose.yaml`) | Cola de los eventos entre contextos |
| `MOCK_LLM_LATENCY_MS` | `1500` (`0` en tests) | Cuánto "piensa" el LLM simulado, para que la interfaz muestre *Analysing…* |
| `MOCK_LLM_FAILURE_RATE` | `0` | Proporción de llamadas al LLM simulado que fallan al azar (`0`–`1`), para probar los reintentos |
| `APPLY_RATE_LIMIT` | `5` | Candidaturas válidas aceptadas por IP cada 15 minutos |

La aplicación web lee `.env.local` en la siguiente petición; **el worker, solo al reiniciarse** (ver más abajo).

## Hacer cambios

**Dónde va el código nuevo.** Primero decide el bounded context y la capa: reglas de negocio en `Domain`, casos de uso en `Application` (una carpeta por command o query), y todo lo que toque Symfony, Doctrine o RabbitMQ en `Infrastructure`. `make qa` falla (Deptrac) si una dependencia apunta en la dirección equivocada. La [estructura de carpetas](ARCHITECTURE.es.md#capas-y-contextos) muestra dónde vive cada tipo de clase.

**Cambiar la base de datos.** El mapeo es XML, en `src/*/Infrastructure/Persistence/Doctrine/Mapping`. Después de cambiarlo:

```bash
docker compose exec app php bin/console doctrine:migrations:diff   # genera migrations/VersionXXXX.php
make db                                                            # la aplica en dev y en test
```

Revisa la migración generada antes de hacer commit.

**Cambiar la interfaz.** Las plantillas son Twig, con las piezas reutilizables como Twig Components en `templates/components/`; el comportamiento son controladores Stimulus pequeños en `assets/controllers/`. Deja `make css-watch` en marcha mientras editas para que Tailwind detecte las clases nuevas. Las convenciones de interfaz están en [`.claude/rules/ui-design.md`](../.claude/rules/ui-design.md).

## La parte asíncrona

El contenedor `worker` ejecuta `messenger:consume async` y se encarga del enriquecimiento con IA.

- **No recarga el código.** Después de cambiar un subscriber, un handler que se ejecute en el worker, cualquier cosa de `Screening` o `.env.local`, reinícialo: `docker compose restart worker`.
- **Para observarlo**: `make logs`; las colas en http://localhost:15672 (guest / guest).
- **Fallos**: un mensaje que sigue fallando se reintenta 3 veces (1 s, 2 s, 4 s) y después se guarda en el failure transport (la tabla `messenger_messages`):

```bash
docker compose exec app php bin/console messenger:failed:show        # lista
docker compose exec app php bin/console messenger:failed:show 1 -vv  # un mensaje, con su excepción
docker compose exec app php bin/console messenger:failed:retry       # reintentar
```

**Probar una caída y su recuperación**: pon `MOCK_LLM_FAILURE_RATE=1` en `.env.local`, reinicia el worker y envía una candidatura; acabará en *AI unavailable*. Vuelve a `0`, reinicia el worker y ejecuta `messenger:failed:retry`: la candidatura recibe su resumen y su score. (Un CV que contenga `[simulate-llm-failure]` siempre falla, así que sirve para el camino de error pero no para la recuperación.)

## Problemas habituales

| Síntoma | Solución |
|---|---|
| `make init` falla: puerto en uso | Algo en el host usa el 8080, el 5433 o el 15672. Páralo o cambia el puerto publicado en `compose.yaml`. |
| Una candidatura se queda en *Analysing…* | El worker no está en marcha o está atascado: `docker compose ps` y después `make logs`. |
| Un cambio en el código del worker no tiene efecto | Reinicia el worker: `docker compose restart worker`. |
| *Too many applications from your network* | El rate limit (5 cada 15 min). Sube `APPLY_RATE_LIMIT` en `.env.local`. |
| *Too many failed login attempts* | El throttling del login: espera un minuto. |
| Las clases nuevas de Tailwind no tienen estilo | `make assets` (o deja `make css-watch` en marcha). |
| Los tests fallan después de traer commits nuevos | Una migración nueva: `make db`. Una dependencia nueva: `docker compose exec app composer install`. |
