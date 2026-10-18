<?php

namespace Vmt\PayFast\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Services\ItnParser;
use Vmt\PayFast\Services\SignatureService;

final class ProtocolTest extends TestCase
{
    public function test_checkout_encoding_and_empty_fields(): void
    {
        $this->assertSame(md5('merchant_id=123&item_name=A+%26+B&amount=1.05&passphrase=a+%26+b'), (new SignatureService)->checkout(['merchant_id' => '123', 'item_name' => ' A & B ', 'amount' => '1.05', 'email_address' => ''], 'a & b'));
    }

    public function test_itn_preserves_order_empty_values_and_whitespace(): void
    {
        $service = new SignatureService;
        $fields = ['item_name' => ' A & B ', 'custom_str1' => '', 'amount_gross' => '1.05'];
        $this->assertSame('item_name=+A+%26+B+&custom_str1=&amount_gross=1.05', $service->notificationString($fields));
        $this->assertSame(md5('item_name=+A+%26+B+&custom_str1=&amount_gross=1.05&passphrase=a+%26+b'), $service->notification($fields, 'a & b'));
    }

    public function test_parser_rejects_duplicate_fields(): void
    {
        $this->expectException(PaymentException::class);
        (new ItnParser)->parse('amount=1&amount=2&signature='.str_repeat('a', 32));
    }

    public function test_parser_rejects_unsigned_suffix(): void
    {
        $this->expectException(PaymentException::class);
        (new ItnParser)->parse('signature='.str_repeat('a', 32).'&amount=1');
    }

    public function test_state_machine_exhaustively(): void
    {
        foreach (PaymentStatus::cases() as $from) {
            foreach (PaymentStatus::cases() as $to) {
                $expected = ($from === PaymentStatus::Created && $to === PaymentStatus::Pending) || ($from === PaymentStatus::Pending && in_array($to, [PaymentStatus::Completed, PaymentStatus::Failed, PaymentStatus::Cancelled], true));
                $this->assertSame($expected, $from->allows($to));
            }
        }
    }
}
