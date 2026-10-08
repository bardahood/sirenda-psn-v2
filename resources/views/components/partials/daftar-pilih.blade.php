{{-- Daftar centang untuk komponen multiPilih (dipakai filter global). --}}
<ul class="max-h-64 overflow-y-auto" role="listbox" aria-multiselectable="true">
    <template x-for="o in daftar" :key="o.nilai">
        <li>
            <label class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50">
                <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-aksen focus:ring-aksen" :checked="terpilih.includes(String(o.nilai))" @change="pilih(o.nilai)">
                <span class="text-isi" x-text="o.label"></span>
            </label>
        </li>
    </template>
    <li x-show="!daftar.length" class="px-2 py-1.5 text-label text-slate-500">Tidak ada pilihan.</li>
</ul>
<button type="button" class="mt-1 w-full rounded-md px-2 py-1 text-left text-label text-aksen-700 hover:bg-slate-50" @click="bersihkan()">Hapus pilihan</button>
