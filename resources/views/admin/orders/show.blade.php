@extends('layouts.admin')

@section('content')
    <div class="panel-grid">
        <section class="card">
            <h2>اقلام</h2>
            <table class="table">
                <thead><tr><th>کالا</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        <tr>
                            <td data-label="کالا">{{ $line->title }}</td>
                            <td data-label="تعداد">{{ fa_number($line->quantity) }}</td>
                            <td data-label="قیمت واحد">{{ format_price($line->unit_price, $order->currency) }}</td>
                            <td data-label="جمع">{{ format_price($line->line_total, $order->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="price price-large">{{ format_price($order->total, $order->currency) }}</p>
        </section>

        <section class="card">
            <h2>مشتری</h2>
            <dl class="spec-list">
                <dt>نام</dt><dd>{{ $order->customer?->name }}</dd>
                <dt>تلفن</dt><dd dir="ltr">{{ $order->customer?->phone }}</dd>
                <dt>ایمیل</dt><dd dir="ltr">{{ $order->customer?->email ?: '—' }}</dd>
                <dt>نشانی</dt><dd>{{ $order->shipping_address }}</dd>
            </dl>

            <h2>وضعیت</h2>
            <form method="post" action="{{ route('admin.orders.update', $order) }}" class="stack">
                @csrf
                @method('put')
                <label>وضعیت سفارش
                    <select name="status">
                        @foreach (['pending', 'paid', 'failed', 'cancelled', 'fulfilled'] as $option)
                            <option value="{{ $option }}" @selected($order->status === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>وضعیت پرداخت
                    <select name="payment_status">
                        @foreach (['unpaid', 'paid', 'failed', 'refunded'] as $option)
                            <option value="{{ $option }}" @selected($order->payment_status === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button" type="submit">ذخیره</button>
            </form>
        </section>
    </div>
@endsection
