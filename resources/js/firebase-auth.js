/**
 * Inicio de sesión con Google mediante Firebase.
 *
 * El SDK abre el popup de Google, obtiene un ID token verificado y lo envía al
 * backend (POST /auth/google), que crea la sesión y devuelve la URL de destino.
 *
 * Sólo se carga en las páginas de login y registro.
 */
import { initializeApp } from 'firebase/app';
import { getAuth, GoogleAuthProvider, signInWithPopup, setPersistence, browserSessionPersistence } from 'firebase/auth';

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
};

const isConfigured = Boolean(firebaseConfig.apiKey && firebaseConfig.projectId);

const provider = new GoogleAuthProvider();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Muestra un mensaje de error en el contenedor reservado para ello.
 */
function showError(message) {
    const box = document.querySelector('[data-google-error]');
    if (box) {
        box.textContent = message;
        box.classList.remove('hidden');
    }
}

async function handleGoogleSignIn(button) {
    if (!isConfigured) {
        showError('El acceso con Google aún no está configurado.');
        return;
    }

    const originalText = button.innerHTML;
    button.disabled = true;
    button.classList.add('opacity-60', 'cursor-wait');

    try {
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        auth.useDeviceLanguage();
        await setPersistence(auth, browserSessionPersistence);

        const result = await signInWithPopup(auth, provider);
        const idToken = await result.user.getIdToken();

        const response = await fetch('/auth/google', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ id_token: idToken }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message || 'No se pudo iniciar sesión con Google.');
        }

        const data = await response.json();
        window.location.href = data.redirect || '/dashboard';
    } catch (error) {
        // El usuario cerró el popup: no es un error que debamos mostrar.
        if (error?.code !== 'auth/popup-closed-by-user' && error?.code !== 'auth/cancelled-popup-request') {
            showError(error.message || 'No se pudo iniciar sesión con Google.');
        }
        button.disabled = false;
        button.classList.remove('opacity-60', 'cursor-wait');
        button.innerHTML = originalText;
    }
}

document.querySelectorAll('[data-google-signin]').forEach((button) => {
    button.addEventListener('click', () => handleGoogleSignIn(button));
});
