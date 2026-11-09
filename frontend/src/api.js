import { reactive } from 'vue';

function leer() {
  try {
    return JSON.parse(sessionStorage.getItem('sesion'));
  } catch {
    return null;
  }
}

export const sesion = reactive({ datos: leer() });
export const avisos = reactive([]);
let siguienteAviso = 1;

export function avisar(texto, tipo = 'ok') {
  const id = siguienteAviso++;
  avisos.push({ id, texto, tipo });
  setTimeout(() => {
    const i = avisos.findIndex((a) => a.id === id);
    if (i >= 0) avisos.splice(i, 1);
  }, 4500);
}

export function guardarSesion(datos) {
  sesion.datos = datos;
  try {
    if (datos) sessionStorage.setItem('sesion', JSON.stringify(datos));
    else sessionStorage.removeItem('sesion');
  } catch {}
}

const CAMPOS = {
  title: 'Título', description: 'Descripción', priority: 'Prioridad', status: 'Estado', name: 'Nombre', email: 'Correo',
  password: 'Contraseña', new: 'Nueva contraseña', current: 'Contraseña actual', role: 'Rol', hostname: 'Nombre del equipo',
  body: 'Comentario', assignee_id: 'Técnico', q: 'Búsqueda',
};

export async function api(metodo, ruta, cuerpo) {
  const headers = {};
  if (cuerpo !== undefined) headers['Content-Type'] = 'application/json';
  if (sesion.datos) headers.Authorization = `Bearer ${sesion.datos.token}`;
  let res;
  try {
    res = await fetch(`/api${ruta}`, { method: metodo, headers, body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo) });
  } catch {
    throw new Error('No hay conexión con el servidor. Comprueba tu red y vuelve a intentarlo.');
  }
  if (res.status === 204) return null;
  const datos = await res.json().catch(() => ({}));
  if (!res.ok) {
    if (res.status === 401 && sesion.datos && ruta !== '/login') {
      guardarSesion(null);
      avisar('Tu sesión ha caducado. Entra de nuevo para continuar.', 'error');
    }
    if (datos.fields) throw new Error(Object.entries(datos.fields).map(([k, v]) => `${CAMPOS[k] || k}: ${v.toLowerCase()}`).join('. '));
    throw new Error(datos.error || 'No se pudo completar la acción. Vuelve a intentarlo.');
  }
  return datos;
}

export const fecha = (v) => (v ? new Date(v).toLocaleString('es-ES', { dateStyle: 'medium', timeStyle: 'short' }) : 'Sin datos');

export function hace(v) {
  if (!v) return 'Nunca';
  const min = Math.round((Date.now() - new Date(v)) / 60000);
  if (min < 1) return 'Ahora mismo';
  if (min < 60) return `Hace ${min} min`;
  if (min < 1440) return `Hace ${Math.round(min / 60)} h`;
  return `Hace ${Math.round(min / 1440)} d`;
}

export async function copiar(texto) {
  try {
    await navigator.clipboard.writeText(texto);
    avisar('Copiado al portapapeles');
  } catch {
    avisar('No se pudo copiar. Selecciona el texto y cópialo a mano.', 'error');
  }
}

export const ESTADOS = { abierto: 'Abierto', en_curso: 'En curso', resuelto: 'Resuelto', cerrado: 'Cerrado' };
export const PRIORIDADES = { baja: 'Baja', media: 'Media', alta: 'Alta', critica: 'Crítica' };
export const ROLES = { admin: 'Administración', tecnico: 'Técnico', usuario: 'Usuario' };
