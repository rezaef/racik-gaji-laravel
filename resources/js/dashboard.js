const $app = document.getElementById('app');
const store = {
    get token() { try { return localStorage.getItem('token'); } catch { return null; } },
    set token(v) { try { v ? localStorage.setItem('token', v) : localStorage.removeItem('token'); } catch {} },
};

const ROLES = { hr_admin: 'HR Admin', manager: 'Manajer', employee: 'Karyawan' };
const MODE_LABEL = { WFO: 'Kantor (WFO)', WFH: 'Rumah (WFH)', CLIENT_SITE: 'Lokasi Klien' };
const MODE_COLOR = { WFO: 'bg-indigo-500', WFH: 'bg-emerald-500', CLIENT_SITE: 'bg-amber-500' };
const STATUS_LABEL = { present: 'Hadir', late: 'Terlambat', leave: 'Cuti', absent: 'Absen' };
const PERIOD_STATUS = { draft: 'Draft', finalized: 'Final', paid: 'Dibayar' };

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const rupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(n) || 0);
const monthName = (m, y) => new Date(y, m - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });

async function api(path, { method = 'GET', body } = {}) {
    const res = await fetch('/api/' + path, {
        method,
        headers: {
            Accept: 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
            ...(store.token ? { Authorization: 'Bearer ' + store.token } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const json = await res.json().catch(() => ({}));
    if (res.status === 401) { store.token = null; showLogin(); throw new Error('Sesi berakhir.'); }
    if (!res.ok) {
        const first = json.errors ? Object.values(json.errors)[0][0] : null;
        throw new Error(first || json.message || 'Terjadi kesalahan.');
    }
    return json;
}

/* ---------- Komponen kecil ---------- */
const card = (title, inner, cls = '') => `
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 ${cls}">
        ${title ? `<h2 class="mb-4 text-sm font-semibold text-slate-500">${title}</h2>` : ''}${inner}
    </section>`;

const stat = (label, value, tone = 'text-slate-900') => `
    <div class="rounded-xl bg-slate-50 p-4">
        <div class="text-xs text-slate-500">${label}</div>
        <div class="mt-1 text-2xl font-semibold ${tone}">${value}</div>
    </div>`;

function modeBars(by) {
    const total = Object.values(by).reduce((a, b) => a + b, 0);
    if (!total) return '<p class="text-sm text-slate-400">Belum ada data.</p>';
    return Object.entries(by).map(([m, n]) => `
        <div class="mb-3 last:mb-0">
            <div class="mb-1 flex justify-between text-xs"><span>${MODE_LABEL[m] ?? m}</span><span class="font-medium">${n}</span></div>
            <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full ${MODE_COLOR[m] ?? 'bg-slate-400'}" style="width:${(n / total) * 100}%"></div></div>
        </div>`).join('');
}

function trendChart(rows) {
    if (!rows.length) return '<p class="text-sm text-slate-400">Belum ada data kehadiran.</p>';
    const max = Math.max(...rows.map((r) => r.present), 1);
    return `
        <div class="flex h-40 items-end gap-1.5">
            ${rows.map((r) => {
                const h = (r.present / max) * 100, lh = r.present ? (r.late / r.present) * 100 : 0;
                return `<div class="group flex h-full flex-1 flex-col justify-end" title="${r.date}: ${r.present} hadir, ${r.late} terlambat">
                    <div class="flex flex-col overflow-hidden rounded-t bg-indigo-500" style="height:${h}%"><div class="bg-amber-400" style="height:${lh}%"></div></div>
                </div>`;
            }).join('')}
        </div>
        <div class="mt-2 flex justify-between text-[11px] text-slate-400"><span>${rows[0].date.slice(5)}</span><span>${rows.at(-1).date.slice(5)}</span></div>
        <div class="mt-3 flex gap-4 text-xs text-slate-500">
            <span><i class="mr-1 inline-block size-2 rounded-sm bg-indigo-500"></i>Hadir</span>
            <span><i class="mr-1 inline-block size-2 rounded-sm bg-amber-400"></i>Terlambat</span>
        </div>`;
}

function payrollChart(rows) {
    if (!rows.length) return '<p class="text-sm text-slate-400">Belum ada periode penggajian.</p>';
    const list = [...rows].reverse();
    const max = Math.max(...list.map((p) => Number(p.total_net) || 0), 1);
    return `
        <div class="flex h-40 items-end gap-3">
            ${list.map((p) => `
                <div class="flex h-full flex-1 flex-col items-center justify-end" title="${rupiah(p.total_net)}">
                    <div class="w-full rounded-t bg-emerald-500" style="height:${((Number(p.total_net) || 0) / max) * 100}%"></div>
                </div>`).join('')}
        </div>
        <div class="mt-2 flex gap-3 text-[11px] text-slate-400">
            ${list.map((p) => `<span class="flex-1 text-center">${monthName(p.month, p.year).replace(/ \d{4}$/, '')}</span>`).join('')}
        </div>
        <table class="mt-4 w-full text-sm">
            ${rows.slice(0, 3).map((p) => `
                <tr class="border-t border-slate-100">
                    <td class="py-2">${monthName(p.month, p.year)}</td>
                    <td class="py-2 text-slate-500">${PERIOD_STATUS[p.status] ?? esc(p.status)}</td>
                    <td class="py-2 text-right font-medium">${rupiah(p.total_net)}</td>
                </tr>`).join('')}
        </table>`;
}

/* ---------- Login ---------- */
async function showLogin(error = '') {
    let demos = [];
    try { demos = (await api('auth/demo-accounts')).data.filter((d) => d.role === 'hr_admin'); } catch {}
    $app.innerHTML = `
        <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
            <h1 class="text-3xl font-semibold tracking-tight">Manajemen Gaji</h1>
            <p class="mt-1 mb-6 text-slate-500">Manajemen gaji karyawan untuk Admin &amp; HR.</p>
            ${card('', `
                <form id="login" class="space-y-3">
                    <input name="email" type="email" required placeholder="Email" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                    <input name="password" type="password" required placeholder="Kata sandi" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                    <p id="err" class="text-sm text-rose-600">${esc(error)}</p>
                    <button class="w-full rounded-lg bg-indigo-600 py-2 font-medium text-white hover:bg-indigo-700">Masuk</button>
                </form>`)}
            ${demos.length ? `<div class="mt-6"><p class="mb-2 text-xs text-slate-500">Atau coba akun demo HR:</p>
                <div class="grid gap-2">${demos.map((d) => `
                    <button data-role="${esc(d.role)}" class="demo rounded-lg bg-white px-4 py-2 text-left text-sm ring-1 ring-slate-200 hover:ring-indigo-400">
                        <span class="font-medium">${esc(d.name)}</span> <span class="text-slate-500">· ${ROLES[d.role] ?? esc(d.role)}${d.position ? ' · ' + esc(d.position) : ''}</span>
                    </button>`).join('')}</div></div>` : ''}
            <p class="mt-10 text-center text-xs text-slate-400">&copy; ${new Date().getFullYear()} Racik Solusi Teknologi &middot; Versi demo</p>
        </main>`;

    const done = async (p) => {
        try { store.token = (await p).token; showDashboard(); }
        catch (e) { document.getElementById('err').textContent = e.message; }
    };
    document.getElementById('login').onsubmit = (e) => {
        e.preventDefault();
        done(api('auth/login', { method: 'POST', body: Object.fromEntries(new FormData(e.target)) }));
    };
    document.querySelectorAll('.demo').forEach((b) => b.onclick = () => done(api('auth/demo-login', { method: 'POST', body: { role: b.dataset.role } })));
}

/* ---------- Kerangka & navigasi ---------- */
const NAV = [['#/dashboard', 'Dashboard'], ['#/karyawan', 'Karyawan'], ['#/penggajian', 'Penggajian']];
const input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
const btn = 'rounded-lg px-3 py-2 text-sm font-medium';
const btnPrimary = `${btn} bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50`;
const btnGhost = `${btn} ring-1 ring-slate-300 hover:bg-slate-50 disabled:opacity-50`;

function shell(me, active, content) {
    $app.innerHTML = `
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
            <div class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">M</span>
                <span class="leading-tight"><span class="block text-sm font-semibold tracking-tight">Manajemen Gaji</span><span class="block text-[11px] text-slate-400">Portal Admin &amp; HR</span></span>
            </div>
            <nav class="flex gap-1 text-sm">${NAV.map(([h, l]) => `<a href="${h}" class="rounded-lg px-3 py-1.5 ${h === active ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-slate-600 hover:bg-slate-100'}">${l}</a>`).join('')}</nav>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden md:inline text-slate-500">${esc(me.name)}</span>
                <button id="out" class="${btnGhost}">Keluar</button>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-6xl space-y-6 px-4 py-6">${content}</main>
    <footer class="mx-auto max-w-6xl px-4 py-6 text-center text-xs text-slate-400">&copy; ${new Date().getFullYear()} Racik Solusi Teknologi &middot; Versi demo</footer>`;
    document.getElementById('out').onclick = logout;
}

async function download(path, filename) {
    const res = await fetch('/api/' + path, { headers: { Authorization: 'Bearer ' + store.token } });
    if (!res.ok) throw new Error('Gagal mengunduh berkas.');
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await res.blob()), download: filename });
    a.click();
}

async function openPdf(id) {
    const res = await fetch(`/api/payslips/${id}/pdf`, { headers: { Authorization: 'Bearer ' + store.token } });
    if (!res.ok) throw new Error('Gagal membuat PDF slip gaji.');
    const w = window.open(URL.createObjectURL(await res.blob()), '_blank');
    if (!w) throw new Error('Pop-up diblokir browser. Izinkan pop-up lalu coba lagi.');
}

async function showDashboard() {
    $app.innerHTML = '<p class="p-10 text-center text-slate-400">Memuat…</p>';
    let me;
    try { me = (await api('auth/me')).data; }
    catch (e) {
        if (store.token) { $app.innerHTML = `<p class="p-10 text-center text-rose-600">${esc(e.message)} <button id="out" class="ml-2 underline">Keluar</button></p>`; document.getElementById('out').onclick = logout; }
        return;
    }
    if (me.role !== 'hr_admin') {
        store.token = null;
        return showLogin('Aplikasi ini khusus Admin/HR. Akun Anda tidak memiliki akses.');
    }
    const route = NAV.find(([h]) => h === location.hash)?.[0] ?? NAV[0][0];
    const view = { '#/dashboard': viewDashboard, '#/karyawan': viewEmployees, '#/penggajian': viewPayroll }[route];
    try { await view(me, route); }
    catch (e) { shell(me, route, `<p class="text-rose-600">${esc(e.message)}</p>`); }
}
window.addEventListener('hashchange', () => store.token && showDashboard());

/* ---------- Dashboard ---------- */
async function viewDashboard(me, route) {
    const d = (await api('dashboard')).data;
    const t = d.team, a = d.pending_approvals, periods = d.payroll ?? [];
    const pendingTotal = a.leave + a.overtime + a.corrections;
    const latest = periods[0];

    shell(me, route, `
        <div>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p class="text-sm text-slate-500">${new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</p>
        </div>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            ${stat('Karyawan aktif', t.headcount)}
            ${stat(latest ? 'Gaji bersih ' + monthName(latest.month, latest.year) : 'Gaji bersih', latest ? rupiah(latest.total_net) : '–', 'text-emerald-700')}
            ${stat('Status periode terbaru', latest ? (PERIOD_STATUS[latest.status] ?? esc(latest.status)) : 'Belum ada')}
            ${stat('Persetujuan tertunda', pendingTotal, pendingTotal ? 'text-amber-600' : '')}
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            ${card('Penggajian (6 periode terakhir)', payrollChart(periods) + '<a href="#/penggajian" class="mt-4 inline-block text-sm text-indigo-600 hover:underline">Kelola penggajian →</a>', 'lg:col-span-2')}
            ${card('Menunggu persetujuan', `
                <div class="grid grid-cols-3 gap-3">${stat('Cuti', a.leave)}${stat('Lembur', a.overtime)}${stat('Koreksi', a.corrections)}</div>
                <p class="mt-4 text-xs text-slate-400">Lembur dan koreksi absensi memengaruhi hitungan gaji periode berjalan.</p>`)}
        </div>
        <h2 class="pt-2 text-lg font-semibold">Kehadiran</h2>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            ${stat('Hadir hari ini', t.present_today, 'text-emerald-600')}
            ${stat('Terlambat', t.late_today, t.late_today ? 'text-amber-600' : '')}
            ${stat('Sedang cuti', t.on_leave_today)}
            ${stat('Belum absen', Math.max(t.headcount - t.present_today - t.on_leave_today, 0))}
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            ${card('Kehadiran 14 hari terakhir', trendChart(t.trend), 'lg:col-span-2')}
            ${card('Mode kerja bulan ini', modeBars(t.by_mode_month))}
        </div>`);
}

/* ---------- Penggajian ---------- */
async function viewPayroll(me, route) {
    const periods = (await api('payroll/periods')).data;
    const now = new Date();
    const selected = Number(sessionStorage.getItem('period')) || periods[0]?.id;
    const cur = periods.find((p) => p.id === selected) ?? periods[0];

    shell(me, route, `
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-2xl font-semibold">Penggajian</h1>
            <form id="gen" class="flex flex-wrap items-center gap-2">
                <select name="month" class="${input} !w-auto">${Array.from({ length: 12 }, (_, i) => `<option value="${i + 1}" ${i === now.getMonth() ? 'selected' : ''}>${new Date(2000, i).toLocaleDateString('id-ID', { month: 'long' })}</option>`).join('')}</select>
                <input name="year" type="number" value="${now.getFullYear()}" class="${input} !w-24">
                <button class="${btnPrimary}">Buat / hitung ulang periode</button>
            </form>
        </div>
        <div id="msg" class="hidden rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"></div>
        ${cur ? `
        <div class="flex flex-wrap items-center gap-2">
            <select id="period" class="${input} !w-auto">${periods.map((p) => `<option value="${p.id}" ${p.id === cur.id ? 'selected' : ''}>${monthName(p.month, p.year)} · ${PERIOD_STATUS[p.status] ?? esc(p.status)}</option>`).join('')}</select>
            ${cur.status === 'draft' ? `<button id="finalize" class="${btnPrimary}">Finalisasi</button>` : ''}
            ${cur.status === 'finalized' ? `<button id="paid" class="${btnPrimary}">Tandai dibayar</button>` : ''}
            <button id="csv" class="${btnGhost}">Ekspor CSV</button>
            <button id="printall" class="${btnGhost}">Cetak semua slip</button>
        </div>
        <div id="detail" class="space-y-4"><p class="text-slate-400">Memuat…</p></div>`
        : card('', '<p class="text-slate-500">Belum ada periode penggajian. Pilih bulan di atas lalu klik “Buat / hitung ulang periode”.</p>')}`);

    const msg = (text) => { const el = document.getElementById('msg'); el.textContent = text; el.classList.remove('hidden'); };
    const guard = (el, fn) => async (...a) => { el.disabled = true; try { await fn(...a); } catch (e) { msg(e.message); } el.disabled = false; };

    const gen = document.getElementById('gen');
    gen.onsubmit = guard(gen.querySelector('button'), async (e) => {
        e.preventDefault();
        const f = Object.fromEntries(new FormData(gen));
        const exist = periods.find((p) => p.month == f.month && p.year == f.year);
        if (exist && !confirm(`Periode ${monthName(f.month, f.year)} sudah ada. Hitung ulang semua slip (hanya bisa saat masih draft)?`)) return;
        const res = await api('payroll/generate', { method: 'POST', body: { month: +f.month, year: +f.year } });
        sessionStorage.setItem('period', res.data.id);
        showDashboard();
    });
    if (!cur) return;

    const $period = document.getElementById('period');
    $period.onchange = () => { sessionStorage.setItem('period', $period.value); showDashboard(); };
    const changeStatus = (id, action, ask) => {
        const el = document.getElementById(id);
        if (el) el.onclick = guard(el, async () => { if (!confirm(ask)) return; await api(`payroll/periods/${cur.id}/${action}`, { method: 'POST' }); showDashboard(); });
    };
    changeStatus('finalize', 'finalize', 'Finalisasi mengunci periode ini dan tidak bisa dihitung ulang. Lanjutkan?');
    changeStatus('paid', 'mark-paid', 'Tandai periode ini sudah dibayarkan?');
    const csv = document.getElementById('csv');
    csv.onclick = guard(csv, () => download(`payroll/periods/${cur.id}/export`, `rekap-gaji-${cur.year}-${String(cur.month).padStart(2, '0')}.csv`));

    const detail = (await api('payroll/periods/' + cur.id)).data;
    const slips = [...(detail.payslips ?? [])].sort((x, y) => String(x.employee?.employee_number).localeCompare(String(y.employee?.employee_number)));
    const tot = detail.totals;
    document.getElementById('detail').innerHTML = `
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            ${stat('Karyawan', tot.employees)}${stat('Gaji kotor', rupiah(tot.gross))}${stat('Potongan', rupiah(tot.deductions), 'text-rose-600')}${stat('Gaji bersih', rupiah(tot.net), 'text-emerald-700')}
        </div>
        ${card('', slips.length ? `<div class="overflow-x-auto text-sm"><table class="w-full">
            <thead><tr class="text-left text-xs text-slate-500"><th class="py-2">No. Karyawan</th><th>Nama</th><th class="text-right">Hari hadir</th><th class="text-right">Gaji kotor</th><th class="text-right">Potongan</th><th class="text-right">Gaji bersih</th><th></th></tr></thead>
            <tbody>${slips.map((x) => `<tr class="border-t border-slate-100">
                <td class="py-2 text-slate-500">${esc(x.employee?.employee_number)}</td><td>${esc(x.employee?.full_name)}</td>
                <td class="text-right">${x.present_days ?? '–'}</td><td class="text-right">${rupiah(x.gross_salary)}</td>
                <td class="text-right text-rose-600">${rupiah(x.total_deductions)}</td><td class="text-right font-medium">${rupiah(x.net_salary)}</td>
                <td class="text-right"><button data-print="${x.id}" class="rounded-lg px-3 py-1 text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50">Cetak</button></td></tr>`).join('')}</tbody>
        </table></div>` : '<p class="text-slate-400">Periode ini belum punya slip gaji.</p>')}`;

    document.querySelectorAll('[data-print]').forEach((b) => b.onclick = guard(b, () => openPdf(b.dataset.print)));
    const all = document.getElementById('printall');
    all.onclick = guard(all, async () => {
        if (!slips.length) throw new Error('Tidak ada slip untuk dicetak.');
        if (slips.length > 5 && !confirm(`Buka ${slips.length} tab PDF sekaligus?`)) return;
        for (const x of slips) await openPdf(x.id);
    });
}

/* ---------- Karyawan ---------- */
const PTKP = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];
const FIELDS = [
    ['full_name', 'Nama lengkap', 'text', true], ['email', 'Email', 'email', true],
    ['password', 'Kata sandi', 'password'], ['role', 'Peran', 'role', true],
    ['position', 'Jabatan', 'text', true], ['department', 'Departemen', 'text', true],
    ['join_date', 'Tanggal masuk', 'date', true], ['manager_id', 'Atasan', 'manager'],
    ['ptkp_status', 'Status PTKP', 'ptkp', true], ['npwp', 'NPWP', 'text'],
    ['phone', 'No. telepon', 'text'], ['bank_name', 'Bank', 'text'], ['bank_account', 'No. rekening', 'text'],
    ['basic_salary', 'Gaji pokok (Rp)', 'number', true], ['fixed_allowance', 'Tunjangan tetap (Rp)', 'number'],
    ['meal_allowance_daily', 'Uang makan / hari (Rp)', 'number'], ['transport_allowance_daily', 'Transport / hari (Rp)', 'number'],
];

async function viewEmployees(me, route) {
    const q = sessionStorage.getItem('emp_q') ?? '', st = sessionStorage.getItem('emp_status') ?? 'active';
    const qs = new URLSearchParams({ per_page: 100, ...(q && { search: q }), ...(st && { status: st }) });
    const res = await api('employees?' + qs);
    const rows = res.data;

    shell(me, route, `
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h1 class="text-2xl font-semibold">Karyawan</h1><p class="text-sm text-slate-500">${res.total ?? rows.length} data</p></div>
            <button id="add" class="${btnPrimary}">+ Tambah karyawan</button>
        </div>
        <div id="msg" class="hidden rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"></div>
        <div class="flex flex-wrap gap-2">
            <input id="q" value="${esc(q)}" placeholder="Cari nama atau nomor karyawan" class="${input} !w-72">
            <select id="st" class="${input} !w-auto">${[['active', 'Aktif'], ['inactive', 'Nonaktif'], ['', 'Semua']].map(([v, l]) => `<option value="${v}" ${v === st ? 'selected' : ''}>${l}</option>`).join('')}</select>
        </div>
        ${card('', rows.length ? `<div class="overflow-x-auto text-sm"><table class="w-full">
            <thead><tr class="text-left text-xs text-slate-500"><th class="py-2">No. Karyawan</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th>Masuk</th><th class="text-right">Gaji pokok</th><th>Status</th><th></th></tr></thead>
            <tbody>${rows.map((e) => `<tr class="border-t border-slate-100">
                <td class="py-2 text-slate-500">${esc(e.employee_number)}</td>
                <td><div class="font-medium">${esc(e.full_name)}</div><div class="text-xs text-slate-400">${esc(e.user?.email)} · ${ROLES[e.user?.role] ?? ''}</div></td>
                <td>${esc(e.position)}</td><td>${esc(e.department)}</td><td>${esc(String(e.join_date).slice(0, 10))}</td>
                <td class="text-right">${rupiah(e.basic_salary)}</td>
                <td><span class="rounded-full px-2 py-0.5 text-xs ${e.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">${e.status === 'active' ? 'Aktif' : 'Nonaktif'}</span></td>
                <td class="whitespace-nowrap text-right"><button data-edit="${e.id}" class="text-indigo-600 hover:underline">Ubah</button>
                    ${e.status === 'active' ? `<button data-off="${e.id}" class="ml-3 text-rose-600 hover:underline">Nonaktifkan</button>` : ''}</td>
            </tr>`).join('')}</tbody></table></div>` : '<p class="text-slate-400">Tidak ada data karyawan.</p>')}`);

    const msg = (text) => { const el = document.getElementById('msg'); el.textContent = text; el.classList.remove('hidden'); };
    let timer;
    document.getElementById('q').oninput = (e) => { clearTimeout(timer); timer = setTimeout(() => { sessionStorage.setItem('emp_q', e.target.value); showDashboard(); }, 400); };
    document.getElementById('st').onchange = (e) => { sessionStorage.setItem('emp_status', e.target.value); showDashboard(); };
    document.getElementById('add').onclick = () => employeeForm(null, rows).catch((e) => msg(e.message));
    document.querySelectorAll('[data-edit]').forEach((b) => b.onclick = () => employeeForm(rows.find((r) => r.id == b.dataset.edit), rows).catch((e) => msg(e.message)));
    document.querySelectorAll('[data-off]').forEach((b) => b.onclick = async () => {
        const e = rows.find((r) => r.id == b.dataset.off);
        if (!confirm(`Nonaktifkan ${e.full_name}? Riwayat absensi dan slip gaji tetap tersimpan, tetapi akun tidak bisa login.`)) return;
        try { await api('employees/' + e.id, { method: 'DELETE' }); showDashboard(); } catch (err) { msg(err.message); }
    });
}

async function employeeForm(emp, loaded) {
    const managers = (await api('employees?per_page=100&status=active')).data.filter((m) => m.id !== emp?.id);
    const val = (k) => (k === 'email' || k === 'role' ? emp?.user?.[k] : emp?.[k]) ?? (k === 'join_date' ? '' : '');
    const field = ([k, label, type, req]) => {
        const v = esc(k === 'join_date' ? String(val(k)).slice(0, 10) : val(k));
        const reqAttr = req && !(k === 'password') ? 'required' : '';
        let el;
        if (type === 'role') el = `<select name="${k}" class="${input}">${Object.entries(ROLES).map(([r, l]) => `<option value="${r}" ${val(k) === r || (!emp && r === 'employee') ? 'selected' : ''}>${l}</option>`).join('')}</select>`;
        else if (type === 'ptkp') el = `<select name="${k}" class="${input}">${PTKP.map((p) => `<option ${val(k) === p ? 'selected' : ''}>${p}</option>`).join('')}</select>`;
        else if (type === 'manager') el = `<select name="${k}" class="${input}"><option value="">— Tidak ada —</option>${managers.map((m) => `<option value="${m.id}" ${emp?.manager_id == m.id ? 'selected' : ''}>${esc(m.full_name)}</option>`).join('')}</select>`;
        else el = `<input name="${k}" type="${type}" value="${type === 'password' ? '' : v}" ${reqAttr} ${k === 'password' ? (emp ? 'placeholder="Kosongkan bila tidak diubah"' : 'required minlength="8"') : ''} ${type === 'number' ? 'min="0"' : ''} class="${input}">`;
        return `<label class="block text-sm"><span class="mb-1 block text-xs text-slate-500">${label}${req ? ' *' : ''}</span>${el}</label>`;
    };

    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-10 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4';
    modal.innerHTML = `
        <form class="my-8 w-full max-w-3xl rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold">${emp ? 'Ubah karyawan · ' + esc(emp.employee_number) : 'Tambah karyawan'}</h2>
            ${emp ? '' : '<p class="mt-1 text-xs text-slate-400">Nomor karyawan dibuat otomatis (RG-tahun-urut) berdasarkan tanggal masuk.</p>'}
            <div class="mt-4 grid gap-3 sm:grid-cols-2">${FIELDS.map(field).join('')}</div>
            <p class="err mt-3 text-sm text-rose-600"></p>
            <div class="mt-4 flex justify-end gap-2"><button type="button" class="cancel ${btnGhost}">Batal</button><button class="${btnPrimary}">Simpan</button></div>
        </form>`;
    document.body.appendChild(modal);
    const close = () => modal.remove();
    modal.querySelector('.cancel').onclick = close;
    modal.onmousedown = (e) => { if (e.target === modal) close(); };

    const form = modal.querySelector('form');
    form.onsubmit = async (e) => {
        e.preventDefault();
        const body = {};
        for (const [k, , type] of FIELDS) {
            let v = form.elements[k].value.trim();
            if (type === 'number') v = v === '' ? undefined : parseInt(v, 10);
            else if (v === '') v = (k === 'password' || ['fixed_allowance'].includes(k)) ? undefined : null;
            if (v !== undefined) body[k] = v;
        }
        if (body.manager_id) body.manager_id = +body.manager_id;
        const submit = form.querySelector('button:not(.cancel)');
        submit.disabled = true;
        try {
            await api(emp ? 'employees/' + emp.id : 'employees', { method: emp ? 'PUT' : 'POST', body });
            close(); showDashboard();
        } catch (err) { modal.querySelector('.err').textContent = err.message; submit.disabled = false; }
    };
}

async function logout() {
    try { await api('auth/logout', { method: 'POST' }); } catch {}
    store.token = null;
    showLogin();
}

store.token ? showDashboard() : showLogin();
