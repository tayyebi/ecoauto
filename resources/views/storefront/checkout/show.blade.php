@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>تسویه حساب</h1>
    </header>

    <div class="checkout-grid">
        <form method="post" action="{{ route('checkout.store', absolute: false) }}" class="stack">
            @csrf
            <label>نام و نام خانوادگی
                <input name="name" value="{{ old('name') }}" required>
            </label>
            <label>تلفن همراه
                <input name="phone" value="{{ old('phone') }}" required inputmode="tel" dir="ltr">
            </label>
            <label>ایمیل (اختیاری)
                <input type="email" name="email" value="{{ old('email') }}" dir="ltr">
            </label>
            <label>شهر
                <input name="city" value="{{ old('city') }}">
            </label>
            <label>نشانی
                <textarea name="address" rows="3" required>{{ old('address') }}</textarea>
            </label>
            <label>کد پستی
                <input name="postal_code" value="{{ old('postal_code') }}" dir="ltr">
            </label>
            <label>توضیحات
                <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
            </label>

            <p class="hint">
                این فروشگاه از درگاه پرداخت آزمايشی استفاده می‌کند؛ مبلغی از حساب شما برداشت نمی‌شود.
            </p>

            <button class="button button-primary" type="submit">ثبت سفارش و پرداخت</button>
        </form>

        <aside class="card card-static">
            <h2 class="card-title">خلاصه سفارش</h2>
            <ul class="summary">
                @foreach ($lines as $line)
                    <li>
                        <span>{{ $line['product']->title }} × {{ fa_number($line['quantity']) }}</span>
                        <span>{{ format_price($line['total'], $line['product']->currency) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="price price-large">{{ format_price($subtotal, config('store.currency')) }}</p>
        </aside>
    </div>
@endsection
