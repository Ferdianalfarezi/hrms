{{--
    JS untuk sms.partials.karyawan-picker. Butuh: $karyawans (list [nama, info]).
    Harus di-include sebelum patrol-script, karena patrol-script memanggil picker._sync() saat membuka form.
--}}
<script>
    (function () {
        var options = {{ Js::from($karyawans) }};
        options.forEach(function (option) { option.key = (option.nama + ' ' + option.info).toLowerCase(); });

        function split(value) {
            var names = [];
            String(value || '').split(',').forEach(function (name) {
                name = name.trim();
                if (name && names.indexOf(name) === -1) names.push(name);
            });
            return names;
        }

        document.querySelectorAll('[data-karyawan-picker]').forEach(function (picker) {
            var hidden = picker.querySelector('input[type=hidden]');
            var search = picker.querySelector('.kp-search');
            var chips = picker.querySelector('.kp-chips');
            var list = picker.querySelector('.kp-list');
            var selected = [];
            var shown = [];
            var active = 0;

            function save() {
                hidden.value = selected.join(', ');
                // Browser menolak submit kalau belum ada yang dipilih.
                search.required = selected.length === 0;
            }

            function renderChips() {
                chips.innerHTML = '';
                selected.forEach(function (nama, index) {
                    var chip = document.createElement('span');
                    chip.className = 'kp-chip';
                    chip.textContent = nama;
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', 'Hapus ' + nama);
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
                list.hidden = true;
                search.setAttribute('aria-expanded', 'false');
            }

            function renderList() {
                var query = search.value.trim().toLowerCase();
                shown = options.filter(function (option) {
                    return selected.indexOf(option.nama) === -1 && (!query || option.key.indexOf(query) !== -1);
                }).slice(0, 50);
                active = Math.min(active, Math.max(shown.length - 1, 0));
                list.innerHTML = '';

                if (!shown.length) {
                    var empty = document.createElement('div');
                    empty.className = 'kp-empty';
                    empty.textContent = 'Karyawan tidak ditemukan.';
                    list.appendChild(empty);
                }
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
                        pick(option.nama);
                    });
                    list.appendChild(item);
                });

                list.hidden = false;
                search.setAttribute('aria-expanded', 'true');
                var current = list.querySelector('.kp-item.active');
                if (current) current.scrollIntoView({ block: 'nearest' });
            }

            function pick(nama) {
                selected.push(nama);
                search.value = '';
                update();
                renderList();
            }

            // Klik area kotak (di luar chip) langsung fokus ke pencarian.
            picker.querySelector('.kp-box').addEventListener('mousedown', function (event) {
                if (event.target !== search && !event.target.closest('button')) {
                    event.preventDefault();
                    search.focus();
                }
            });
            search.addEventListener('focus', function () { active = 0; renderList(); });
            search.addEventListener('input', function () { active = 0; renderList(); });
            search.addEventListener('blur', close);
            search.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (list.hidden) return renderList();
                    active = (active + (event.key === 'ArrowDown' ? 1 : -1) + shown.length) % Math.max(shown.length, 1);
                    renderList();
                } else if (event.key === 'Enter') {
                    // Enter memilih karyawan, bukan submit form.
                    event.preventDefault();
                    if (!list.hidden && shown[active]) pick(shown[active].nama);
                } else if (event.key === 'Backspace' && search.value === '' && selected.length) {
                    selected.pop();
                    update();
                    renderList();
                } else if (event.key === 'Escape' && !list.hidden) {
                    // Tutup daftar saja, modal tetap terbuka.
                    event.preventDefault();
                    event.stopPropagation();
                    close();
                }
            });

            // Dipanggil patrol-script setelah nilai input hidden diisi (tambah / edit / validasi gagal).
            picker._sync = function () {
                selected = split(hidden.value);
                search.value = '';
                close();
                update();
            };
            picker._sync();
        });
    })();
</script>
