{{--
    JS bersama Daily Patrol & Cross Patrol: lihat foto, modal tambah/edit/hapus, kompres foto, aturan Close.
    Butuh: $fields (nama field form), $routePrefix (mis. 'sms.daily-patrols').
--}}
<script>
    (function () {
        // Klik foto (di tabel maupun di modal detail): tampilkan ukuran besar.
        // Dipasang di <body> yang ikut diganti Turbo tiap pindah halaman, jadi tidak terpasang dobel.
        var photoModal = document.getElementById('photo-modal');
        document.body.addEventListener('click', function (event) {
            var button = event.target.closest('[data-photo]');
            if (!button || photoModal.open) return;
            document.getElementById('photo-modal-img').src = button.dataset.photo;
            photoModal.showModal();
        });

        // Icon mata: tampilkan semua atribut temuan di modal detail.
        var detailModal = document.getElementById('detail-modal');
        var detailId = null;
        document.querySelectorAll('[data-detail]').forEach(function (button) {
            button.addEventListener('click', function () {
                var template = document.getElementById('detail-' + button.dataset.detail);
                if (!template) return;
                detailId = button.dataset.detail;
                var body = document.getElementById('detail-body');
                body.innerHTML = '';
                body.appendChild(template.content.cloneNode(true));
                document.getElementById('detail-sub').textContent = template.dataset.sub || '';
                detailModal.querySelector('[data-detail-action="print"]').hidden = !document.querySelector('[data-print="' + detailId + '"]');
                detailModal.showModal();
            });
        });
        // Tombol Print / Edit di modal detail menjalankan icon yang sama di baris tabelnya.
        detailModal.querySelectorAll('[data-detail-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.querySelector('[data-' + button.dataset.detailAction + '="' + detailId + '"]');
                detailModal.close();
                if (target) target.click();
            });
        });

        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { dialog.close(); });
            });
            dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
        });

        var modal = document.getElementById('patrol-modal');
        if (!modal) return;

        var fields = {{ Js::from($fields) }};
        var form = document.getElementById('patrol-form');
        var storeUrl = {{ Js::from(route($routePrefix.'.store')) }};
        var updateUrl = {{ Js::from(route($routePrefix.'.update', '__ID__')) }};
        var destroyUrl = {{ Js::from(route($routePrefix.'.destroy', '__ID__')) }};
        var today = {{ Js::from(now()->toDateString()) }};
        var statusSelect = form.elements.status;
        var closeOption = statusSelect.querySelector('option[value=closed]');
        var hasFixPhoto = false;

        function setPreview(id, url) {
            var img = document.getElementById(id);
            img.hidden = !url;
            if (url) img.src = url; else img.removeAttribute('src');
        }

        // Close hanya bisa dipilih kalau foto perbaikan sudah ada (tersimpan atau baru dipilih).
        function syncStatus() {
            var ready = hasFixPhoto || form.elements.foto_perbaikan.files.length > 0;
            closeOption.disabled = !ready;
            if (!ready) statusSelect.value = 'open';
            document.getElementById('status-hint').hidden = ready;
        }

        // Foto dari HP biasanya besar: perkecil di browser (sisi terpanjang 1600 px, JPEG) sebelum diupload.
        function compress(input) {
            var file = input.files[0];
            if (!file || !/^image\//.test(file.type) || typeof DataTransfer === 'undefined') return Promise.resolve();

            return new Promise(function (resolve) {
                var img = new Image();
                var url = URL.createObjectURL(file);
                img.onload = function () {
                    URL.revokeObjectURL(url);
                    var scale = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
                    if (scale === 1 && file.size < 1.5 * 1024 * 1024) return resolve();
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.naturalWidth * scale);
                    canvas.height = Math.round(img.naturalHeight * scale);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(function (blob) {
                        if (blob && blob.size < file.size) {
                            var transfer = new DataTransfer();
                            transfer.items.add(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
                            input.files = transfer.files;
                        }
                        resolve();
                    }, 'image/jpeg', 0.85);
                };
                img.onerror = function () { URL.revokeObjectURL(url); resolve(); };
                img.src = url;
            });
        }

        var pending = [];
        form.querySelectorAll('[data-photo-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                var file = input.files[0];
                setPreview(input.dataset.preview, file ? URL.createObjectURL(file) : input._savedUrl);
                if (input.name === 'foto_perbaikan') syncStatus();
                var job = compress(input);
                pending.push(job);
                job.then(function () { pending.splice(pending.indexOf(job), 1); });
            });
        });

        // Tunggu kompres foto selesai sebelum form benar-benar dikirim.
        form.addEventListener('submit', function (event) {
            if (!pending.length) return;
            event.preventDefault();
            var submit = document.getElementById('patrol-submit');
            submit.disabled = true;
            submit.textContent = 'Menyiapkan foto...';
            Promise.all(pending).then(function () {
                submit.disabled = false;
                submit.textContent = 'Simpan';
                form.requestSubmit();
            });
        });

        function openForm(id, record, keepErrors) {
            var editing = !!id;
            form.reset();
            form.action = editing ? updateUrl.replace('__ID__', id) : storeUrl;
            form.elements._method.value = editing ? 'PUT' : 'POST';
            form.elements._modal.value = editing ? 'edit:' + id : 'create';
            document.getElementById('patrol-modal-title').textContent = editing ? 'Edit Temuan' : 'Tambah Temuan';

            fields.forEach(function (name) {
                var el = form.elements[name];
                if (!el || el.type === 'file') return;
                el.value = (record && record[name]) || '';
            });
            // Pilih karyawan (PIC dst.) ikut menampilkan nilai yang baru diisi.
            form.querySelectorAll('[data-karyawan-picker]').forEach(function (picker) { if (picker._sync) picker._sync(); });
            if (!form.elements.tanggal.value) form.elements.tanggal.value = today;
            if (!statusSelect.value) statusSelect.value = 'open';

            // Foto wajib saat tambah; saat edit boleh dikosongkan (foto lama dipakai).
            form.elements.foto.required = !editing;
            form.elements.foto._savedUrl = record && record.foto_url;
            form.elements.foto_perbaikan._savedUrl = record && record.foto_perbaikan_url;
            setPreview('preview-foto', record && record.foto_url);
            setPreview('preview-foto_perbaikan', record && record.foto_perbaikan_url);
            hasFixPhoto = !!(record && record.foto_perbaikan_url);

            modal.querySelectorAll('[data-edit-only]').forEach(function (el) { el.hidden = !editing; });
            statusSelect.disabled = !editing;
            form.elements.foto_perbaikan.disabled = !editing;
            syncStatus();
            if (record && record.status === 'closed' && !closeOption.disabled) statusSelect.value = 'closed';

            if (!keepErrors) modal.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
            modal.showModal();
            form.elements.tanggal.focus();
        }

        function recordOf(id) {
            var button = document.querySelector('[data-edit="' + id + '"]');
            return button ? JSON.parse(button.dataset.record) : null;
        }

        var createButton = document.querySelector('[data-create]');
        if (createButton) createButton.addEventListener('click', function () { openForm(null, null); });
        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () { openForm(button.dataset.edit, recordOf(button.dataset.edit)); });
        });
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('delete-form').action = destroyUrl.replace('__ID__', button.dataset.delete);
                document.getElementById('delete-name').textContent = button.dataset.name;
                document.getElementById('delete-modal').showModal();
            });
        });

        // Validasi gagal: buka lagi modal yang tadi dipakai beserta isiannya (foto perlu dipilih ulang).
        var reopen = {{ Js::from($errors->hasAny($fields) ? old('_modal') : null) }};
        if (reopen) {
            var editId = reopen.indexOf('edit:') === 0 ? reopen.slice(5) : null;
            var old = {{ Js::from(collect(old())->only($fields)) }};
            openForm(editId, Object.assign({}, editId ? recordOf(editId) : {}, old), true);
        }
    })();
</script>
