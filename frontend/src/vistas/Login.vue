<script setup>
import { ref } from 'vue';
import { api, guardarSesion } from '../api';

const email = ref('');
const password = ref('');
const error = ref('');
const enviando = ref(false);

async function entrar() {
  error.value = '';
  enviando.value = true;
  try {
    guardarSesion(await api('POST', '/login', { email: email.value, password: password.value }));
  } catch (e) {
    error.value = e.message;
  } finally {
    enviando.value = false;
  }
}
</script>

<template>
  <div class="acceso">
    <form class="tarjeta" @submit.prevent="entrar">
      <h1>Soportix</h1>
      <p class="tenue">Soporte técnico e inventario de equipos</p>
      <label>Correo <input v-model="email" type="email" autocomplete="username" required /></label>
      <label>Contraseña <input v-model="password" type="password" autocomplete="current-password" required /></label>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <button :disabled="enviando">{{ enviando ? 'Entrando…' : 'Entrar' }}</button>
    </form>
  </div>
</template>
