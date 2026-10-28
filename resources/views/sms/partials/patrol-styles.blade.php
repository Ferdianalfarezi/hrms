{{-- CSS bersama halaman Daily Patrol & Cross Patrol (di-include di dalam @section('styles')). --}}
    .dp-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .dp-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .filters input[type=search] { width: 260px; border-radius: 999px; }
    .filters input[type=date] { width: auto; border-radius: 999px; padding: 9px 14px; }
    .filters select { width: auto; min-width: 150px; border-radius: 999px; }
    .summary { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }
    .summary .chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; white-space: nowrap; background: var(--glass-strong); }

    table.patrol { font-size: 13px; }
    table.patrol th { white-space: nowrap; }
    table.patrol td { vertical-align: middle; }
    table.patrol .c-finding { min-width: 140px; max-width: 200px; font-weight: 500; color: var(--text); }
    table.patrol .c-bahaya { min-width: 120px; max-width: 180px; color: #b45309; }
    table.patrol .c-rekom { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); }
    table.patrol .c-pic { min-width: 100px; max-width: 150px; font-size: 12px; color: var(--muted); }
    td.nowrap { white-space: nowrap; }
    .thumb {
        display: block; width: 52px; height: 52px; padding: 0; border-radius: 10px; overflow: hidden; cursor: zoom-in;
        border: 1px solid var(--line-strong); background: var(--soft-2);
    }
    .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .thumb-empty { display: grid; place-items: center; width: 52px; height: 52px; border-radius: 10px; border: 1px dashed var(--line-strong); color: var(--faint); font-size: 11px; }

    .risk { display: inline-grid; place-items: center; min-width: 30px; height: 26px; padding: 0 8px; border-radius: 999px; font-weight: 700; font-size: 12px; }
    .risk-green { background: #dcfce7; color: #15803d; }
    .risk-orange { background: #ffedd5; color: #c2410c; }
    .risk-red { background: #fee2e2; color: #b91c1c; }
    .kat { display: inline-block; max-width: 160px; padding: 4px 10px; border-radius: 8px; background: var(--soft); color: var(--navy); font-size: 12px; font-weight: 600; line-height: 1.3; }
    .status-pill { display: inline-block; padding: 5px 16px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1.5px solid; white-space: nowrap; }
    .status-open { color: #b45309; border-color: #f59e0b; background: #fffbeb; }
    .status-closed { color: var(--success); border-color: #34d399; background: var(--success-bg); }
    .closed-by { display: block; margin-top: 4px; font-size: 11px; color: var(--muted); white-space: nowrap; }
    .closed-by b { color: var(--text); font-weight: 600; }
    .summary .chip.chip-open { background: #fffbeb; color: #b45309; }
    .summary .chip.chip-closed { background: var(--success-bg); color: var(--success); }

    table.patrol .c-finding .sub { display: block; margin-top: 2px; font-size: 12px; font-weight: 400; color: var(--muted); }

    /* Tombol aksi berupa icon */
    .act { display: inline-flex; gap: 4px; justify-content: flex-end; }
    .act-btn {
        width: 32px; height: 32px; padding: 0; display: grid; place-items: center; border-radius: 50%; cursor: pointer;
        background: var(--glass-strong); border: 1px solid var(--line-strong); color: var(--muted); transition: background .15s, color .15s;
    }
    .act-btn svg { width: 15px; height: 15px; }
    .act-btn:hover { background: #fff; color: var(--primary); border-color: var(--primary); }
    .act-btn.act-danger:hover { color: var(--danger); border-color: var(--danger); background: var(--danger-bg); }

    /* Modal detail */
    #detail-modal { width: min(720px, calc(100% - 32px)); }
    .detail-photos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .detail-photo { margin: 0; }
    .detail-photo figcaption { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
    .detail-photo button { display: block; width: 100%; padding: 0; border: 1px solid var(--line-strong); border-radius: 12px; overflow: hidden; cursor: zoom-in; background: var(--soft-2); aspect-ratio: 4 / 3; }
    .detail-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .detail-photo .none { display: grid; place-items: center; aspect-ratio: 4 / 3; border: 1px dashed var(--line-strong); border-radius: 12px; color: var(--faint); font-size: 13px; }
    .detail-list { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; margin: 0; }
    .detail-list > div { min-width: 0; }
    .detail-list .wide { grid-column: 1 / -1; }
    .detail-list dt { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-bottom: 2px; }
    .detail-list dd { margin: 0; font-size: 14px; color: var(--text); overflow-wrap: anywhere; }
    @media (max-width: 560px) { .detail-photos, .detail-list { grid-template-columns: 1fr; } }

    /* Form modal */
    #patrol-modal { width: min(760px, calc(100% - 32px)); }
    #patrol-modal .modal-body { display: grid; gap: 14px; }
    textarea { width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; color: var(--text); background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 12px; resize: vertical; min-height: 64px; }
    textarea:focus { outline: 2px solid var(--primary); outline-offset: -1px; background: #fff; }
    input[type=date] { width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; color: var(--text); background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 12px; }
    .locked-field { padding: 11px 14px; border-radius: 12px; background: #f4f6fa; border: 1px solid var(--line); color: var(--muted); }
    /* Field temuan (di luar kotak Perbaikan); dikunci abu-abu untuk Admin Departemen. */
    .patrol-main { display: grid; gap: 14px; min-width: 0; margin: 0; padding: 0; border: 0; }
    .patrol-main:disabled label { color: var(--muted); }
    .patrol-main:disabled :is(input, textarea, select, .kp-box, .locked-field) {
        background: #eef1f6; color: var(--muted); border-color: var(--line); cursor: not-allowed; box-shadow: none;
    }
    .patrol-main:disabled .kp-chip button, .patrol-main:disabled input[type=file], .patrol-main:disabled label .muted { display: none; }
    .patrol-main:disabled .kp-search::placeholder { color: transparent; }
    .patrol-main:disabled .photo-preview { filter: grayscale(.6); opacity: .8; }
    .locked-note { padding: 10px 14px; border-radius: 12px; font-size: 13px; color: var(--muted); background: var(--soft-2); border: 1px dashed var(--line-strong); }
    .photo-field { display: flex; gap: 12px; align-items: center; }
    .photo-preview { flex: none; width: 72px; height: 72px; border-radius: 12px; object-fit: cover; border: 1px solid var(--line-strong); background: var(--soft-2); }
    .photo-field input[type=file] { flex: 1; min-width: 0; font: inherit; font-size: 13px; }
    .fix-box { padding: 14px; border-radius: var(--radius-sm); background: var(--soft-2); border: 1px solid var(--line); display: grid; gap: 14px; }
    .fix-box h3 { font-size: 14px; }
    /* display: grid di atas mengalahkan atribut hidden, jadi disembunyikan eksplisit (kotak ini hanya untuk edit). */
    .fix-box[hidden] { display: none; }

    /* Lihat foto besar */
    #photo-modal { width: min(900px, calc(100% - 32px)); background: #0f1b33; }
    #photo-modal img { display: block; width: 100%; max-height: 80vh; object-fit: contain; }
    #photo-modal .modal-close { position: absolute; top: 10px; right: 10px; }

    /* Pilih karyawan (PIC / PIC Patrol / Patroler) */
    .kp-box {
        display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 45px; padding: 6px 8px; cursor: text;
        background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 12px;
    }
    .kp-box:focus-within { outline: 2px solid var(--primary); outline-offset: -1px; background: #fff; }
    .kp-chips { display: contents; }
    .kp-chip {
        display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 4px 4px 4px 10px; border-radius: 999px;
        background: var(--soft); color: var(--navy); font-size: 13px; font-weight: 600; overflow-wrap: anywhere;
    }
    .kp-chip button {
        width: 20px; height: 20px; flex: none; padding: 0; border: 0; border-radius: 50%; cursor: pointer;
        background: transparent; color: var(--navy); font-size: 15px; line-height: 1;
    }
    .kp-chip button:hover { background: rgba(30, 58, 138, .15); }
    .kp-box input.kp-search { flex: 1; min-width: 140px; width: auto; padding: 5px 6px; border: 0; background: transparent; outline: none; }
    .kp-list {
        margin-top: 4px; max-height: 220px; overflow-y: auto; border: 1px solid var(--line-strong); border-radius: 12px;
        background: #fff; box-shadow: 0 12px 28px rgba(15, 35, 80, .12);
    }
    .kp-item { display: flex; flex-direction: column; gap: 1px; padding: 8px 12px; cursor: pointer; }
    .kp-item b { font-size: 14px; font-weight: 600; color: var(--text); }
    .kp-item small { font-size: 12px; color: var(--muted); }
    .kp-item.active, .kp-item:hover { background: var(--soft-2); }
    .kp-empty { padding: 10px 12px; font-size: 13px; color: var(--muted); }
