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
