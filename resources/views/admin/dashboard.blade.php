@extends('layouts.admin')

@section('content')
    @if (! $currentAccount)
        <p class="notice">حسابی برای مشاهده انتخاب نشده است.</p>
    @elseif (! $currentAccount->hasDatabase())
        <div class="card">
            <h2>این حساب فروشگاه ندارد</h2>
            <p>«{{ $currentAccount->name }}» یک حساب میانی است. برای مدیریت فروشگاه یکی از زیرحساب‌ها را انتخاب کنید.</p>
            <ul class="tree">
                @foreach ($children as $child)
                    <li>
                        {{ $child->name }}
                        <span class="muted">— {{ $child->hasDatabase() ? 'فروشگاه فعال' : 'بدون فروشگاه' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="stat-grid">
            <div class="stat"><span class="stat-value">{{ fa_number($counts['products'] ?? 0) }}</span><span class="stat-label">محصول</span></div>
            <div class="stat"><span class="stat-value">{{ fa_number($counts['posts'] ?? 0) }}</span><span class="stat-label">نوشته</span></div>
            <div class="stat"><span class="stat-value">{{ fa_number($counts['categories'] ?? 0) }}</span><span class="stat-label">دسته‌بندی</span></div>
            <div class="stat"><span class="stat-value">{{ fa_number($counts['vendors'] ?? 0) }}</span><span class="stat-label">تامین‌کننده</span></div>
            <div class="stat"><span class="stat-value">{{ fa_number($counts['orders'] ?? 0) }}</span><span class="stat-label">سفارش</span></div>
            <div class="stat"><span class="stat-value">{{ format_price($counts['revenue'] ?? 0) }}</span><span class="stat-label">فروش</span></div>
        </div>

        <div class="panel-grid">
            <section class="card">
                <h2>دامنه‌ها</h2>
                <ul class="plain">
                    @foreach ($domains as $domain)
                        <li>
                            <span dir="ltr">{{ $domain->host }}</span>
                            @if ($domain->is_primary)<span class="tag">اصلی</span>@endif
                        </li>
                    @endforeach
                    @if (empty($domains))
                        <li class="muted">دامنه‌ای ثبت نشده است.</li>
                    @endif
                </ul>
                <a class="button button-small" href="{{ route('admin.accounts.show', $currentAccount->id) }}">مدیریت حساب</a>
            </section>

            <section class="card">
                <h2>آخرین سفارش‌ها</h2>
                @if ($recentOrders->isEmpty())
                    <p class="muted">سفارشی ثبت نشده است.</p>
                @else
                    <table class="table">
                        <thead><tr><th>شماره</th><th>مبلغ</th><th>پرداخت</th></tr></thead>
                        <tbody>
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td><a href="{{ route('admin.orders.show', $order) }}" dir="ltr">{{ $order->number }}</a></td>
                                    <td>{{ format_price($order->total, $order->currency) }}</td>
                                    <td>{{ $order->payment_status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    @endif
@endsection
