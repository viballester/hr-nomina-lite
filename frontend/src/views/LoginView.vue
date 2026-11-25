<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

async function login() {
  error.value = ''
  loading.value = true

  try {
    await auth.login(email.value, password.value)
    // Vuelve a la página que pedía antes de que el guard le mandara aquí
    router.push(route.query.redirect || '/')
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se ha podido conectar con el servidor.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login">
    <form class="card" @submit.prevent="login">
      <h1>HR Nómina Lite</h1>

      <div class="field">
        <label for="email">Email</label>
        <input
          id="email"
          type="email"
          v-model="email"
          required
          autocomplete="username"
        >
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input
          id="password"
          type="password"
          v-model="password"
          required
          autocomplete="current-password"
        >
      </div>

      <button type="submit" class="btn" :disabled="loading">
        {{ loading ? 'Entrando…' : 'Entrar' }}
      </button>

      <!-- role="alert" hace que los lectores de pantalla anuncien el error -->
      <p v-if="error" class="error" role="alert">{{ error }}</p>
    </form>
  </main>
</template>
