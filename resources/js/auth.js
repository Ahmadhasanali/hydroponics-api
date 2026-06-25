document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const toggleIcon = document.getElementById('toggleIcon');
    const loginForm = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnSubmit');

    // 1. Password Visibility Toggle
    if (togglePasswordBtn && passwordInput && toggleIcon) {
        togglePasswordBtn.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle accessibility attributes
            const isPressed = togglePasswordBtn.getAttribute('aria-pressed') === 'true';
            togglePasswordBtn.setAttribute('aria-pressed', !isPressed);
            togglePasswordBtn.setAttribute('aria-label', isPressed ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');

            // Toggle icon class
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });
    }

    // 2. Frontend required-field validation
    const passwordClientError = document.getElementById('passwordClientError');

    const showPasswordError = () => {
        if (!passwordClientError || !passwordInput) {
            return false;
        }

        if (!passwordInput.value.trim()) {
            passwordClientError.classList.remove('d-none');
            passwordInput.classList.add('is-invalid');
            passwordInput.focus();
            return true;
        }

        passwordClientError.classList.add('d-none');
        passwordInput.classList.remove('is-invalid');
        return false;
    };

    if (passwordInput && passwordClientError) {
        passwordInput.addEventListener('input', () => {
            if (passwordInput.value.trim()) {
                passwordClientError.classList.add('d-none');
                passwordInput.classList.remove('is-invalid');
            }
        });
    }

    // 3. Prevent Double Submits
    if (loginForm && btnSubmit) {
        loginForm.addEventListener('submit', (event) => {
            if (showPasswordError()) {
                event.preventDefault();
                return;
            }

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Memproses...
            `;
        });
    }
});
