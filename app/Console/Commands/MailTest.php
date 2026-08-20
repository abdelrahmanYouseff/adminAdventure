<?php

namespace App\Console\Commands;

use App\Mail\TestSystemMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : عنوان البريد المستلم}';

    protected $description = 'إرسال رسالة بريد تجريبية عبر المزود الافتراضي (Resend)';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('عنوان البريد غير صالح.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');
        $hasKey = filled(config('services.resend.key'));

        $this->line('Mailer: '.$mailer);
        $this->line('From: '.$fromAddress);
        $this->line('Resend key: '.($hasKey ? 'configured' : 'missing'));
        $this->line('To: '.$email);
        $this->newLine();

        if ($mailer === 'resend' && ! $hasKey) {
            $this->error('RESEND_KEY غير مضبوط في .env. أضف المفتاح ثم أعد المحاولة.');

            return self::FAILURE;
        }

        try {
            Mail::to($email)->send(new TestSystemMail(now()->toDateTimeString()));
        } catch (Throwable $e) {
            $this->error('فشل الإرسال: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('تم إرسال رسالة الاختبار بنجاح.');

        return self::SUCCESS;
    }
}
