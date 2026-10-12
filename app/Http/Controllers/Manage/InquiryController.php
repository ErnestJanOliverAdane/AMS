<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\GuestInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $handled = $request->query('status') === 'handled';

        return view('manage.inquiries.index', [
            'inquiries' => GuestInquiry::where('status', $handled ? 'handled' : 'new')->latest()->paginate(15)->withQueryString(),
            'handled' => $handled,
        ]);
    }

    public function handle(GuestInquiry $inquiry): RedirectResponse
    {
        $inquiry->update(['status' => 'handled', 'handled_at' => now()]);

        return back()->with('status', 'Marked as handled.');
    }
}
