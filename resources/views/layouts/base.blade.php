<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HR System') · {{ config('app.name') }}</title>
    @stack('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --page: #c9d3e3;
            --shell-top: #f5f8fe; --shell-bottom: #e3ebf8;
            --glass: rgba(255, 255, 255, .9); --glass-strong: rgba(255, 255, 255, .96);
            --line: rgba(15, 35, 80, .08); --line-strong: rgba(15, 35, 80, .14);
            --text: #0f1b33; --muted: #6b7a95; --faint: #9aa7bf;
            --primary: #2563eb; --primary-hover: #1d4fd0; --navy: #1e3a8a; --navy-deep: #172d6b;
            --soft: #dbe6fa; --soft-2: #eef3fd;
            --danger: #c2410c; --danger-bg: #fff1eb; --success: #047857; --success-bg: #e8f8f1;
            --radius: 22px; --radius-sm: 14px;
            --shadow: 0 1px 2px rgba(15, 35, 80, .04), 0 12px 32px rgba(30, 64, 175, .08);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background:
                radial-gradient(1200px 600px at 0% 0%, rgba(147, 180, 245, .35), transparent 60%),
                radial-gradient(900px 500px at 100% 100%, rgba(120, 160, 240, .25), transparent 60%),
                linear-gradient(160deg, #e4ecfa 0%, #dbe5f7 50%, #e2eafa 100%);
            background-attachment: fixed;
            color: var(--text); line-height: 1.5; font-size: 14px; -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        h1, h2, h3 { margin: 0; font-weight: 600; letter-spacing: -.01em; }
        button { font-family: inherit; }

        /* Area aplikasi memakai lebar penuh layar */
        .shell { padding: 18px 32px 32px; min-height: 100vh; }
        @media (max-width: 720px) {
            .shell { padding: 14px 16px 24px; }
        }

        /* Navbar lengket: menutup lebar shell (padding dibalik dengan margin negatif) dan memburamkan isi di belakangnya. */
        .app-nav {
            position: sticky; top: 0; z-index: 30; margin: -18px -32px 24px; padding: 18px 32px 0;
            background: rgba(226, 234, 249, .82); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            box-shadow: 0 1px 0 var(--line), 0 8px 24px rgba(30, 64, 175, .06);
        }
        .app-nav > .topbar:last-child { margin-bottom: 0; padding-bottom: 14px; }
        @media (max-width: 720px) {
            .app-nav { margin: -14px -16px 20px; padding: 14px 16px 0; }
        }

        /* Topbar dengan tab */
        .topbar { display: flex; align-items: center; gap: 16px; margin-bottom: 18px; }
        .brand {
            flex: none; padding: 8px 18px; border-radius: 999px; border: 1px solid var(--line-strong);
            background: var(--glass); font-weight: 600; font-size: 15px;
        }
        .tabs {
            flex: 1; display: flex; justify-content: flex-end; gap: 4px; overflow-x: auto; scrollbar-width: none;
        }
        .tabs::-webkit-scrollbar { display: none; }
        .tabs-inner { display: flex; gap: 4px; padding: 4px; border-radius: 999px; background: var(--glass); border: 1px solid rgba(255,255,255,.9); }
        .tab {
            white-space: nowrap; padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 500; color: var(--muted);
            transition: background .15s, color .15s;
        }
        .tab:hover { color: var(--text); background: rgba(255, 255, 255, .8); }
        .tab.active { background: var(--navy); color: #fff; box-shadow: 0 4px 12px rgba(30, 58, 138, .3); }
        .top-actions { flex: none; display: flex; align-items: center; gap: 8px; }
        .icon-btn {
            width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; cursor: pointer;
            background: var(--glass); border: 1px solid rgba(255,255,255,.9); color: var(--muted);
        }
        .icon-btn:hover { color: var(--primary); background: #fff; }
        .icon-btn svg { width: 18px; height: 18px; }
        @media (max-width: 900px) {
            .topbar { flex-wrap: wrap; }
            .tabs { order: 3; flex-basis: 100%; justify-content: flex-start; }
            .top-actions { margin-left: auto; }
        }

        /* Tab menu modul (baris kedua) */
        .subtabs { display: flex; flex-wrap: wrap; gap: 0 24px; margin: -8px 0 0; position: relative; z-index: 20; }
        .subtab { padding: 10px 2px; font-size: 14px; font-weight: 500; color: var(--muted); border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
        .subtab:hover { color: var(--text); }
        .subtab.active { color: var(--navy); border-bottom-color: var(--navy); font-weight: 600; }
        button.subtab { display: inline-flex; align-items: center; gap: 4px; background: none; border-width: 0 0 2px; border-style: solid; font-family: inherit; cursor: pointer; }
        .subtab-drop { position: relative; display: flex; }
        .subtab-menu {
            position: absolute; top: 100%; left: -8px; min-width: 150px; padding: 6px; margin-top: 4px; display: none;
            background: #fff; border-radius: var(--radius-sm); border: 1px solid var(--line); box-shadow: 0 12px 32px rgba(30, 64, 175, .14);
        }
        /* Jembatan tak terlihat supaya dropdown tidak tertutup saat kursor turun dari tab ke menu. */
        .subtab-menu::before { content: ""; position: absolute; left: 0; right: 0; top: -8px; height: 8px; }
        .subtab-drop:hover .subtab-menu, .subtab-drop:focus-within .subtab-menu { display: block; }
        .subtab-menu a { display: block; padding: 8px 12px; border-radius: 10px; font-size: 13px; font-weight: 500; color: var(--text); }
        .subtab-menu a:hover { background: var(--soft-2); }
        .subtab-menu a.active { background: var(--soft); color: var(--navy); font-weight: 600; }

        /* Komponen dasar */
        .card {
            background: var(--glass); border: 1px solid rgba(255, 255, 255, .9); border-radius: var(--radius);
            padding: 20px; box-shadow: var(--shadow); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
        }
        .card-title { font-size: 16px; font-weight: 600; }
        .muted { color: var(--muted); }
        .small { font-size: 12px; }
        .avatar { border-radius: 50%; object-fit: cover; background: var(--soft); flex: none; display: block; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 999px;
            padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; background: var(--primary); color: #fff;
            transition: background .15s;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-ghost { background: var(--glass-strong); color: var(--text); border: 1px solid var(--line-strong); }
        .btn-ghost:hover { background: #fff; }
        .btn-danger { background: transparent; color: var(--danger); border: 1px solid var(--line-strong); }
        .btn-danger:hover { background: var(--danger-bg); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .badge {
            display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 3px 10px;
            font-size: 11px; font-weight: 600; background: var(--soft); color: var(--navy); white-space: nowrap;
        }
        .badge-off { background: #eef0f4; color: var(--muted); }
        .badge-warn { background: #fff4d6; color: #8a5a00; }
        .alert, .success { border-radius: var(--radius-sm); padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
        .alert { background: var(--danger-bg); color: var(--danger); }
        .success { background: var(--success-bg); color: var(--success); }

        /* Form */
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type=text], input[type=password], input[type=email], input[type=search], select {
            width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; color: var(--text);
            background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 12px;
        }
        input:focus, select:focus { outline: 2px solid var(--primary); outline-offset: -1px; background: #fff; }
        .check { display: flex; align-items: center; gap: 8px; font-weight: 500; margin: 0; }
        .field-error { color: var(--danger); font-size: 12px; margin-top: 4px; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }

        /* Modal (pakai elemen <dialog>) */
        dialog.modal {
            border: 0; padding: 0; border-radius: var(--radius); width: min(480px, calc(100% - 32px));
            background: #fff; color: var(--text); box-shadow: 0 30px 80px rgba(15, 35, 80, .25);
        }
        dialog.modal::backdrop { background: rgba(15, 27, 51, .35); backdrop-filter: blur(3px); }
        dialog.modal[open] { animation: modal-in .16s ease-out; }
        @keyframes modal-in { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
        .modal-head { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px 0; }
        .modal-head h2 { font-size: 18px; }
        .modal-close { width: 32px; height: 32px; border-radius: 50%; border: 0; background: var(--soft-2); color: var(--muted); cursor: pointer; font-size: 18px; line-height: 1; }
        .modal-close:hover { background: var(--soft); color: var(--text); }
        .modal-body { padding: 20px 24px; }
        .modal-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 0 24px 20px; }

        /* Tabel */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        th { color: var(--muted); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
        tbody tr:last-child td { border-bottom: 0; }
        .pagination { display: flex; gap: 6px; list-style: none; padding: 0; margin: 16px 0 0; flex-wrap: wrap; }
        .pagination li > * { display: block; padding: 6px 12px; border-radius: 999px; font-size: 13px; background: var(--glass-strong); border: 1px solid var(--line); }
        .pagination li.disabled > * { color: var(--faint); }
        .pagination li.active > * { background: var(--navy); border-color: var(--navy); color: #fff; }

        /* Toast notifikasi pojok kanan bawah (gaya kartu kaca seperti dashboard) */
        .toast-stack {
            position: fixed; right: 20px; bottom: 20px; z-index: 60; width: min(350px, calc(100% - 32px));
            display: flex; flex-direction: column; gap: 12px; max-height: calc(100vh - 40px); overflow-y: auto; padding: 6px;
        }
        .toast {
            padding: 16px; border-radius: var(--radius); background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(255, 255, 255, .95); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 1px 2px rgba(15, 35, 80, .06), 0 20px 48px rgba(30, 64, 175, .22);
            animation: toast-in .5s cubic-bezier(.22, 1, .36, 1) backwards;
        }
        .toast.seen { animation: none; }
        .toast.leaving { animation: toast-out .25s ease-in forwards; }
        @keyframes toast-in { from { opacity: 0; transform: translateY(16px) scale(.96); } }
        @keyframes toast-out { to { opacity: 0; transform: translateX(32px) scale(.97); } }
        .toast-head { display: flex; align-items: center; gap: 12px; }
        .toast-ico {
            position: relative; flex: none; width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center;
            background: var(--navy); color: #fff; box-shadow: 0 6px 14px rgba(30, 58, 138, .3);
        }
        .toast-ico svg { width: 18px; height: 18px; }
        .toast-ico::after {
            content: ""; position: absolute; top: 1px; right: 1px; width: 10px; height: 10px; border-radius: 50%;
            background: #ef4444; border: 2px solid #fff;
        }
        .toast-title { font-weight: 600; font-size: 14px; color: var(--text); }
        .toast-sub { font-size: 12px; color: var(--muted); }
        .toast-close {
            margin-left: auto; align-self: flex-start; flex: none; width: 28px; height: 28px; border-radius: 50%; border: 0; cursor: pointer;
            background: var(--soft-2); color: var(--muted); font-size: 16px; line-height: 1;
        }
        .toast-close:hover { background: var(--soft); color: var(--text); }
        .toast-metric { display: flex; align-items: center; gap: 12px; margin: 14px 0 12px; }
        .toast-metric b { font-size: 38px; font-weight: 400; letter-spacing: -.04em; line-height: 1; color: var(--text); }
        .toast-metric span { font-size: 12px; color: var(--muted); line-height: 1.4; }
        .toast-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .toast-chip {
            max-width: 130px; padding: 5px 10px; border-radius: 999px; background: var(--soft-2); border: 1px solid var(--line);
            font-size: 12px; font-weight: 500; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .toast-chip.more { background: var(--soft); color: var(--navy); font-weight: 700; cursor: help; }
        .toast-foot { display: flex; justify-content: flex-end; margin-top: 14px; }
        .toast-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 999px;
            background: var(--navy); color: #fff; font-size: 13px; font-weight: 600; transition: background .15s, gap .15s;
        }
        .toast-btn:hover { background: var(--navy-deep); gap: 9px; }
        @media (max-width: 720px) { .toast-stack { right: 12px; bottom: 12px; } }

        /* Progress bar Turbo saat pindah halaman */
        .turbo-progress-bar { height: 3px; background: var(--primary); }
    </style>

    {{-- Turbo: pindah menu & submit form tanpa reload penuh (hanya isi <body> yang diganti). --}}
    <script type="module" src="{{ asset('vendor/turbo/turbo.es2017-esm.js') }}"></script>
    <script>
        // Modal yang sedang terbuka ditutup sebelum halaman disimpan ke cache Turbo,
        // supaya saat tombol Back ditekan halaman tidak muncul dengan modal terbuka.
        document.addEventListener('turbo:before-cache', function () {
            document.querySelectorAll('dialog[open]').forEach(function (dialog) { dialog.close(); });
        });

        // Toast notifikasi: animasi masuk hanya sekali, toast yang sudah ditutup tidak muncul lagi dari cache Turbo,
        // dan daftar toast dicek ulang ke server tiap 15 detik supaya notifikasi baru muncul tanpa refresh.
        window.hrToasts = window.hrToasts || (function () {
            function read(key) {
                try { return JSON.parse(sessionStorage.getItem(key) || '[]'); } catch (e) { return []; }
            }
            function write(key, ids) {
                try { sessionStorage.setItem(key, JSON.stringify(ids.slice(-50))); } catch (e) {}
            }

            function prepare(stack) {
                if (!stack) return;
                var closed = read('toast-closed'), seen = read('toast-seen');
                stack.querySelectorAll('[data-toast]').forEach(function (toast) {
                    var id = toast.dataset.toast;
                    if (closed.indexOf(id) !== -1) return toast.remove();
                    if (seen.indexOf(id) !== -1) toast.classList.add('seen');
                    else seen.push(id);
                });
                write('toast-seen', seen);
            }

            function poll() {
                var stack = document.getElementById('toast-stack');
                if (!stack || document.hidden) return;

                fetch(stack.dataset.pollUrl, { headers: { 'Accept': 'text/html' } })
                    .then(function (response) { return response.ok ? response.text() : null; })
                    .then(function (html) {
                        if (html === null) return;
                        var holder = document.createElement('div');
                        holder.innerHTML = html;
                        var fresh = {};
                        holder.querySelectorAll('[data-toast]').forEach(function (toast) { fresh[toast.dataset.toast] = toast; });

                        // Toast yang sudah tidak ada di server (mis. diganti upload ulang) dibuang, yang baru ditambahkan.
                        stack.querySelectorAll('[data-toast]').forEach(function (toast) {
                            if (fresh[toast.dataset.toast]) delete fresh[toast.dataset.toast];
                            else if (!toast.classList.contains('leaving')) toast.remove();
                        });
                        Object.keys(fresh).forEach(function (id) { stack.appendChild(fresh[id]); });
                        prepare(stack);
                    })
                    .catch(function () {});
            }

            setInterval(poll, 15000);
            // Tab yang sedang tidak dibuka tidak dicek; begitu dibuka lagi langsung dicek.
            document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

            return { prepare: prepare, poll: poll };
        })();

        // Tombol × pada toast notifikasi: tandai sudah dibaca di server, lalu hilangkan.
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-toast-close]');
            if (!button) return;

            var toast = button.closest('[data-toast]');
            fetch(toast.dataset.readUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            // Diingat juga di browser, supaya tidak muncul lagi dari cache Turbo saat tombol Back ditekan.
            try {
                var closed = JSON.parse(sessionStorage.getItem('toast-closed') || '[]');
                sessionStorage.setItem('toast-closed', JSON.stringify(closed.concat(toast.dataset.toast).slice(-50)));
            } catch (e) {}
            toast.classList.add('leaving');
            setTimeout(function () { toast.remove(); }, 250);
        });
    </script>
</head>
<body>
    {{-- CSS khusus halaman ditaruh di body supaya ikut terganti saat pindah halaman lewat Turbo. --}}
    <style>
        @yield('styles')
    </style>
    @yield('body')
    @stack('scripts')
</body>
</html>
