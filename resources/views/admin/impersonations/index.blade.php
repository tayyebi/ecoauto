@extends('layouts.admin')

@section('content')
    <p class="hint">
        هر بار جانشینی با نام جانشین‌کننده ثبت می‌شود. هنگام جانشینی، اختیارات خودِ جانشین‌کننده
        اعمال نمی‌شود و شما دقیقا همان چیزی را می‌بینید که کاربر هدف می‌بیند.
    </p>

    <table class="table">
        <thead>
            <tr><th>شروع</th><th>پایان</th><th>جانشین‌کننده</th><th>کاربر هدف</th><th>دلیل</th><th>وضعیت</th></tr>
        </thead>
        <tbody>
            @foreach ($impersonations as $record)
                <tr>
                    <td data-label="شروع">{{ fa_date($record->started_at, 'Y/m/d H:i') }}</td>
                    <td>{{ $record->ended_at ? fa_date($record->ended_at, 'Y/m/d H:i') : '—' }}</td>
                    <td data-label="جانشین‌کننده">{{ $record->actor?->name }}</td>
                    <td data-label="کاربر هدف">{{ $record->target?->name }}</td>
                    <td>{{ $record->reason ?? '—' }}</td>
                    <td>
                        @if ($record->isActive())
                            <span class="tag tag-warn">فعال</span>
                        @else
                            {{ $record->end_reason }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $impersonations->links() }}
@endsection
