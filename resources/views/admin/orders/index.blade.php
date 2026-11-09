@extends('layouts.admin')

@section('content')
    <form method="get" action="{{ route('admin.orders.index') }}" class="actions">
        <label>وضعیت
            <select name="status">
                <option value="">همه</option>
                @foreach (['pending', 'paid', 'failed', 'cancelled', 'fulfilled'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <button class="button button-small" type="submit">فیلتر</button>
    </form>

    <table class="table">
        <thead><tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>پرداخت</th><th>تاریخ</th></tr></thead>
        <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td data-label="شماره"><a href="{{ route('admin.orders.show', $order) }}" dir="ltr">{{ $order->number }}</a></td>
                    <td data-label="مشتری">{{ $order->customer?->name }}</td>
                    <td data-label="مبلغ">{{ format_price($order->total, $order->currency) }}</td>
                    <td data-label="وضعیت">{{ $order->status }}</td>
                    <td data-label="پرداخت">{{ $order->payment_status }}</td>
                    <td data-label="تاریخ">{{ fa_date($order->created_at) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $orders->links() }}
@endsection
