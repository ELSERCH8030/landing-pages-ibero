# prospecty-api

Conector en PHP que recibe los registros de las landings HTML y los guarda como contactos en Prospecty (HighLevel). Corre en el mismo Apache donde viven las landings; no necesita servicios externos ni la extensión curl.

Este repositorio es público: **el token del CRM nunca se sube aquí**. Vive en un archivo de configuración en el servidor, fuera de la carpeta pública.

## Qué hace por cada registro

1. `POST /contacts/upsert`: crea el contacto o actualiza el existente (busca por teléfono o correo).
2. Agrega las etiquetas que manda la landing (`platica-colegio-ibero`, `carrera-derecho`, etc.).
3. Guarda una nota con los datos del alumno, grado, licenciatura, UTM y fecha.
4. Si `workflow_id` está definido, mete al contacto a ese workflow.

## Instalación (una sola vez, en el servidor)

1. Copiar `prospecty-config.example.php` como `prospecty-config.php` en la carpeta padre del DocumentRoot
   (por ejemplo, si el sitio está en `/var/www/promocion/html`, el archivo va en `/var/www/promocion/prospecty-config.php`)
   o bien en `/etc/prospecty-config.php`.
2. Llenar `token` y `location_id`. Permisos recomendados: `chmod 640` y dueño `www-data`.
3. Nada más. Las landings ya apuntan a `prospecty-api/enviar.php` con ruta relativa.

Alternativa sin archivo: definir las variables de entorno `GHL_TOKEN` y `GHL_LOCATION_ID` en la configuración de Apache (`SetEnv`).

## Uso desde una landing

```js
fetch('../../prospecty-api/enviar.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ nombre, telefono, email, hijo, grado, carrera, colegio, fuente, tags, utm, pagina })
});
```

Responde `{ "ok": true, "id": "<id del contacto>" }` o `{ "ok": false, "error": "..." }`.

## Prueba rápida

```bash
curl -X POST https://promocion.iberotijuana.edu.mx/prospecty-api/enviar.php -H "Content-Type: application/json" -d "{\"nombre\":\"Prueba Landing\",\"telefono\":\"6640000000\",\"email\":\"prueba@ejemplo.com\",\"hijo\":\"Alumno Prueba\",\"grado\":\"3° de preparatoria\",\"carrera\":\"Derecho\",\"colegio\":\"Colegio Ibero\",\"fuente\":\"Prueba\",\"tags\":[\"prueba\"]}"
```

## Scopes que necesita el token

`contacts.write`, `contacts.readonly` y, si se usa workflow, `workflows.readonly`.
