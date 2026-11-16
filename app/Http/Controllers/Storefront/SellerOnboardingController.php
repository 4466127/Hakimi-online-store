<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SellerAddressRequest;
use App\Http\Requests\Storefront\SellerInfoRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Province;
use App\Models\Seller;
use App\Services\SellerOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * فورم چهارقدمی ثبت‌نام فروشنده (فقط برای مشتری واردشده).
 * هر قدم جدا ذخیره می‌شود؛ اگر فروشنده نیمه‌کاره رفت، بعداً از همان‌جا ادامه می‌دهد.
 */
class SellerOnboardingController extends Controller
{
    public function __construct(private readonly SellerOnboarding $onboarding) {}

    /** وضعیت درخواست؛ اگر هنوز پیش‌نویس است، رفتن به قدم فعلی. */
    public function index(Request $request): View|RedirectResponse
    {
        $seller = $this->seller($request);

        if ($seller->status->value === 'draft') {
            return redirect()->route('seller.step', ['step' => Seller::STEPS[$seller->onboarding_step]]);
        }

        return view('storefront.seller.status', ['seller' => $seller]);
    }

    public function show(Request $request, string $step): View|RedirectResponse
    {
        $seller = $this->seller($request);
        $number = $this->stepNumber($step);

        if (! $seller->canOpenStep($number)) {
            return redirect()->route('seller.onboarding');
        }

        return view('storefront.seller.onboarding', [
            'seller' => $seller,
            'step' => $number,
            'stepKey' => $step,
            'rootCategories' => Category::query()->active()->roots()->orderBy('position')->get(),
            'provinces' => Province::query()->active()->get(),
            'isComplete' => $this->onboarding->isComplete($seller),
        ]);
    }

    public function saveInfo(SellerInfoRequest $request): RedirectResponse
    {
        $seller = $this->seller($request);
        abort_unless($seller->canOpenStep(1), 403);

        $this->onboarding->saveInfo($seller, $request->validated());

        return redirect()->route('seller.step', ['step' => 'address']);
    }

    public function saveAddress(SellerAddressRequest $request): RedirectResponse
    {
        $seller = $this->seller($request);
        abort_unless($seller->canOpenStep(2), 403);

        $this->onboarding->saveAddress($seller, $request->validated());

        return redirect()->route('seller.step', ['step' => 'rules']);
    }

    public function acceptRules(Request $request): RedirectResponse
    {
        $seller = $this->seller($request);
        abort_unless($seller->canOpenStep(3), 403);

        $request->validate(['terms' => ['accepted']], ['terms.accepted' => __('seller.validation.rules_required')]);
        $this->onboarding->acceptRules($seller);

        return redirect()->route('seller.step', ['step' => 'review']);
    }

    public function submit(Request $request): RedirectResponse
    {
        $seller = $this->seller($request);
        abort_unless($seller->canOpenStep(4), 403);

        if (! $this->onboarding->isComplete($seller)) {
            return redirect()->route('seller.step', ['step' => 'info'])
                ->withErrors(['form' => __('seller.validation.incomplete')]);
        }

        $this->onboarding->submit($seller);

        return redirect()->route('seller.onboarding')->with('status', __('seller.status.submitted_flash'));
    }

    private function seller(Request $request): Seller
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return $this->onboarding->forCustomer($customer);
    }

    private function stepNumber(string $step): int
    {
        $number = array_search($step, Seller::STEPS, true);
        abort_if($number === false, 404);

        return (int) $number;
    }
}
