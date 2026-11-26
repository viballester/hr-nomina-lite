import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import http from '@/api/http'

export const useAuthStore = defineStore('auth', () => {
  // Estado: el token se recupera de localStorage al cargar la app
  const token = ref(localStorage.getItem('token'))
  const user = ref(null)

  // Getter: true si hay token (!! convierte cualquier valor en true/false)
  const isAuthenticated = computed(() => !!token.value)

  async function login(email, password) {
    const response = await http.post('/login', { email, password })
    token.value = response.data.token
    user.value = response.data.user
    localStorage.setItem('token', token.value)
  }

  // Recupera el usuario tras recargar la página: solo tenemos el token
  async function fetchUser() {
    const response = await http.get('/me')
    user.value = response.data
  }

  async function logout() {
    try {
      await http.post('/logout') // invalida el token también en el servidor
    } finally {
      // se limpia la sesión local aunque la petición falle
      token.value = null
      user.value = null
      localStorage.removeItem('token')
    }
  }

  return { token, user, isAuthenticated, login, fetchUser, logout }
})
