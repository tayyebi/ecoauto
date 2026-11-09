@extends('layouts.admin')

@section('content')
    <table class="table">
        <thead><tr><th>کاربر</th><th>ایمیل</th><th>نقش</th><th>عملیات</th></tr></thead>
        <tbody>
            @foreach ($memberships as $membership)
                <tr>
                    <td data-label="کاربر">{{ $membership->user?->name }}</td>
                    <td data-label="ایمیل" dir="ltr">{{ $membership->user?->email }}</td>
                    <td data-label="نقش">
                        <form method="post" action="{{ route('admin.members.update', $membership) }}" class="inline">
                            @csrf
                            @method('put')
                            <select name="role">
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}" @selected($membership->role === $role)>{{ $role }}</option>
                                @endforeach
                            </select>
                            <button class="button button-small" type="submit">ذخیره</button>
                        </form>
                    </td>
                    <td data-label="عملیات">
                        <form method="post" action="{{ route('admin.members.destroy', $membership) }}" class="inline">
                            @csrf
                            @method('delete')
                            <button class="button button-small button-ghost" type="submit">حذف</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="card">
        <h2>افزودن عضو</h2>
        <p class="hint">
            دسترسی به زیرحساب‌ها فقط از نقش «مالک» یا «مدیر» این حساب به ارث می‌رسد؛
            نقش «نویسنده» و «بازدیدکننده» تنها به همین حساب محدود است.
        </p>
        <form method="post" action="{{ route('admin.members.store') }}" class="stack">
            @csrf
            <label>ایمیل کاربر
                <input type="email" name="email" required dir="ltr">
            </label>
            <label>نقش
                <select name="role">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-primary" type="submit">افزودن</button>
        </form>
    </section>
@endsection
