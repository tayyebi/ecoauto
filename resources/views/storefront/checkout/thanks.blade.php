@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>سفارش ثبت شد</h1>
        <p>شماره سفارش: <span dir="ltr">{{ $order->number }}</span></p>
    </header>

    <div class="card card-static">
        <dl class="spec-list">
            <dt>وضعیت سفارش</dt><dd>{{ $order->status }}</dd>
            <dt>وضعیت پرداخت</dt><dd>{{ $order->payment_status === 'paid' ? 'پرداخت شده' : 'پرداخت نشده' }}</dd>
            <dt>مبلغ کل</dt><dd>{{ format_price($order->total, $order->currency) }}</dd>
        </dl>

        <ul class="summary">
            @foreach ($order->lines as $line)
                <li>
                    <span>{{ $line->title }} × {{ fa_number($line->quantity) }}</span>
                    <span>{{ format_price($line->line_total, $order->currency) }}</span>
                </li>
            @endforeach
        </ul>

        <a class="button" href="/">بازگشت به فروشگاه</a>
    </div>
@endsection
