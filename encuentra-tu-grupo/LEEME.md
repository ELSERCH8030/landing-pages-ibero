# Encuentra tu grupo — Curso Propedéutico IBERO Tijuana

Buscador web para que los estudiantes de nuevo ingreso se localicen por nombre,
vean su grupo y descarguen sus datos como imagen para guardarlos en el celular.

- **Lo que se publica:** `index.html` — un solo archivo, sin dependencias.
  La fuente oficial *Iberoamericana* y el logotipo vectorial van incrustados,
  así que funciona sin internet una vez cargado.
- **Peso:** ~215 KB. **Contenido actual:** 183 alumnos, 11 carreras, grupos A–J.

---

## 1. Actualizar la lista de alumnos

Deja **un solo `.xlsx` dentro de `_datos\`** y vuelve a compilar. El script
busca los encabezados por nombre (no por posición), así que las columnas pueden
moverse:

| Columna en el Excel | Se usa como |
|---|---|
| `Carrera` | carrera |
| `Cuenta` (o `Matrícula`) | número de cuenta |
| `Apellido paterno` · `Apellido materno` · `Nombre` | se unen en ese orden |
| `Grupo` | grupo |
| `Salon` (o `Salón`, `Aula`) | salón |

Limpia espacios sobrantes automáticamente y avisa en consola si algún alumno
se quedó sin grupo.

## 2. Cambiar textos, fechas o campos

Todo vive en `CONFIG`, al inicio del `<script>` de `_build\app.src.html`:

- `evento` — nombre, fechas, horario, punto de reunión y cómo se entra.
  Alimenta a la vez la tarjeta de la página, la hoja de datos y la imagen.
- `campos` — qué se muestra y con qué etiqueta. Para agregar uno nuevo hay que
  traerlo también desde el Excel (ver `COLS` en `build.py`).
- `contacto` — correo de Servicios Escolares. **Está vacío**: mientras no se
  llene, el pie dice "acércate a Servicios Escolares" sin liga de correo.
- `periodo`, `generacion`, `minLetras`, `maxLista`.

## 3. Regenerar `index.html`

```bash
"%LOCALAPPDATA%\Programs\Python\Python312\python.exe" "_build\build.py"
```

Lee el Excel, incrusta las 4 variantes de Iberoamericana (subconjunto latino
convertido a woff2) y el logotipo oficial, y escribe `index.html` en la raíz.
Requiere `openpyxl`, `fonttools` y `brotli`.

**Nunca edites `index.html` a mano**: se sobreescribe en cada compilación.

## 4. Publicar

`index.html` es estático y autocontenido: sirve tal cual en GitHub Pages,
Netlify Drop, Vercel o cualquier carpeta del servidor. No necesita backend.

---

## Nota de privacidad — leer antes de publicar

Toda la lista viaja **dentro del HTML**. Cualquiera que abra la página puede
ver el código fuente y quedarse con el padrón completo: 183 nombres con su
número de cuenta, carrera y grupo. El buscador no es un candado.

Por eso el archivo lleva `noindex, nofollow` y en los resultados sólo se ven
nombre, carrera y grupo — el número de cuenta aparece al abrir los datos.

Si eso no basta para Servicios Escolares, las salidas razonables son:

1. **Quitar el número de cuenta** de `CONFIG.campos` y dejar sólo carrera y grupo.
2. **URL no adivinable y temporal.** Enlace largo y aleatorio, difundido sólo
   por los canales de admisión, y bajar el sitio al terminar el propedéutico.
3. **Contraseña de acceso** en el servidor (Netlify y Vercel la traen de fábrica).
4. **Backend real** si se quiere que cada quien vea únicamente su registro:
   eso ya no se resuelve con un HTML suelto.

## Estructura

```
directorio-nuevo-ingreso\
├─ index.html          ← lo que se publica (generado)
├─ LEEME.md
├─ _datos\
│  └─ Prope, asignación de grupos.xlsx   ← la base; sustituir aquí
└─ _build\
   ├─ app.src.html     ← fuente editable (diseño, textos, CONFIG)
   └─ build.py         ← Excel + fuentes + logo → index.html
```
