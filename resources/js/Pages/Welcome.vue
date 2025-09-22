<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
  canLogin: {
    type: Boolean,
    default: true,
  },
});

const imageExists = ref(true);

function handleImageError() {
  imageExists.value = false;
}

// datos de Inertia ($page.props.auth.user)
const page = usePage();
const user = page.props?.auth?.user ?? null;

// redirige al dashboard si ya está autenticado
onMounted(() => {
  if (user) {
    try {
      const url = typeof route === 'function' ? route('dashboard') : '/dashboard';
      window.location.href = url;
    } catch (e) {
      window.location.href = '/dashboard';
    }
  }
});
</script>

<template>
  <Head title="Bienvenido" />

  <div class="min-h-screen flex flex-col bg-gray-50 dark:bg-black text-black/80 dark:text-white/80">
    <!-- Header solo visible si está autenticado -->
    <header class="flex items-center justify-end px-6 py-6">
      <nav class="flex items-center gap-3">
        <Link
          v-if="user"
          :href="typeof route === 'function' ? route('dashboard') : '/dashboard'"
          class="rounded-md px-3 py-2 text-sm font-medium bg-white/80 ring-1 ring-transparent hover:bg-white/90 dark:bg-zinc-900/80 dark:hover:bg-zinc-900/90 transition"
        >
          Dashboard
        </Link>
      </nav>
    </header>

    <!-- Contenido central: logo + botón -->
    <main class="flex flex-1 items-center justify-center px-6 pb-12">
      <div class="w-full max-w-md">
        <!-- Card del logo -->
        <div class="flex flex-col items-center gap-6 rounded-xl bg-white/60 p-8 text-center shadow-md dark:bg-zinc-900/60">
          <div class="w-32 h-32 sm:w-40 sm:h-40 flex items-center justify-center p-2 rounded-lg bg-white/30 dark:bg-black/30">
            <img
              v-if="imageExists"
              src="/favicon.png"
              alt="Logo"
              class="max-h-full max-w-full object-contain"
              @error="handleImageError"
              loading="lazy"
            />
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">
              Logo no encontrado.
              <div class="mt-2 text-xs text-gray-400">
                Coloca <code>logo.png</code> en la carpeta <code>/public</code>.
              </div>
            </div>
          </div>

          <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Bienvenido</h1>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            Estas en la ventana de inicio de secion porfavor ingrese su cuenta correspondiente 
          </p>

          <!-- Botón Iniciar sesión (solo si no hay usuario) -->
          <Link
            v-if="canLogin && !user"
            :href="typeof route === 'function' ? route('login') : '/login'"
            class="mt-4 rounded-md px-4 py-2 text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none"
          >
            Iniciar sesión
          </Link>
        </div>
      </div>
    </main>
  </div>
</template>
