<div class="flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ffce54]/20 text-2xl text-[#d4a020]">
        <i class="bi bi-buildings"></i>
    </div>
    <h2 class="mt-6 text-xl font-semibold text-slate-900">Belum Ada Farm</h2>
    <p class="mt-2 max-w-md text-sm text-slate-500">Anda belum terdaftar di farm manapun. Buat farm baru
        untuk memulai monitoring hidroponik.</p>
    <a href="{{ route('farm.create') }}"
        class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-[#ffce54] px-6 py-3 text-sm font-bold text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830]">
        <i class="bi bi-plus-lg"></i>
        Buat Farm Baru
    </a>
</div>
