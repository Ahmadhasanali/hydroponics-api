<div id="agroBot" data-user-id="{{ auth()->id() }}"
     data-chat-url="{{ route('chat.send') }}"
     data-csrf="{{ csrf_token() }}">

    {{-- Floating button --}}
    <button id="agroBotToggle" type="button" aria-label="Buka chat Agro Bot"
        class="fixed bottom-20 right-6 z-50 inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#ffce54] text-[#1a1c1e] shadow-lg shadow-[#ffce54]/30 transition hover:bg-[#f0b830] lg:bottom-6">
        <i class="bi bi-chat-dots text-2xl"></i>
    </button>

    {{-- Chat panel --}}
    <div id="agroBotPanel"
        class="fixed bottom-24 right-6 z-50 hidden w-[380px] max-w-[calc(100vw-3rem)] flex-col overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-2xl">

        {{-- Header --}}
        <div class="flex items-center gap-3 bg-[#1a1c1e] px-5 py-4 text-white">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e]">
                <i class="bi bi-flower1"></i>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold">Agro Bot</p>
                <p class="text-xs text-slate-400">Asisten Agrikultur &amp; Hidroponik</p>
            </div>
            <button id="agroBotSessions" type="button" title="Sesi chat"
                class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white">
                <i class="bi bi-clock-history text-sm"></i>
            </button>
            <button id="agroBotClear" type="button" title="Bersihkan chat"
                class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white">
                <i class="bi bi-trash3 text-sm"></i>
            </button>
            <button id="agroBotClose" type="button" title="Tutup"
                class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Messages + sessions sidebar --}}
        <div class="relative h-80 overflow-hidden">
            <div id="agroBotSidebar" class="absolute inset-y-0 left-0 z-10 hidden w-64 flex-col border-r border-slate-100 bg-white shadow-lg">
                <div class="flex items-center justify-between px-3 py-3">
                    <p class="text-xs font-semibold text-slate-500">SESI CHAT</p>
                    <button id="agroBotNewSession" type="button" title="Sesi baru"
                        class="inline-flex h-7 items-center gap-1 rounded-lg bg-[#ffce54] px-2 text-xs font-semibold text-[#1a1c1e] transition hover:bg-[#f0b830]">
                        <i class="bi bi-plus-lg text-xs"></i> Baru
                    </button>
                </div>
                <div id="agroBotSessionList" class="flex flex-1 flex-col gap-1 overflow-y-auto px-2 pb-3"></div>
            </div>
            <div id="agroBotMessages" class="flex h-full flex-col gap-3 overflow-y-auto px-4 py-4"></div>
        </div>

        {{-- Input --}}
        <form id="agroBotForm" class="border-t border-slate-100 p-3">
            <div class="flex items-end gap-2">
                <textarea id="agroBotInput" rows="1" maxlength="2000" placeholder="Tanya tentang selada atau data farm..."
                    class="flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"></textarea>
                <button id="agroBotSend" type="submit"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830] disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="bi bi-send-fill text-sm"></i>
                </button>
            </div>
        </form>
    </div>
</div>
