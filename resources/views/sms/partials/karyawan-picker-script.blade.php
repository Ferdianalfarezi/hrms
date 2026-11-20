{{--
    JS untuk sms.partials.karyawan-picker. Karyawan dicari ke server saat mengetik (maks. 20 hasil),
    jadi halaman tidak memuat seluruh data karyawan. Nilai input hidden = JSON [{id, nama}];
    id null = nama lama yang dulu diketik manual (bisa dihapus, tidak bisa ditambah).
    Harus di-include sebelum patrol-script, karena patrol-script memanggil picker._sync() saat membuka form.
--}}
<script>
    (function () {
        var searchUrl = {{ Js::from(route('sms.karyawan-options')) }};
        var cache = {};

        function parse(value) {
            if (!value) return [];
            try {
                var items = JSON.parse(value);
                if (Array.isArray(items)) {
                    return items.filter(function (item) { return item && item.nama; });
                }
            } catch (error) { /* bukan JSON: nama lama dipisah koma */ }
            return String(value).split(',').map(function (nama) { return nama.trim(); })
                .filter(Boolean).map(function (nama) { return { id: null, nama: nama }; });
        }

        function keyOf(item) {
            return item.id ? 'id:' + item.id : 'nama:' + item.nama;
        }

        function fetchOptions(query, exclude) {
            var url = searchUrl + '?' + new URLSearchParams({ q: query, exclude: exclude.join(',') }).toString();
            if (!cache[url]) {
                cache[url] = fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) throw new Error(response.status);
                        return response.json();
                    })
                    .catch(function (error) { delete cache[url]; throw error; });
            }
            return cache[url];
        }

        document.querySelectorAll('[data-karyawan-picker]').forEach(function (picker) {
            var hidden = picker.querySelector('input[type=hidden]');
            var search = picker.querySelector('.kp-search');
            var chips = picker.querySelector('.kp-chips');
            var list = picker.querySelector('.kp-list');
            var selected = [];
            var shown = [];
            var active = 0;
            var timer = null;
            var request = 0;

            function save() {
                hidden.value = selected.length ? JSON.stringify(selected.map(function (item) { return { id: item.id || null, nama: item.nama }; })) : '';
                // Browser menolak submit kalau belum ada yang dipilih.
                search.required = selected.length === 0;
            }

            function renderChips() {
                chips.innerHTML = '';
                selected.forEach(function (item, index) {
                    var chip = document.createElement('span');
                    chip.className = 'kp-chip' + (item.id ? '' : ' kp-chip-legacy');
                    chip.textContent = item.nama;
                    if (!item.id) chip.title = __t('Nama lama, belum terhubung ke data karyawan');
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', 'Hapus ' + item.nama);
                    remove.addEventListener('click', function () {
                        selected.splice(index, 1);
                        update();
                        search.focus();
                    });
                    chip.appendChild(remove);
                    chips.appendChild(chip);
                });
            }

            function update() {
                save();
                renderChips();
            }

            function close() {
                clearTimeout(timer);
                request++;
                list.hidden = true;
                search.setAttribute('aria-expanded', 'false');
            }

            function message(text) {
                list.innerHTML = '';
                var empty = document.createElement('div');
                empty.className = 'kp-empty';
                empty.textContent = text;
                list.appendChild(empty);
                list.hidden = false;
                search.setAttribute('aria-expanded', 'true');
            }

            function renderList() {
                active = Math.min(active, Math.max(shown.length - 1, 0));
                if (!shown.length) return message(__t('Karyawan tidak ditemukan.'));

                list.innerHTML = '';
                shown.forEach(function (option, index) {
                    var item = document.createElement('div');
                    item.className = 'kp-item' + (index === active ? ' active' : '');
                    item.setAttribute('role', 'option');
                    var nama = document.createElement('b');
                    nama.textContent = option.nama;
                    var info = document.createElement('small');
                    info.textContent = option.info;
                    item.append(nama, info);
                    // mousedown (bukan click) supaya input pencarian tidak blur duluan.
                    item.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        pick(option);
                    });
                    list.appendChild(item);
                });

                list.hidden = false;
                search.setAttribute('aria-expanded', 'true');
                var current = list.querySelector('.kp-item.active');
                if (current) current.scrollIntoView({ block: 'nearest' });
            }

            // Cari ke server (ditunda sebentar selama user masih mengetik).
            function load(delay) {
                clearTimeout(timer);
                var ticket = ++request;
                if (list.hidden || !shown.length) message(__t('Mencari...'));
                timer = setTimeout(function () {
                    var exclude = selected.filter(function (item) { return item.id; }).map(function (item) { return item.id; });
                    fetchOptions(search.value.trim(), exclude).then(function (options) {
                        if (ticket !== request) return;
                        shown = options;
                        active = 0;
                        renderList();
                    }, function () {
                        if (ticket === request) message(__t('Gagal memuat data karyawan. Coba ketik lagi.'));
                    });
                }, delay);
            }

            function pick(option) {
                selected.push({ id: option.id, nama: option.nama });
                search.value = '';
                update();
                load(0);
            }

            // Klik area kotak (di luar chip) langsung fokus ke pencarian.
            picker.querySelector('.kp-box').addEventListener('mousedown', function (event) {
                if (event.target !== search && !event.target.closest('button')) {
                    event.preventDefault();
                    search.focus();
                }
            });
            search.addEventListener('focus', function () { load(0); });
            search.addEventListener('input', function () { load(250); });
            search.addEventListener('blur', close);
            search.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (list.hidden) return load(0);
                    if (!shown.length) return;
                    active = (active + (event.key === 'ArrowDown' ? 1 : -1) + shown.length) % shown.length;
                    renderList();
                } else if (event.key === 'Enter') {
                    // Enter memilih karyawan, bukan submit form.
                    event.preventDefault();
                    if (!list.hidden && shown[active]) pick(shown[active]);
                } else if (event.key === 'Backspace' && search.value === '' && selected.length) {
                    selected.pop();
                    update();
                    load(0);
                } else if (event.key === 'Escape' && !list.hidden) {
                    // Tutup daftar saja, modal tetap terbuka.
                    event.preventDefault();
                    event.stopPropagation();
                    close();
                }
            });

            // Dipanggil patrol-script setelah nilai input hidden diisi (tambah / edit / validasi gagal).
            picker._sync = function () {
                var seen = {};
                selected = parse(hidden.value).filter(function (item) {
                    var key = keyOf(item);
                    if (seen[key]) return false;
                    seen[key] = true;
                    return true;
                });
                search.value = '';
                close();
                update();
            };
            picker._sync();
        });
    })();
</script>
