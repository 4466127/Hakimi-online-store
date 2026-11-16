<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SellerStatus;
use App\Models\Customer;
use App\Models\Seller;
use App\Models\User;
use App\Services\Commission\SellerIncentives;
use LogicException;

/**
 * ثبت‌نام و بررسی فروشنده («در بازارک بفروشید»).
 *
 * مشتری فورم چهارقدمی را پر می‌کند و می‌فرستد؛ مدیر از پنل تأیید یا رد می‌کند.
 * هر تغییر وضعیت در تاریخچه‌ی فعالیت نوشته می‌شود.
 */
class SellerOnboarding
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly SellerIncentives $incentives,
    ) {}

    /** فروشنده‌ی این مشتری؛ اگر نبود، یک پیش‌نویس تازه ساخته می‌شود. */
    public function forCustomer(Customer $customer): Seller
    {
        // همیشه از دیتابیس خوانده شود (نه از رابطه‌ی cache‌شده) تا دو بار ساخته نشود
        $seller = Seller::query()->where('customer_id', $customer->id)->first();
        if ($seller !== null) {
            return $seller;
        }

        $seller = new Seller;
        $seller->customer_id = $customer->id;
        $seller->full_name = $customer->name;
        $seller->save();

        return $seller;
    }

    /** @param  array<string, mixed>  $data */
    public function saveInfo(Seller $seller, array $data): void
    {
        $this->ensureEditable($seller);

        $isBusiness = ($data['type'] ?? null) === 'business';
        $seller->fill([
            'type' => $data['type'],
            'full_name' => $data['full_name'],
            'father_name' => $data['father_name'],
            'tazkira_number' => $data['tazkira_number'],
            'store_name' => $data['store_name'],
            'business_name' => $isBusiness ? ($data['business_name'] ?? null) : null,
            'license_number' => $isBusiness ? ($data['license_number'] ?? null) : null,
            'is_local_producer' => (bool) ($data['is_local_producer'] ?? false),
            'is_social_seller' => (bool) ($data['is_social_seller'] ?? false),
            'social_url' => ! empty($data['is_social_seller']) ? ($data['social_url'] ?? null) : null,
            'has_brand' => (bool) ($data['has_brand'] ?? false),
            'brand_name' => ! empty($data['has_brand']) ? ($data['brand_name'] ?? null) : null,
            'category_ids' => array_values(array_map('intval', $data['category_ids'] ?? [])),
        ]);
        $seller->completeStep(1);
        $seller->save();
    }

    /** @param  array<string, mixed>  $data */
    public function saveAddress(Seller $seller, array $data): void
    {
        $this->ensureEditable($seller);

        $seller->fill([
            'province_id' => (int) $data['province_id'],
            'city' => $data['city'],
            'address' => $data['address'],
            'phone' => $data['phone'] ?? null,
        ]);
        $seller->completeStep(2);
        $seller->save();
    }

    public function acceptRules(Seller $seller): void
    {
        $this->ensureEditable($seller);

        $seller->terms_accepted_at = now();
        $seller->completeStep(3);
        $seller->save();
    }

    /** درخواست برای بررسی مدیر فرستاده می‌شود. */
    public function submit(Seller $seller): void
    {
        $this->ensureEditable($seller);
        if (! $this->isComplete($seller)) {
            throw new LogicException('Seller application is not complete.');
        }

        $old = $seller->status;
        $seller->forceFill([
            'status' => SellerStatus::Pending,
            'submitted_at' => now(),
            'review_note' => null,
        ])->saveQuietly();

        $this->logger->log('seller.submitted', $seller, ['status' => ['old' => $old->value, 'new' => SellerStatus::Pending->value]]);
    }

    /** همه‌ی قدم‌ها پر شده است؟ */
    public function isComplete(Seller $seller): bool
    {
        return $seller->type !== null
            && filled($seller->full_name) && filled($seller->father_name)
            && filled($seller->tazkira_number) && filled($seller->store_name)
            && ! empty($seller->category_ids)
            && $seller->province_id !== null && filled($seller->city) && filled($seller->address)
            && $seller->terms_accepted_at !== null;
    }

    public function approve(Seller $seller, User $reviewer): void
    {
        $this->review($seller, $reviewer, SellerStatus::Approved, null, 'seller.approved');
        // مزایای شروع (کمیسیون ۰٪ اینستاگرامی، گرفتن رایگان جنس برای برند، …) فقط بار اول
        $this->incentives->applyOnFirstApproval($seller, $reviewer);
    }

    public function reject(Seller $seller, User $reviewer, string $reason): void
    {
        $this->review($seller, $reviewer, SellerStatus::Rejected, $reason, 'seller.rejected');
    }

    public function suspend(Seller $seller, User $reviewer, string $reason): void
    {
        $this->review($seller, $reviewer, SellerStatus::Suspended, $reason, 'seller.suspended');
    }

    private function review(Seller $seller, User $reviewer, SellerStatus $status, ?string $note, string $action): void
    {
        $old = $seller->status;
        $seller->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->saveQuietly();

        $this->logger->log($action, $seller, array_filter([
            'status' => ['old' => $old->value, 'new' => $status->value],
            'review_note' => $note !== null ? ['old' => null, 'new' => $note] : null,
        ]), $reviewer);
    }

    private function ensureEditable(Seller $seller): void
    {
        if (! $seller->status->isEditable()) {
            throw new LogicException('Seller application cannot be edited now.');
        }
    }
}
