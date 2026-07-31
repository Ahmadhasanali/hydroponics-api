<div id="agroBot" data-user-id="{{ auth()->id() }}"
     data-chat-url="{{ route('chat.send') }}"
     data-csrf="{{ csrf_token() }}">

    {{-- Floating button --}}
    <button id="agroBotToggle" type="button" aria-label="Buka chat Agro Bot"
        class="fixed bottom-6 right-6 z-50 inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#ffce54] text-[#1a1c1e] shadow-lg shadow-[#ffce54]/30 transition hover:bg-[#f0b830]">
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
            <button id="agroBotClear" type="button" title="Bersihkan chat"
                class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white">
                <i class="bi bi-trash3 text-sm"></i>
            </button>
            <button id="agroBotClose" type="button" title="Tutup"
                class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Messages --}}
        <div id="agroBotMessages" class="flex h-80 flex-col gap-3 overflow-y-auto px-4 py-4"></div>

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
