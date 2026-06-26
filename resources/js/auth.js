document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const toggleIcon = document.getElementById('toggleIcon');
    const loginForm = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnSubmit');
    const passwordClientError = document.getElementById('passwordClientError');

    if (togglePasswordBtn && passwordInput && toggleIcon) {
        togglePasswordBtn.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            const isPressed = togglePasswordBtn.getAttribute('aria-pressed') === 'true';
            togglePasswordBtn.setAttribute('aria-pressed', (!isPressed).toString());
            togglePasswordBtn.setAttribute('aria-label', isPressed ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });
    }

    const showPasswordError = () => {
        if (!passwordClientError || !passwordInput) {
            return false;
        }

        if (!passwordInput.value.trim()) {
            passwordClientError.classList.remove('hidden');
            passwordInput.classList.add('ring-2', 'ring-rose-400/40');
            passwordInput.focus();
            return true;
        }

        passwordClientError.classList.add('hidden');
        passwordInput.classList.remove('ring-2', 'ring-rose-400/40');
        return false;
    };

    if (passwordInput && passwordClientError) {
        passwordInput.addEventListener('input', () => {
            if (passwordInput.value.trim()) {
                passwordClientError.classList.add('hidden');
                passwordInput.classList.remove('ring-2', 'ring-rose-400/40');
            }
        });
    }

    if (loginForm && btnSubmit) {
        loginForm.addEventListener('submit', (event) => {
            if (showPasswordError()) {
                event.preventDefault();
                return;
            }

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="bi bi-arrow-repeat mr-2 animate-spin"></i> Memproses...';
        });
    }
});
