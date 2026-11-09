@extends('layouts.admin')

@section('content')
    <form method="get" action="{{ route('admin.audit.index') }}" class="actions">
        <label>رویداد
            <input name="event" value="{{ $event }}" dir="ltr" placeholder="product.updated">
        </label>
        <button class="button button-small" type="submit">فیلتر</button>
    </form>

    <table class="table">
        <thead>
            <tr><th>زمان</th><th>رویداد</th><th>اقدام‌کننده</th><th>به‌جای</th><th>موضوع</th><th>حساب</th></tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td data-label="زمان">{{ fa_date($log->created_at, 'Y/m/d H:i') }}</td>
                    <td data-label="رویداد" dir="ltr">{{ $log->event }}</td>
                    <td data-label="اقدام‌کننده">{{ $log->actor?->name ?? '—' }}</td>
                    <td>
                        @if ($log->impersonation_id)
                            <span class="tag">جانشینی</span>
                        @else
                            —
                        @endif
                    </td>
                    <td dir="ltr">{{ class_basename($log->subject_type) }} {{ $log->subject_id }}</td>
                    <td dir="ltr">{{ $log->account_id }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $logs->links() }}
@endsection
