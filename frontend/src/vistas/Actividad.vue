<script setup>
import { onMounted, ref } from 'vue';
import { api, fecha } from '../api';

const ACCIONES = {
  'sesion.inicio': 'Inició sesión', 'usuario.alta': 'Añadió un usuario', 'usuario.cambio': 'Modificó un usuario',
  'usuario.reinicio_contrasena': 'Restableció una contraseña', 'usuario.cambio_contrasena': 'Cambió su contraseña',
  'equipo.alta': 'Añadió un equipo', 'ticket.cambio': 'Modificó un ticket',
};
const filas = ref(null);
const error = ref('');
onMounted(async () => {
  try {
    filas.value = await api('GET', '/audit');
  } catch (e) {
    error.value = e.message;
  }
});
</script>

<template>
  <h1>Actividad</h1>
  <p class="tenue intro">Las últimas 200 acciones realizadas en el portal.</p>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div v-if="!filas && !error" class="cargando" aria-label="Cargando"></div>
  <div v-if="filas" class="tabla-envoltura">
    <table>
      <thead><tr><th>Cuándo</th><th>Quién</th><th>Qué hizo</th><th>Detalle</th><th>IP</th></tr></thead>
      <tbody>
        <tr v-for="f in filas" :key="f.id"><td class="sin-salto">{{ fecha(f.created_at) }}</td><td>{{ f.user_name || 'Sistema' }}</td><td>{{ ACCIONES[f.action] || f.action }}</td><td class="tenue">{{ f.detail }}</td><td class="tenue">{{ f.ip }}</td></tr>
        <tr v-if="!filas.length"><td colspan="5" class="tenue">Todavía no hay actividad.</td></tr>
      </tbody>
    </table>
  </div>
</template>
