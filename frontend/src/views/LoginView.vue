<script setup>
import { ref } from 'vue'

// Variables reactivas
const email = ref('')
const password = ref('')

// Función de login
function login() {
  console.log(email.value, password.value)
}
</script>

<template>
  <h1>HR Nómina Lite</h1>

  <form @submit.prevent="login">
    <div>
      <label for="email">Email</label>
      <input
        id="email"
        type="email"
        v-model="email"
      >
    </div>

    <div>
      <label for="password">Contraseña</label>
      <input
        id="password"
        type="password"
        v-model="password"
      >
    </div>

    <button type="submit">Entrar</button>
  </form>
</template>

