<?php

namespace Tests\Unit\Rules;

use App\Rules\NoTempEmail;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoTempEmailTest extends TestCase
{
    #[DataProvider('validEmails')]
    public function test_valid_emails_pass(string $email): void
    {
        $validator = Validator::make(['email' => $email], ['email' => [new NoTempEmail]]);

        $this->assertFalse($validator->fails(), "{$email} seharusnya lolos");
    }

    #[DataProvider('disposableEmails')]
    public function test_disposable_emails_fail(string $email): void
    {
        $validator = Validator::make(['email' => $email], ['email' => [new NoTempEmail]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Email sementara (temporary email) tidak diizinkan. Gunakan alamat email permanen.',
            $validator->errors()->first('email'),
        );
    }

    public static function validEmails(): array
    {
        return [
            ['petani@example.com'],
            ['user@gmail.com'],
            ['orang@yahoo.co.id'],
            ['ALI@Example.COM'],
        ];
    }

    public static function disposableEmails(): array
    {
        return [
            ['user@temp-mail.org'],
            ['user@guerrillamail.com'],
            ['user@yopmail.com'],
            ['user@MAILINATOR.com'],
            ['user@10minutemail.com'],
        ];
    }
}
