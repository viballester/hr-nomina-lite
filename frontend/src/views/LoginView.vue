
<script setup>
import { ref } from 'vue'
import http from '@/api/http'

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function login() {
  error.value = ''
  loading.value = true

  try {
    const response = await http.post('/login', {
      email: email.value,
      password: password.value
    })

    console.log(response.data.user)
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se ha podido conectar con el servidor.'
  } finally {
    loading.value = false
  }
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
        required
        autocomplete="username"
      >
    </div>

    <div>
      <label for="password">Contraseña</label>
      <input
        id="password"
        type="password"
        v-model="password"
        required
        autocomplete="current-password"
      >
    </div>

    <button type="submit" :disabled="loading">
      Entrar
    </button>

    <p v-if="error">{{ error }}</p>
  </form>
</template>


