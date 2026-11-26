import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import LoginView from '@/views/LoginView.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import HomeView from '@/views/HomeView.vue'
import EmployeesView from '@/views/EmployeesView.vue'
import EmployeeFormView from '@/views/EmployeeFormView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/login', name: 'login', component: LoginView, meta: { public: true } },
    {
      // Rutas privadas: todas se pintan dentro del layout (cabecera + menú)
      path: '/',
      component: AppLayout,
      children: [
        { path: '', name: 'home', component: HomeView },
        // meta.roles: solo estos roles pueden entrar (la API devolvería 403 al resto)
        { path: 'employees', name: 'employees', component: EmployeesView, meta: { roles: ['admin', 'manager'] } },
        { path: 'employees/new', name: 'employee-create', component: EmployeeFormView, meta: { roles: ['admin'] } },
        // props: true pasa el :id de la URL como prop al componente
        { path: 'employees/:id/edit', name: 'employee-edit', component: EmployeeFormView, props: true, meta: { roles: ['admin'] } },
      ],
    },
    // Cualquier URL desconocida vuelve al inicio
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

// Guard global: se ejecuta antes de cada navegación
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    // Con sesión abierta no tiene sentido volver al login
    return auth.isAuthenticated ? { name: 'home' } : true
  }

  if (!auth.isAuthenticated) {
    // Guardamos a dónde quería ir para llevarle allí tras el login
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (!auth.user) {
    // Tras recargar la página solo tenemos el token: pedimos el usuario
    try {
      await auth.fetchUser()
    } catch {
      // 401: el interceptor de http.js cierra la sesión y redirige al login.
      // Otro error (servidor caído): seguimos y cada vista muestra su propio error.
    }
  }

  if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) {
    return { name: 'home' }
  }
})

export default router
