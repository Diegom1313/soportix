<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api';

const usuarios = ref([]);
const nuevo = reactive({ name: '', email: '', password: '', role: 'usuario' });
const error = ref('');
const ROLES = { admin: 'Administración', tecnico: 'Técnico', usuario: 'Usuario' };

async function intentar(accion) {
  error.value = '';
  try {
    await accion();
  } catch (e) {
    error.value = e.message;
  }
  usuarios.value = await api('GET', '/users').catch(() => usuarios.value);
}

const crear = () => intentar(async () => {
  await api('POST', '/users', { ...nuevo });
  Object.assign(nuevo, { name: '', email: '', password: '', role: 'usuario' });
});
const cambiar = (u, campos) => intentar(() => api('PATCH', `/users/${u.id}`, campos));
onMounted(() => intentar(async () => {}));
</script>

<template>
  <h1>Usuarios</h1>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <form class="tarjeta formulario rejilla" @submit.prevent="crear">
    <label>Nombre <input v-model="nuevo.name" required minlength="2" /></label>
    <label>Correo <input v-model="nuevo.email" type="email" required /></label>
    <label>Contraseña <input v-model="nuevo.password" type="password" required minlength="12" autocomplete="new-password" /></label>
    <label>Rol <select v-model="nuevo.role"><option v-for="(t, k) in ROLES" :key="k" :value="k">{{ t }}</option></select></label>
    <button>Crear usuario</button>
  </form>
  <div class="tabla-envoltura">
    <table>
      <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th></tr></thead>
      <tbody>
        <tr v-for="u in usuarios" :key="u.id">
          <td>{{ u.name }}</td>
          <td>{{ u.email }}</td>
          <td><select :value="u.role" :aria-label="`Rol de ${u.name}`" @change="cambiar(u, { role: $event.target.value })"><option v-for="(t, k) in ROLES" :key="k" :value="k">{{ t }}</option></select></td>
          <td><button class="sutil" @click="cambiar(u, { active: !u.active })">{{ u.active ? 'Desactivar' : 'Activar' }}</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
