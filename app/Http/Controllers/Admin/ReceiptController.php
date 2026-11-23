<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * رسید چاپی یک پرداخت (مرحله‌ی ۳). فقط کاربران پنل که اجازه‌ی دیدن آن پرداخت را دارند
 * (`PaymentPolicy@view`؛ پیک فقط رسید پول‌هایی که خودش گرفته).
 *
 * آدرس با uuid است، نه شناسه‌ی عددی، تا با عوض کردن عدد در نوار آدرس رسید دیگری باز نشود.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, string $payment): View
    {
        $row = Payment::query()->where('uuid', $payment)
            ->with(['order.collectionPoint', 'collector', 'collectionPoint'])
            ->firstOrFail();

        $this->authorizeView($request, $row);

        return view('admin.receipt', ['payment' => $row, 'order' => $row->order]);
    }

    private function authorizeView(Request $request, Payment $payment): void
    {
        $user = $request->user();
        abort_if($user === null || ! $user->can('view', $payment), 403);
    }
}
