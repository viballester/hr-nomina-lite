import axios from 'axios'

const http = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
})

// Lee de localStorage y no del store para evitar una dependencia circular store ↔ http
http.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// 401 con token = token caducado o revocado: se cierra la sesión local.
// La recarga completa (location.assign) también vacía el store de Pinia.
http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && localStorage.getItem('token')) {
      localStorage.removeItem('token')
      window.location.assign('/login')
    }
    return Promise.reject(error)
  },
)

export default http
