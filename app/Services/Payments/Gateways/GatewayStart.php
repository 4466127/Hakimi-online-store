<?php

declare(strict_types=1);

namespace App\Services\Payments\Gateways;

use Carbon\CarbonInterface;

/** نتیجه‌ی شروع پرداخت آنلاین: مشتری به این آدرس فرستاده می‌شود. */
final class GatewayStart
{
    public function __construct(
        public readonly string $redirectUrl,
        /** شناسه‌ی پرداخت در خود درگاه (در `payments.provider_reference` می‌نشیند). */
        public readonly ?string $reference = null,
        public readonly ?CarbonInterface $expiresAt = null,
    ) {}
}
