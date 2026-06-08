<?php

namespace App\Services\Parsing;

final class GenericBankParser implements BankParserInterface
{
    public function supports(string $packageName, ?string $bankName = null): bool
    {
        return true;
    }

    public function parse(array $notification, array $tenantConfig = []): array
    {
        $text = trim(($notification['title'] ?? '') . ' ' . ($notification['text'] ?? '') . ' ' . ($notification['big_text'] ?? ''));

        return [
            'amount' => $this->extractAmount($text),
            'currency' => 'VND',
            'direction' => $this->extractDirection($text),
            'order_code' => $this->extractOrderCode($text, $tenantConfig['order_code_pattern'] ?? null),
            'transfer_content' => $text,
            'account_number' => null,
            'confidence' => 0.5,
            'parser_name' => self::class,
        ];
    }

    private function extractAmount(string $text): ?int
    {
        if (! preg_match('/(?:\+|ghi co|ghi có|nhan|nhận)?\s*([0-9]{1,3}(?:[,.][0-9]{3})+|[0-9]{5,})\s*(?:vnd|vnđ|đ)?/iu', $text, $matches)) {
            return null;
        }

        return (int) str_replace([',', '.'], '', $matches[1]);
    }

    private function extractOrderCode(string $text, ?string $customPattern): ?string
    {
        $pattern = $customPattern ?: '/(TOUR|DH|ORDER|BK|INV)[A-Z0-9]{4,20}/i';
        return preg_match($pattern, $text, $matches) ? $matches[0] : null;
    }

    private function extractDirection(string $text): string
    {
        if (preg_match('/(ghi nợ|ghi no|debit|chuyển đi|chuyen di|thanh toán|thanh toan|rút tiền|rut tien)/iu', $text)) {
            return 'out';
        }

        if (preg_match('/(\+|nhận|nhan|ghi có|ghi co|credit|increase|tiền vào|tien vao)/iu', $text)) {
            return 'in';
        }

        return 'unknown';
    }
}
