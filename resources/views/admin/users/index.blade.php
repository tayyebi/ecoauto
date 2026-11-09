@extends('layouts.admin')

@section('content')
    <table class="table">
        <thead><tr><th>نام</th><th>ایمیل</th><th>نقش‌ها</th><th>عملیات</th></tr></thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td data-label="نام">{{ $user->name }}@if ($user->is_platform_admin) <span class="tag">مدیر پلتفرم</span>@endif</td>
                    <td data-label="ایمیل" dir="ltr">{{ $user->email }}</td>
                    <td data-label="نقش‌ها">
                        @foreach ($user->memberships as $membership)
                            <span class="tag">{{ $membership->role }}</span>
                        @endforeach
                    </td>
                    <td>
                        @can('impersonate', $user)
                            <form method="post" action="{{ route('admin.impersonate.store', $user) }}" class="inline">
                                @csrf
                                <input type="hidden" name="reason" value="پشتیبانی">
                                <button class="button button-small button-ghost" type="submit">جانشینی</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $users->links() }}
@endsection
