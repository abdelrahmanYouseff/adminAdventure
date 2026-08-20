@extends('emails.layout')

@section('title', 'اختبار البريد')
@section('preheader')
    اختبار إرسال البريد — {{ config('mail.from.name') }}
@endsection
@section('subtitle', 'اختبار إرسال البريد')
@section('accent', '#ea580c')

@section('content')
    <p style="margin:0 0 12px;font-size:15px;line-height:1.7;">
        تم استلام هذه الرسالة بنجاح. إعدادات البريد في النظام تعمل بشكل صحيح.
    </p>
    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.6;" dir="ltr">
        Sent at: {{ $sentAt }}
    </p>
@endsection
