<?php

namespace App\Services\Parsing;

interface BankParserInterface
{
    public function supports(string $packageName, ?string $bankName = null): bool;

    /** @return array<string, mixed> */
    public function parse(array $notification, array $tenantConfig = []): array;
}
