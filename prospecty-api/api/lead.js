/**
 * Conector landing → Prospecty (HighLevel / LeadConnector).
 *
 * Recibe el JSON del formulario, crea o actualiza el contacto en el CRM
 * (upsert por teléfono/correo), le agrega etiquetas, una nota con los datos
 * del alumno y, opcionalmente, lo mete a un workflow.
 *
 * Variables de entorno (Vercel → Settings → Environment Variables):
 *   GHL_TOKEN        Private Integration Token (pit-...)
 *   GHL_LOCATION_ID  ID de la subcuenta en Prospecty
 *   ALLOWED_ORIGINS  Dominios permitidos, separados por coma (opcional, default: *)
 *   GHL_WORKFLOW_ID  Workflow al que se agrega el contacto (opcional)
 */

const API = 'https://services.leadconnectorhq.com';
const VERSION = '2021-07-28';

function cors(req, res) {
  const allowed = (process.env.ALLOWED_ORIGINS || '*').split(',').map(s => s.trim()).filter(Boolean);
  const origin = req.headers.origin || '';
  const allow = allowed.includes('*') ? '*' : (allowed.includes(origin) ? origin : allowed[0]);
  res.setHeader('Access-Control-Allow-Origin', allow);
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.setHeader('Vary', 'Origin');
}

function normalizarTelefono(raw) {
  const d = String(raw || '').replace(/\D/g, '');
  if (d.length === 10) return '+52' + d;               // México, 10 dígitos
  if (d.length === 11 && d.startsWith('1')) return '+' + d; // EE. UU. con 1
  if (d.length === 12 && d.startsWith('52')) return '+' + d;
  if (d.length === 13 && d.startsWith('521')) return '+52' + d.slice(3); // formato viejo 521
  return d ? '+' + d : '';
}

function partirNombre(nombre) {
  const partes = String(nombre || '').trim().split(/\s+/);
  if (partes.length <= 1) return { firstName: partes[0] || '', lastName: '' };
  // Convención MX: dos nombres + apellidos es común; tomamos el primer token como nombre.
  return { firstName: partes[0], lastName: partes.slice(1).join(' ') };
}

async function ghl(path, body, method = 'POST') {
  const r = await fetch(API + path, {
    method,
    headers: {
      Authorization: 'Bearer ' + process.env.GHL_TOKEN,
      Version: VERSION,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await r.json().catch(() => ({}));
  if (!r.ok) {
    const err = new Error(data.message ? [].concat(data.message).join('; ') : 'HighLevel ' + r.status);
    err.status = r.status; err.data = data;
    throw err;
  }
  return data;
}

module.exports = async (req, res) => {
  cors(req, res);
  if (req.method === 'OPTIONS') return res.status(204).end();
  if (req.method !== 'POST') return res.status(405).json({ ok: false, error: 'Método no permitido' });

  if (!process.env.GHL_TOKEN || !process.env.GHL_LOCATION_ID) {
    return res.status(500).json({ ok: false, error: 'Faltan GHL_TOKEN o GHL_LOCATION_ID en el servidor' });
  }

  let b = req.body;
  if (typeof b === 'string') { try { b = JSON.parse(b); } catch { b = {}; } }
  b = b || {};

  const phone = normalizarTelefono(b.telefono);
  const email = String(b.email || '').trim().toLowerCase();
  if (!String(b.nombre || '').trim()) return res.status(400).json({ ok: false, error: 'Falta el nombre' });
  if (!phone && !email) return res.status(400).json({ ok: false, error: 'Falta teléfono o correo' });

  const { firstName, lastName } = partirNombre(b.nombre);
  const tags = Array.isArray(b.tags) ? b.tags.map(String).slice(0, 15) : [];

  try {
    // 1) Crear o actualizar contacto (upsert por teléfono / correo)
    const contacto = {
      locationId: process.env.GHL_LOCATION_ID,
      firstName, lastName,
      phone: phone || undefined,
      email: email || undefined,
      source: b.fuente || 'Landing',
      tags,
    };
    const up = await ghl('/contacts/upsert', contacto);
    const id = up.contact && up.contact.id;
    if (!id) throw new Error('El CRM no devolvió el id del contacto');

    // 2) Nota con los datos del alumno y del evento
    const lineas = [
      'Registro desde: ' + (b.fuente || 'Landing'),
      b.colegio ? 'Colegio: ' + b.colegio : null,
      b.hijo ? 'Alumno(a): ' + b.hijo : null,
      b.grado ? 'Grado: ' + b.grado : null,
      b.carrera ? 'Licenciatura de interés: ' + b.carrera : null,
      b.utm && (b.utm.source || b.utm.campaign) ? 'UTM: ' + [b.utm.source, b.utm.medium, b.utm.campaign, b.utm.content].filter(Boolean).join(' / ') : null,
      b.pagina ? 'Página: ' + b.pagina : null,
      'Fecha: ' + new Date().toLocaleString('es-MX', { timeZone: 'America/Tijuana' }),
    ].filter(Boolean);
    await ghl(`/contacts/${id}/notes`, { body: lineas.join('\n') }).catch(e => console.warn('Nota no guardada:', e.message));

    // 3) Workflow opcional
    if (process.env.GHL_WORKFLOW_ID) {
      await ghl(`/contacts/${id}/workflow/${process.env.GHL_WORKFLOW_ID}`, {}).catch(e => console.warn('Workflow no aplicado:', e.message));
    }

    return res.status(200).json({ ok: true, id, nuevo: !!(up.new) });
  } catch (e) {
    console.error('Error HighLevel:', e.status, e.data || e.message);
    return res.status(502).json({ ok: false, error: e.message || 'Error al guardar en el CRM' });
  }
};
