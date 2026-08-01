import { renderMarkdown } from './markdown.js';

const initChatWidget = () => {
    const root = document.getElementById('agroBot');
    if (!root) return;

    const toggleBtn = document.getElementById('agroBotToggle');
    const closeBtn = document.getElementById('agroBotClose');
    const clearBtn = document.getElementById('agroBotClear');
    const sessionsBtn = document.getElementById('agroBotSessions');
    const panel = document.getElementById('agroBotPanel');
    const messages = document.getElementById('agroBotMessages');
    const form = document.getElementById('agroBotForm');
    const input = document.getElementById('agroBotInput');
    const sendBtn = document.getElementById('agroBotSend');
    const sidebar = document.getElementById('agroBotSidebar');
    const sessionList = document.getElementById('agroBotSessionList');

    const userId = root.dataset.userId;
    const chatUrl = root.dataset.chatUrl;
    const csrf = root.dataset.csrf;
    const STORAGE_KEY = `agrobot_chats_${userId}`;

    let currentSessionId = null;
    let sessions = [];

    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf };

    const api = async (url, options = {}) => {
        const res = await fetch(url, { headers, ...options });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.status === 204 ? null : res.json();
    };

    const escapeHtml = (text) =>
        text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const timeAgo = (iso) => {
        const diff = (Date.now() - new Date(iso).getTime()) / 1000;
        if (diff < 60) return 'baru saja';
        if (diff < 3600) return `${Math.floor(diff / 60)} mnt lalu`;
        if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
        return `${Math.floor(diff / 86400)} hari lalu`;
    };

    const appendBubble = (role, content, tracked = true) => {
        const wrap = document.createElement('div');
        wrap.className = 'agro-bubble flex ' + (role === 'user' ? 'justify-end' : 'justify-start');
        wrap.dataset.role = role;
        if (!tracked) wrap.dataset.history = 'false';

        const bubble = document.createElement('div');
        bubble.className =
            role === 'user'
                ? 'max-w-[80%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-[#ffce54] px-4 py-2.5 text-sm font-medium text-[#1a1c1e]'
                : 'max-w-[85%] rounded-2xl rounded-bl-md border border-slate-200/80 bg-white px-4 py-2.5 text-sm text-slate-700 ' +
                  '[&_p]:leading-relaxed [&_p+*]:mt-2 [&_h4]:font-semibold [&_h4]:text-slate-900 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5 ' +
                  '[&_ol]:my-2 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-5 [&_strong]:font-semibold [&_strong]:text-slate-900 ' +
                  '[&_em]:italic [&_code]:rounded-md [&_code]:bg-slate-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.85em]';
        if (role === 'assistant') {
            bubble.innerHTML = renderMarkdown(content);
        } else {
            bubble.textContent = content;
        }
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    };

    const renderSessions = () => {
        sessionList.innerHTML = '';
        sessions.forEach((session) => {
            const item = document.createElement('div');
            item.className =
                'group flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-slate-100 ' +
                (session.id === currentSessionId ? 'bg-[#ffce54]/30' : '');
            item.innerHTML =
                `<i class="bi bi-chat-dots shrink-0 text-slate-400"></i>` +
                `<span class="min-w-0 flex-1 truncate">${escapeHtml(session.title || 'Sesi baru')}</span>` +
                `<span class="shrink-0 text-[10px] text-slate-400">${timeAgo(session.updated_at)}</span>` +
                `<span class="hidden shrink-0 items-center gap-1 group-hover:flex">` +
                `<button type="button" class="rename-btn rounded p-1 text-slate-400 hover:text-slate-700" data-id="${session.id}" title="Ganti nama"><i class="bi bi-pencil text-xs"></i></button>` +
                `<button type="button" class="delete-btn rounded p-1 text-slate-400 hover:text-red-500" data-id="${session.id}" title="Hapus"><i class="bi bi-trash3 text-xs"></i></button>` +
                `</span>`;
            item.addEventListener('click', (e) => {
                if (e.target.closest('.rename-btn') || e.target.closest('.delete-btn')) return;
                switchSession(session.id);
            });
            sessionList.appendChild(item);
        });
    };

    const loadSessions = async () => {
        try {
            const data = await api(chatUrl + '/sessions');
            sessions = data.sessions;
            renderSessions();
            return sessions;
        } catch {
            sessions = [];
            return [];
        }
    };

    const migrateLegacy = async (savedChats) => {
        try {
            const data = await api(chatUrl + '/sessions/migrate', {
                method: 'POST',
                body: JSON.stringify({ messages: savedChats.slice(-20).map(({ role, content }) => ({ role, content })) }),
            });
            if (data.migrated) {
                await loadSessions();
                if (data.session) await openSession(data.session.id);
            }
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // offline: keep localStorage until next attempt
        }
    };

    const openSession = async (id) => {
        currentSessionId = id;
        messages.innerHTML = '';
        renderSessions();
        try {
            const data = await api(`${chatUrl}/sessions/${id}/messages`);
            data.messages.forEach(({ role, content }) => appendBubble(role, content));
        } catch {
            appendBubble('assistant', 'Gagal memuat riwayat chat.', false);
        }
        if (messages.children.length === 0) {
            appendBubble('assistant', 'Halo! Saya Agro Bot. Tanyakan apa saja tentang budidaya selada hidroponik, atau data farm Anda.', false);
        }
        sidebar.classList.add('hidden');
        sidebar.classList.remove('flex');
    };

    const newSession = async () => {
        try {
            const data = await api(chatUrl + '/sessions', { method: 'POST' });
            sessions.unshift(data.session);
            await openSession(data.session.id);
            renderSessions();
        } catch {
            appendBubble('assistant', 'Gagal membuat sesi baru.', false);
        }
    };

    const switchSession = (id) => openSession(id);

    const renameSession = async (id, title) => {
        await api(`${chatUrl}/sessions/${id}`, {
            method: 'PATCH',
            body: JSON.stringify({ title }),
        });
        await loadSessions();
    };

    const deleteSession = async (id) => {
        if (!confirm('Hapus sesi ini beserta riwayatnya?')) return;
        await api(`${chatUrl}/sessions/${id}`, { method: 'DELETE' });
        if (currentSessionId === id) currentSessionId = null;
        await loadSessions();
        if (currentSessionId === null) {
            messages.innerHTML = '';
            appendBubble('assistant', 'Halo! Saya Agro Bot. Tanyakan apa saja tentang budidaya selada hidroponik, atau data farm Anda.', false);
        }
    };

    sessionList.addEventListener('click', (e) => {
        const renameBtn = e.target.closest('.rename-btn');
        const deleteBtn = e.target.closest('.delete-btn');
        if (renameBtn) {
            const id = Number(renameBtn.dataset.id);
            const session = sessions.find((s) => s.id === id);
            const title = prompt('Nama sesi:', session?.title || '');
            if (title !== null && title.trim()) renameSession(id, title.trim());
        }
        if (deleteBtn) deleteSession(Number(deleteBtn.dataset.id));
    });

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

    const send = async () => {
        const message = input.value.trim();
        if (!message || sendBtn.disabled) return;

        input.value = '';
        input.style.height = 'auto';
        appendBubble('user', message);
        showTyping();
        sendBtn.disabled = true;

        try {
            const data = await api(chatUrl, {
                method: 'POST',
                body: JSON.stringify({ session_id: currentSessionId, message }),
            });
            hideTyping();
            appendBubble('assistant', data.reply || 'Maaf, terjadi kesalahan. Silakan coba lagi.');
            if (currentSessionId === null) {
                currentSessionId = data.session_id;
                await loadSessions();
            } else if (data.title !== undefined) {
                await loadSessions();
            }
        } catch {
            hideTyping();
            appendBubble('assistant', 'Maaf, terjadi kesalahan koneksi. Silakan coba lagi.');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    };

    toggleBtn.addEventListener('click', async () => {
        panel.classList.toggle('hidden');
        panel.classList.toggle('flex');
        if (!panel.classList.contains('hidden')) {
            let list = await loadSessions();
            if (list.length === 0) {
                let saved = [];
                try {
                    saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                } catch {
                    localStorage.removeItem(STORAGE_KEY);
                }
                if (saved.length > 0) {
                    await migrateLegacy(saved);
                    return;
                }
                if (currentSessionId === null) {
                    messages.innerHTML = '';
                    appendBubble('assistant', 'Halo! Saya Agro Bot. Tanyakan apa saja tentang budidaya selada hidroponik, atau data farm Anda seperti PPM, pH, dan riwayat nutrisi.', false);
                }
            } else if (currentSessionId === null) {
                await openSession(list[0].id);
            }
        }
        input.focus();
    });

    sessionsBtn.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
        sidebar.classList.toggle('flex');
        renderSessions();
    });

    messages.addEventListener('click', () => {
        if (!sidebar.classList.contains('hidden')) {
            sidebar.classList.add('hidden');
            sidebar.classList.remove('flex');
            input.focus();
        }
    });

    document.getElementById('agroBotNewSession').addEventListener('click', () => newSession());

    closeBtn.addEventListener('click', () => {
        panel.classList.add('hidden');
        panel.classList.remove('flex');
    });

    clearBtn.addEventListener('click', async () => {
        if (currentSessionId === null || !confirm('Kosongkan riwayat chat sesi ini?')) return;
        await api(`${chatUrl}/sessions/${currentSessionId}/messages`, { method: 'DELETE' });
        messages.innerHTML = '';
        appendBubble('assistant', 'Sesi dikosongkan. Mulai pertanyaan baru di sesi ini.', false);
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
