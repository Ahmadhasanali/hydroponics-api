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

    // 2. Prevent Double Submits
    if (loginForm && btnSubmit) {
        loginForm.addEventListener('submit', () => {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Memproses...
            `;
        });
    }
});
