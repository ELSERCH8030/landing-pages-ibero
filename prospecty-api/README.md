# prospecty-api

Función serverless (Vercel) que recibe los registros de las landings HTML y los guarda como contactos en Prospecty (HighLevel).

El token del CRM vive solo aquí como variable de entorno. Las páginas HTML nunca lo incluyen.

## Qué hace por cada registro

1. `POST /contacts/upsert`: crea el contacto o actualiza el existente (busca por teléfono o correo).
2. Agrega las etiquetas que manda la landing (`platica-colegio-ibero`, `carrera-derecho`, etc.).
3. Guarda una nota con los datos del alumno, grado, licenciatura, UTM y fecha.
4. Si `GHL_WORKFLOW_ID` está definido, mete al contacto a ese workflow.

## Despliegue

```bash
cd prospecty-api
npx vercel
```

Luego en Vercel → Project → Settings → Environment Variables:

| Variable | Valor |
|---|---|
| `GHL_TOKEN` | Token `pit-...` (Prospecty → Settings → Private Integrations) |
| `GHL_LOCATION_ID` | ID de la subcuenta (aparece en la URL: `/v2/location/AQUI/...`) |
| `ALLOWED_ORIGINS` | Dominios donde viven las landings, separados por coma |
| `GHL_WORKFLOW_ID` | Opcional |

Redeploy después de guardar las variables. La URL final queda como
`https://<proyecto>.vercel.app/api/lead` y se pega en `LEAD_ENDPOINT` dentro de cada landing.

## Prueba rápida

```bash
curl -X POST https://<proyecto>.vercel.app/api/lead -H "Content-Type: application/json" -d "{\"nombre\":\"Prueba Landing\",\"telefono\":\"6640000000\",\"email\":\"prueba@ejemplo.com\",\"hijo\":\"Alumno Prueba\",\"grado\":\"3° de preparatoria\",\"carrera\":\"Derecho\",\"colegio\":\"Colegio Ibero\",\"fuente\":\"Prueba\",\"tags\":[\"prueba\"]}"
```

## Scopes que necesita el token

`contacts.write`, `contacts.readonly` y, si se usa workflow, `workflows.readonly`.
