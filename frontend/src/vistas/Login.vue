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
    <section class="acceso-marca">
      <a class="marca"><span class="sello"><i></i><i></i><i></i></span>Soportix</a>
      <p class="lema">Cada incidencia, cada equipo y cada plazo, en una sola cola de trabajo.</p>
    </section>
    <form class="acceso-form" @submit.prevent="entrar">
      <h1>Entra en tu cuenta</h1>
      <label>Correo <input v-model="email" type="email" autocomplete="username" required autofocus /></label>
      <label>Contraseña <input v-model="password" type="password" autocomplete="current-password" required /></label>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <button class="principal" :disabled="enviando">{{ enviando ? 'Entrando…' : 'Entrar' }}</button>
    </form>
  </div>
</template>
