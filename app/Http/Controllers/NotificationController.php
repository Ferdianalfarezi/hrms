<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Toast notifikasi yang belum ditutup, untuk polling dari layout.
     */
    public function toasts(Request $request): View
    {
        return view('partials.toasts', ['toasts' => $request->user()->unreadNotifications]);
    }

    /**
     * Tandai notifikasi sudah dibaca (toast ditutup lewat tombol ×). Hanya notifikasi milik user sendiri.
     */
    public function read(Request $request, string $notification): Response
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->noContent();
    }
}
