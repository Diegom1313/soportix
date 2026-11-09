import { reactive } from 'vue';

function leer() {
  try {
    return JSON.parse(sessionStorage.getItem('sesion'));
  } catch {
    return null;
  }
}

export const sesion = reactive({ datos: leer() });

export function guardarSesion(datos) {
  sesion.datos = datos;
  try {
    if (datos) sessionStorage.setItem('sesion', JSON.stringify(datos));
    else sessionStorage.removeItem('sesion');
  } catch {}
}

export async function api(metodo, ruta, cuerpo) {
  const headers = {};
  if (cuerpo !== undefined) headers['Content-Type'] = 'application/json';
  if (sesion.datos) headers.Authorization = `Bearer ${sesion.datos.token}`;
  const res = await fetch(`/api${ruta}`, {
    method: metodo,
    headers,
    body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
  });
  if (res.status === 204) return null;
  const datos = await res.json().catch(() => ({}));
  if (!res.ok) {
    if (res.status === 401 && sesion.datos && ruta !== '/login') guardarSesion(null);
    const campos = datos.fields ? `: ${Object.entries(datos.fields).map(([k, v]) => `${k} (${v})`).join(', ')}` : '';
    throw new Error((datos.error || 'No se pudo completar la acción') + campos);
  }
  return datos;
}

export const fecha = (v) => (v ? new Date(v).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' }) : '—');
export const ESTADOS = { abierto: 'Abierto', en_curso: 'En curso', resuelto: 'Resuelto', cerrado: 'Cerrado' };
export const PRIORIDADES = { baja: 'Baja', media: 'Media', alta: 'Alta', critica: 'Crítica' };
