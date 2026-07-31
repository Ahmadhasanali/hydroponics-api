const initChatWidget = () => {
    const root = document.getElementById('agroBot');
    if (!root) return;

    const toggleBtn = document.getElementById('agroBotToggle');
    const closeBtn = document.getElementById('agroBotClose');
    const clearBtn = document.getElementById('agroBotClear');
    const panel = document.getElementById('agroBotPanel');
    const messages = document.getElementById('agroBotMessages');
    const form = document.getElementById('agroBotForm');
    const input = document.getElementById('agroBotInput');
    const sendBtn = document.getElementById('agroBotSend');

    const userId = root.dataset.userId;
    const chatUrl = root.dataset.chatUrl;
    const csrf = root.dataset.csrf;
    const STORAGE_KEY = `agrobot_chats_${userId}`;

    const loadMessages = () => {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            saved.forEach(({ role, content }) => appendBubble(role, content, false));
        } catch {
            localStorage.removeItem(STORAGE_KEY);
        }
    };

    const saveMessages = () => {
        const history = [];
        messages.querySelectorAll('.agro-bubble').forEach((el) => {
            if (el.dataset.history === 'false') return;
            history.push({ role: el.dataset.role, content: el.dataset.content });
        });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-20)));
    };

    const appendBubble = (role, content, persist = true, tracked = true) => {
        const wrap = document.createElement('div');
        wrap.className = 'agro-bubble flex ' + (role === 'user' ? 'justify-end' : 'justify-start');
        wrap.dataset.role = role;
        wrap.dataset.content = content;
        if (!tracked) wrap.dataset.history = 'false';

        const bubble = document.createElement('div');
        bubble.className =
            role === 'user'
                ? 'max-w-[80%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-[#ffce54] px-4 py-2.5 text-sm font-medium text-[#1a1c1e]'
                : 'max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-bl-md border border-slate-200/80 bg-white px-4 py-2.5 text-sm text-slate-700';
        bubble.textContent = content;
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;

        if (persist) saveMessages();
    };

    const showTyping = () => {
        const wrap = document.createElement('div');
        wrap.id = 'agroTyping';
        wrap.className = 'flex justify-start';
        wrap.innerHTML =
            '<div class="flex items-center gap-1 rounded-2xl rounded-bl-md border border-slate-200/80 bg-white px-4 py-3">' +
            '<span class="h-2 w-2 animate-pulse rounded-full bg-slate-400"></span>' +
            '<span class="h-2 w-2 animate-pulse rounded-full bg-slate-400" style="animation-delay:150ms"></span>' +
            '<span class="h-2 w-2 animate-pulse rounded-full bg-slate-400" style="animation-delay:300ms"></span></div>';
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    };

    const hideTyping = () => document.getElementById('agroTyping')?.remove();

    const history = () => {
        const items = [];
        messages.querySelectorAll('.agro-bubble').forEach((el) => {
            if (el.dataset.history === 'false') return;
            items.push({ role: el.dataset.role, content: el.dataset.content });
        });
        return items.slice(-20);
    };

    const send = async () => {
        const message = input.value.trim();
        if (!message || sendBtn.disabled) return;

        input.value = '';
        input.style.height = 'auto';
        appendBubble('user', message);
        showTyping();
        sendBtn.disabled = true;

        try {
            const res = await fetch(chatUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ message, history: history() }),
            });

            const data = await res.json();
            hideTyping();
            appendBubble('assistant', data.reply || 'Maaf, terjadi kesalahan. Silakan coba lagi.');
        } catch {
            hideTyping();
            appendBubble('assistant', 'Maaf, terjadi kesalahan koneksi. Silakan coba lagi.');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    };

    toggleBtn.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        panel.classList.toggle('flex');
        if (!panel.classList.contains('hidden') && messages.children.length === 0) {
            appendBubble(
                'assistant',
                'Halo! Saya Agro Bot. Tanyakan apa saja tentang budidaya selada hidroponik, atau data farm Anda seperti PPM, pH, dan riwayat nutrisi.',
                false,
                false
            );
        }
        input.focus();
    });

    closeBtn.addEventListener('click', () => {
        panel.classList.add('hidden');
        panel.classList.remove('flex');
    });

    clearBtn.addEventListener('click', () => {
        messages.innerHTML = '';
        localStorage.removeItem(STORAGE_KEY);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        send();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });
};

document.addEventListener('DOMContentLoaded', initChatWidget);
