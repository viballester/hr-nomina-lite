<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const auth = useAuthStore()

async function login() {
  error.value = ''
  loading.value = true

  try {
    await auth.login(email.value, password.value)
    await auth.fetchUser() // comprueba que el token funciona; en la Tarea 5 se usará al recargar
    console.log(auth.user)
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


