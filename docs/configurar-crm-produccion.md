# Dónde obtener la configuración CRM de producción

## Archivo del servidor

Las variables van en el archivo `.env` de Laravel **en el servidor que sirve `app.sefaruniversal.com`**. La carpeta correcta contiene `artisan`, `composer.json`, `app/`, `config/` y `public/`. El `.env` suele estar oculto: activa «Mostrar archivos ocultos» en el administrador de archivos del hosting.

Los informes de error de producción sitúan Laravel en:

```text
/home/xzysctv/gitproyects/app/AppSefarUniversal
```

La conexión SSH indicada para ese alojamiento es:

```bash
ssh xzysctv-alejandrod@ssh.cluster100.hosting.ovh.net
```

Después de iniciar sesión en tu terminal, comprueba la ruta y la presencia del archivo antes de editar:

```bash
cd /home/xzysctv/gitproyects/app/AppSefarUniversal
pwd
ls -l artisan .env
```

Si aparecen ambos archivos, puedes consultar qué archivo de entorno utiliza esa instalación sin mostrar sus valores:

```bash
php artisan tinker --execute="dump(base_path(), app()->environmentFilePath());"
```

Para editar el archivo de entorno:

```bash
nano .env
```

En `nano`, guarda con `Ctrl+O`, confirma con `Enter` y sal con `Ctrl+X`. Actualiza únicamente las variables CRM de esta guía y conserva las demás entradas. Si la ruta no existe, comprueba la raíz del dominio en el panel del alojamiento; si termina en `/public`, la carpeta Laravel normalmente es su carpeta superior.

El `.env` está excluido de Git. El despliegue actual descarga el código mediante `git pull`; no transfiere las variables del equipo local al servidor. Si el hosting administra las variables mediante un panel de entorno, utiliza ese panel para la aplicación correspondiente.

## HubSpot

Abre [Api-Call-Sefar → Autenticación](https://app.hubspot.com/private-apps/20053496/1512588/auth).

- `CRM_HUBSPOT_PORTAL_ID`: `20053496`, el identificador del portal que ya estamos configurando.
- `CRM_HUBSPOT_CLIENT_SECRET`: en **Secreto del cliente**, pulsa **Mostrar secreto** y después **Copiar**. Ese campo está debajo de «Token de acceso». Pega el valor directamente en la configuración del servidor.
- `CRM_HUBSPOT_SIGNATURE_VERSION`: conserva `v3` para la comprobación inicial. El receptor admite también `v1` si la entrega real del emisor legacy exige configurar esa versión expresamente; no hace una degradación automática.

## Teamleader

### Secreto del webhook

`CRM_TEAMLEADER_WEBHOOK_SECRET` es una clave nueva compartida para este destino. Puedes generarla en tu terminal privada con:

```bash
openssl rand -hex 32
```

Copia el resultado completo de 64 caracteres en esa variable. Conserva el valor para el registro del webhook; si ya existe uno configurado, úsalo y evita cambiarlo durante la comprobación. No publiques el resultado en el chat ni en Git.

### Identificador de la cuenta

`CRM_TEAMLEADER_ACCOUNT_ID` corresponde a `data.account.id` de la respuesta de [`POST users.me`](https://developer.focus.teamleader.eu/docs/api/users-me).

Puedes consultarlo utilizando la conexión Teamleader que ya tiene la App. En la terminal del **servidor**, desde la carpeta donde está `artisan`, ejecuta:

```bash
php artisan tinker --execute="dump(\Illuminate\Support\Facades\Http::withToken(app(\App\Services\TeamleaderService::class)->getAccessToken())->post('https://api.focus.teamleader.eu/users.me')->throw()->json('data.account.id'));"
```

Este comando consulta la cuenta autorizada y muestra únicamente su identificador, sin imprimir el token de acceso. Pega el identificador obtenido en `CRM_TEAMLEADER_ACCOUNT_ID`. Si falla la autorización, hay que revisar la conexión Teamleader antes de registrar suscripciones.

## Variables de recepción y procesamiento

Añade o actualiza estas entradas en el entorno de producción. Completa los tres valores vacíos con los datos obtenidos arriba:

```dotenv
CRM_HUBSPOT_WEBHOOKS_ENABLED=true
CRM_HUBSPOT_CLIENT_SECRET=""
CRM_HUBSPOT_PORTAL_ID=20053496
CRM_HUBSPOT_SIGNATURE_VERSION=v3

CRM_TEAMLEADER_WEBHOOKS_ENABLED=true
CRM_TEAMLEADER_WEBHOOK_SECRET=""
CRM_TEAMLEADER_ACCOUNT_ID=""

CRM_EVENT_PROCESSING_ENABLED=false
CRM_LOCAL_READS_ENABLED=false
```

Después, en la misma carpeta del servidor, elimina la caché de configuración para que la siguiente petición lea el `.env` actualizado:

```bash
php artisan config:clear
```

Abre [Integraciones → Sincronización CRM](https://app.sefaruniversal.com/admin/integrations/crm-sync). Debe mostrar ambas recepciones habilitadas, los datos de configuración presentes y el procesamiento pausado. Entonces podrá usarse **Registrar webhooks de Teamleader** y comprobar entregas sin modificar perfiles.

## Cálculo inicial del COS y webhooks acotados

Para calcular el COS de todos los representados que ya están en la App y habilitar después las actualizaciones de sus contactos y negocios desde HubSpot, conserva la configuración de firma y añade:

```dotenv
CRM_HUBSPOT_WEBHOOKS_ENABLED=true
CRM_HUBSPOT_COS_FIELDS_ONLY=true
CRM_EVENT_PROCESSING_ENABLED=false
```

```bash
php artisan config:clear
php artisan cos:backfill-local --start
php artisan cos:backfill-local --status
```

El scheduler continúa cada cinco minutos en lotes de 25. En la carga inicial, la App verifica en HubSpot el contacto y los negocios de cada representado identificado por `hs_id` antes de calcular el COS. Los registros locales existentes se actualizan por ID. Los representados sin `hs_id` se calculan con la información local. La App usa el límite compartido de HubSpot y reanuda desde el último ID si llega al límite de solicitudes.

Consulta el avance en **Integraciones → Sincronización CRM → Carga inicial del COS**. El panel separa los representados sincronizados con HubSpot, los calculados con datos locales, los que quedaron sin estatus y los casos apartados para revisión. El cursor se guarda en el almacenamiento privado de Laravel y se conserva al desplegar o limpiar la caché. Si el estado quedó en «Pendiente de iniciar» porque el despliegue anterior limpió la caché, ejecuta `cos:backfill-local --start` después de desplegar esta versión. Los registros ya importados se reutilizan por ID.

El panel muestra la hora del último lote programado y los reintentos del caso actual. Si HubSpot devuelve un ID de contacto distinto del registrado, el caso pasa a revisión y el lote sigue. El log de Laravel guarda el ID del representado y el tipo de error para que Sistemas pueda revisar el caso.

Al terminar, `hubspot_scoped_processing` pasa a `true`. La cola CRM procesa los webhooks de contactos con `hs_id` y negocios asociados. La primera etapa usa `users`, `negocios` y la cola existente. Las actividades, presupuestos y demás objetos se podrán habilitar en una etapa posterior. En **Contactos y expedientes**, el filtro **Estatus del COS** usa el último cálculo guardado.

Para habilitar la asociación automática con Teamleader, configura `TEAMLEADER_AI_ASSOCIATION_ENABLED=true`. Usa el modelo de IA ya configurado y los proyectos locales, exige confianza estrictamente superior a `TEAMLEADER_AI_ASSOCIATION_MIN_CONFIDENCE` (80 de forma predeterminada), y guarda el vínculo en `negocios`. `php artisan crm:associate-deals-ai --limit=5` muestra el resultado del lote sin guardar asociaciones.

Si los secretos e identificadores ya figuran configurados pero la recepción sigue deshabilitada, verifica los dos interruptores. Este bloque, ejecutado en la carpeta Laravel del servidor, actualiza todas sus entradas existentes o las añade si faltan, sin imprimir ni cambiar secretos:

```bash
for crm_flag in CRM_HUBSPOT_WEBHOOKS_ENABLED CRM_TEAMLEADER_WEBHOOKS_ENABLED; do
  if grep -q "^${crm_flag}=" .env; then
    sed -i "s/^${crm_flag}=.*/${crm_flag}=true/" .env
  else
    printf '\n%s=true\n' "$crm_flag" >> .env
  fi
done

php artisan config:clear
```

Tras la incidencia del 16-11-2026, no se debe ejecutar `config:cache` en la versión anterior de producción: había lecturas de `env()` durante las peticiones. La recuperación comprobada es `php artisan config:clear`. La corrección mueve esas lecturas a los archivos de configuración y se comprueba con un proceso HTTP nuevo y configuración cacheada en un entorno de prueba.

Estas variables habilitan la base de recepción. La sincronización automática bidireccional y la resolución por fecha de cada campo siguen pendientes; no actives el procesamiento para anunciar esa ampliación como terminada.
