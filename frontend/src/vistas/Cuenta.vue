<script setup>
import { reactive, ref } from 'vue';
import { api, avisar, ROLES } from '../api';

defineProps({ usuario: Object });
const f = reactive({ current: '', new: '', repetir: '' });
const error = ref('');

async function guardar() {
  error.value = '';
  if (f.new !== f.repetir) {
    error.value = 'Las dos contraseñas nuevas no coinciden.';
    return;
  }
  try {
    await api('POST', '/me/password', { current: f.current, new: f.new });
    avisar('Contraseña cambiada. Las demás sesiones se han cerrado.');
    Object.assign(f, { current: '', new: '', repetir: '' });
  } catch (e) {
    error.value = e.message;
  }
}
</script>

<template>
  <h1>Tu cuenta</h1>
  <dl class="cuenta-datos"><dt>Nombre</dt><dd>{{ usuario.name }}</dd><dt>Correo</dt><dd>{{ usuario.email }}</dd><dt>Rol</dt><dd>{{ ROLES[usuario.role] }}</dd></dl>
  <h2>Cambiar contraseña</h2>
  <form class="formulario estrecho" @submit.prevent="guardar">
    <label>Contraseña actual <input v-model="f.current" type="password" required autocomplete="current-password" /></label>
    <label>Nueva contraseña <input v-model="f.new" type="password" required minlength="12" maxlength="72" autocomplete="new-password" /><small class="tenue">Entre 12 y 72 caracteres.</small></label>
    <label>Repite la nueva contraseña <input v-model="f.repetir" type="password" required autocomplete="new-password" /></label>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <button class="principal">Cambiar contraseña</button>
  </form>
</template>
