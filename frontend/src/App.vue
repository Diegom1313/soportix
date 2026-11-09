<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api, avisos, guardarSesion, sesion, ROLES } from './api';
import Icono from './Icono.vue';
import Login from './vistas/Login.vue';
import Resumen from './vistas/Resumen.vue';
import Tickets from './vistas/Tickets.vue';
import TicketDetalle from './vistas/TicketDetalle.vue';
import Equipos from './vistas/Equipos.vue';
import EquipoDetalle from './vistas/EquipoDetalle.vue';
import Usuarios from './vistas/Usuarios.vue';
import Actividad from './vistas/Actividad.vue';
import Cuenta from './vistas/Cuenta.vue';

const capa = ref(null);
watch(() => avisos.length, async () => {
  await nextTick();
  const el = capa.value;
  if (!el?.showPopover) return;
  if (el.matches(':popover-open')) el.hidePopover();
  if (avisos.length) el.showPopover();
});

const hash = ref(location.hash);
const actualizar = () => {
  hash.value = location.hash;
  window.scrollTo(0, 0);
};
onMounted(() => addEventListener('hashchange', actualizar));
onBeforeUnmount(() => removeEventListener('hashchange', actualizar));

const usuario = computed(() => sesion.datos?.user);
const personal = computed(() => ['admin', 'tecnico'].includes(usuario.value?.role));
const admin = computed(() => usuario.value?.role === 'admin');
const ruta = computed(() => {
  const [camino, consulta] = hash.value.replace(/^#\/?/, '').split('?');
  const partes = camino.split('/');
  return {
    seccion: partes[0] || (personal.value ? 'resumen' : 'tickets'),
    id: partes[1] ? Number(partes[1]) : null,
    consulta: Object.fromEntries(new URLSearchParams(consulta || '')),
  };
});
const vista = computed(() => {
  const { seccion, id } = ruta.value;
  if (seccion === 'tickets') return id ? TicketDetalle : Tickets;
  if (seccion === 'equipos' && personal.value) return id ? EquipoDetalle : Equipos;
  if (seccion === 'usuarios' && admin.value) return Usuarios;
  if (seccion === 'actividad' && admin.value) return Actividad;
  if (seccion === 'cuenta') return Cuenta;
  if (seccion === 'resumen' && personal.value) return Resumen;
  return Tickets;
});
const enlaces = computed(() => [
  personal.value && ['resumen', 'Resumen'],
  ['tickets', 'Tickets'],
  personal.value && ['equipos', 'Equipos'],
  admin.value && ['usuarios', 'Usuarios'],
  admin.value && ['actividad', 'Actividad'],
].filter(Boolean));

function preferido() {
  try {
    return localStorage.getItem('tema');
  } catch {
    return null;
  }
}
function ponerTema(tema) {
  if (tema) document.documentElement.dataset.theme = tema;
}
ponerTema(preferido());
function alternarTema() {
  const oscuro = document.documentElement.dataset.theme
    ? document.documentElement.dataset.theme === 'dark'
    : matchMedia('(prefers-color-scheme: dark)').matches;
  const nuevo = oscuro ? 'light' : 'dark';
  ponerTema(nuevo);
  try {
    localStorage.setItem('tema', nuevo);
  } catch {}
}

async function salir() {
  try {
    await api('POST', '/logout');
  } finally {
    guardarSesion(null);
    location.hash = '';
  }
}
</script>

<template>
  <Login v-if="!usuario" />
  <div v-else class="marco">
    <aside class="lateral">
      <a class="marca" href="#/"><span class="sello"><i></i><i></i><i></i></span>Soportix</a>
      <nav aria-label="Principal">
        <a v-for="[id, texto] in enlaces" :key="id" :href="`#/${id}`" :class="{ activo: ruta.seccion === id }" :aria-current="ruta.seccion === id ? 'page' : null">
          <Icono :nombre="id" /><span>{{ texto }}</span>
        </a>
      </nav>
      <div class="cuenta">
        <a href="#/cuenta" class="persona" :class="{ activo: ruta.seccion === 'cuenta' }">
          <span class="inicial">{{ usuario.name[0] }}</span>
          <span class="persona-texto"><b>{{ usuario.name }}</b><small>{{ ROLES[usuario.role] }}</small></span>
        </a>
        <button class="icono-boton" aria-label="Cambiar entre tema claro y oscuro" @click="alternarTema"><Icono nombre="tema" /></button>
        <button class="icono-boton" aria-label="Cerrar sesión" @click="salir"><Icono nombre="salir" /></button>
      </div>
    </aside>
    <main class="contenido">
      <component :is="vista" :id="ruta.id" :key="hash" :usuario="usuario" :consulta="ruta.consulta" />
    </main>
  </div>
  <div ref="capa" popover="manual" class="avisos" role="status" aria-live="polite">
    <div v-for="a in avisos" :key="a.id" class="aviso-flotante" :class="a.tipo">{{ a.texto }}</div>
  </div>
</template>
