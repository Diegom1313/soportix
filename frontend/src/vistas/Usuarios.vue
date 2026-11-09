<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api, avisar, ROLES } from '../api';
import Icono from '../Icono.vue';
import Modal from '../Modal.vue';

const usuarios = ref(null);
const creando = ref(false);
const nuevo = reactive({ name: '', email: '', password: '', role: 'usuario' });
const reinicio = ref(null);
const clave = ref('');
const baja = ref(null);
const error = ref('');

async function cargar() {
  try {
    usuarios.value = await api('GET', '/users');
  } catch (e) {
    error.value = e.message;
  }
}

async function accion(fn, mensaje) {
  try {
    await fn();
    avisar(mensaje);
    await cargar();
    return true;
  } catch (e) {
    avisar(e.message, 'error');
    await cargar();
    return false;
  }
}

async function crear() {
  if (await accion(() => api('POST', '/users', { ...nuevo }), 'Usuario creado')) {
    Object.assign(nuevo, { name: '', email: '', password: '', role: 'usuario' });
    creando.value = false;
  }
}
const cambiarRol = (u, role) => accion(() => api('PATCH', `/users/${u.id}`, { role }), 'Rol actualizado');
const activar = (u) => accion(() => api('PATCH', `/users/${u.id}`, { active: true }), 'Usuario activado');
async function desactivar() {
  await accion(() => api('PATCH', `/users/${baja.value.id}`, { active: false }), 'Usuario desactivado');
  baja.value = null;
}
async function reiniciar() {
  if (await accion(() => api('POST', `/users/${reinicio.value.id}/password`, { password: clave.value }), 'Contraseña restablecida')) {
    reinicio.value = null;
    clave.value = '';
  }
}
onMounted(cargar);
</script>

<template>
  <div class="cabecera">
    <h1>Usuarios</h1>
    <button class="principal" @click="creando = true"><Icono nombre="mas" />Añadir usuario</button>
  </div>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div v-if="!usuarios && !error" class="cargando" aria-label="Cargando"></div>
  <div v-if="usuarios" class="tabla-envoltura">
    <table>
      <thead><tr><th>Persona</th><th>Rol</th><th>Estado</th><th><span class="oculto">Acciones</span></th></tr></thead>
      <tbody>
        <tr v-for="u in usuarios" :key="u.id" :class="{ inactivo: !u.active }">
          <td><b>{{ u.name }}</b><small class="tenue bloque">{{ u.email }}</small></td>
          <td><select :value="u.role" :aria-label="`Rol de ${u.name}`" @change="cambiarRol(u, $event.target.value)"><option v-for="(t, k) in ROLES" :key="k" :value="k">{{ t }}</option></select></td>
          <td>{{ u.active ? 'Activo' : 'Desactivado' }}</td>
          <td class="celda-acciones">
            <button @click="reinicio = u">Restablecer contraseña</button>
            <button v-if="u.active" class="peligro" @click="baja = u">Desactivar</button>
            <button v-else @click="activar(u)">Activar</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <Modal v-if="creando" titulo="Añadir usuario" @cerrar="creando = false">
    <form class="formulario" @submit.prevent="crear">
      <label>Nombre <input v-model="nuevo.name" required minlength="2" autofocus /></label>
      <label>Correo <input v-model="nuevo.email" type="email" required /></label>
      <label>Contraseña inicial <input v-model="nuevo.password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" /><small class="tenue">Entre 12 y 72 caracteres.</small></label>
      <label>Rol <select v-model="nuevo.role"><option v-for="(t, k) in ROLES" :key="k" :value="k">{{ t }}</option></select></label>
      <button class="principal">Añadir usuario</button>
    </form>
  </Modal>
  <Modal v-if="reinicio" :titulo="`Restablecer la contraseña de ${reinicio.name}`" @cerrar="reinicio = null">
    <form class="formulario" @submit.prevent="reiniciar">
      <label>Nueva contraseña <input v-model="clave" type="password" required minlength="12" maxlength="72" autocomplete="new-password" autofocus /><small class="tenue">Se cerrarán todas sus sesiones abiertas.</small></label>
      <button class="principal">Restablecer contraseña</button>
    </form>
  </Modal>
  <Modal v-if="baja" :titulo="`¿Desactivar a ${baja.name}?`" @cerrar="baja = null">
    <div class="formulario">
      <p>No podrá entrar y se cerrarán sus sesiones. Sus tickets se conservan y puedes volver a activarlo.</p>
      <button class="peligro-fuerte" @click="desactivar">Desactivar</button>
    </div>
  </Modal>
</template>
