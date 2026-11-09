<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api, guardarSesion, sesion } from './api';
import Login from './vistas/Login.vue';
import Resumen from './vistas/Resumen.vue';
import Tickets from './vistas/Tickets.vue';
import TicketDetalle from './vistas/TicketDetalle.vue';
import Equipos from './vistas/Equipos.vue';
import EquipoDetalle from './vistas/EquipoDetalle.vue';
import Usuarios from './vistas/Usuarios.vue';

const hash = ref(location.hash);
const actualizar = () => (hash.value = location.hash);
onMounted(() => addEventListener('hashchange', actualizar));
onBeforeUnmount(() => removeEventListener('hashchange', actualizar));

const usuario = computed(() => sesion.datos?.user);
const personal = computed(() => ['admin', 'tecnico'].includes(usuario.value?.role));
const admin = computed(() => usuario.value?.role === 'admin');
const ruta = computed(() => {
  const partes = hash.value.replace(/^#\/?/, '').split('/');
  return { seccion: partes[0] || (personal.value ? 'resumen' : 'tickets'), id: partes[1] ? Number(partes[1]) : null };
});
const vista = computed(() => {
  const { seccion, id } = ruta.value;
  if (seccion === 'tickets') return id ? TicketDetalle : Tickets;
  if (seccion === 'equipos' && personal.value) return id ? EquipoDetalle : Equipos;
  if (seccion === 'usuarios' && admin.value) return Usuarios;
  if (seccion === 'resumen' && personal.value) return Resumen;
  return Tickets;
});

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
    <header class="barra">
      <strong class="marca">Soportix</strong>
      <nav>
        <a v-if="personal" href="#/resumen" :class="{ activo: ruta.seccion === 'resumen' }">Resumen</a>
        <a href="#/tickets" :class="{ activo: ruta.seccion === 'tickets' }">Tickets</a>
        <a v-if="personal" href="#/equipos" :class="{ activo: ruta.seccion === 'equipos' }">Equipos</a>
        <a v-if="admin" href="#/usuarios" :class="{ activo: ruta.seccion === 'usuarios' }">Usuarios</a>
      </nav>
      <div class="cuenta">
        <span>{{ usuario.name }}</span>
        <button class="sutil" @click="salir">Salir</button>
      </div>
    </header>
    <main class="contenido">
      <component :is="vista" :id="ruta.id" :key="hash" :usuario="usuario" />
    </main>
  </div>
</template>
