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

        // If custom rules are defined, try parsing with them
        $rules = $tenantConfig['bank_rules'] ?? null;
        if ($rules && !empty($rules['regex'])) {
            $parsed = $this->parseWithCustomRules($text, $rules);
            if ($parsed !== null) {
                return $parsed;
            }
        }

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

    private function parseWithCustomRules(string $text, array $rules): ?array
    {
        $regex = $rules['regex'];

        // Ensure delimiters exist in PHP regex
        if (!str_starts_with($regex, '/') && !str_starts_with($regex, '#')) {
            $regex = '/' . $regex . '/iu';
        }

        if (!@preg_match($regex, $text, $matches)) {
            return null; // Regex failed or didn't match
        }

        $amountGroup = $rules['amount_group'] ?? null;
        $directionGroup = $rules['direction_group'] ?? null;
        $orderCodeGroup = $rules['order_code_group'] ?? null;
        $transferContentGroup = $rules['transfer_content_group'] ?? null;

        $amount = null;
        $direction = 'unknown';
        $orderCode = null;
        $transferContent = $text;

        // Extract Amount
        if ($amountGroup !== null && isset($matches[$amountGroup])) {
            $rawAmount = $matches[$amountGroup];
            $amount = (int) str_replace([',', '.'], '', $rawAmount);
        } else {
            $amount = $this->extractAmount($text);
        }

        // Extract Direction
        if ($directionGroup !== null && isset($matches[$directionGroup])) {
            $rawDir = strtolower(trim($matches[$directionGroup]));
            if (in_array($rawDir, ['+', 'in', 'ghi có', 'ghi co', 'nhận', 'nhan'])) {
                $direction = 'in';
            } elseif (in_array($rawDir, ['-', 'out', 'ghi nợ', 'ghi no', 'chuyển', 'chuyen', 'thanh toán', 'thanh toan'])) {
                $direction = 'out';
            }
        }

        if ($direction === 'unknown') {
            $direction = $this->extractDirection($text);
        }

        // Extract Order Code
        if ($orderCodeGroup !== null && isset($matches[$orderCodeGroup])) {
            $orderCode = trim($matches[$orderCodeGroup]);
        } else {
            $orderCode = $this->extractOrderCode($text, null);
        }

        // Extract Transfer Content
        if ($transferContentGroup !== null && isset($matches[$transferContentGroup])) {
            $transferContent = trim($matches[$transferContentGroup]);
        }

        return [
            'amount' => $amount,
            'currency' => 'VND',
            'direction' => $direction,
            'order_code' => $orderCode,
            'transfer_content' => $transferContent,
            'account_number' => null,
            'confidence' => 0.9,
            'parser_name' => self::class . '@custom',
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
        $pattern = $customPattern ?: '/(NTR|TOUR|DH|ORDER|BK|INV)[A-Z0-9-]{4,25}/i';
        return preg_match($pattern, $text, $matches) ? $matches[0] : null;
    }

    private function extractDirection(string $text): string
    {
        // Prioritize explicit sign matches near the transaction amount
        if (preg_match('/[+-]\s*[0-9]{1,3}(?:[,.][0-9]{3})+/', $text, $matches)) {
            return str_contains($matches[0], '+') ? 'in' : 'out';
        }

        // Fallback to checking keywords, but prioritize 'in' keywords that indicate credit/receive
        if (preg_match('/(\+|ghi có|ghi co|credit|increase|tiền vào|tien vao)/iu', $text)) {
            return 'in';
        }

        if (preg_match('/(ghi nợ|ghi no|debit|chuyển đi|chuyen di|rút tiền|rut tien)/iu', $text)) {
            return 'out';
        }

        if (preg_match('/(nhận|nhan)/iu', $text) && !preg_match('/(người nhận|nguoi nhan)/iu', $text)) {
            return 'in';
        }

        if (preg_match('/(thanh toán|thanh toan)/iu', $text)) {
            return 'out';
        }

        return 'unknown';
    }
}
