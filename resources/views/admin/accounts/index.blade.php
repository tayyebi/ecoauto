@extends('layouts.admin')

@section('content')
    <div class="actions">
        @if (auth('landlord')->user()->canManage($account))
            <a class="button button-primary" href="{{ route('admin.accounts.create') }}">حساب جدید</a>
        @endif
    </div>

    <table class="table">
        <thead>
            <tr><th>نام</th><th>شناسه</th><th>وضعیت</th><th>فروشگاه</th><th>عمق</th><th></th></tr>
        </thead>
        <tbody>
            <tr class="is-current">
                <td>{{ $account->name }} <span class="tag">حساب جاری</span></td>
                <td data-label="شناسه" dir="ltr">{{ $account->slug }}</td>
                <td data-label="وضعیت">{{ $account->status }}</td>
                <td data-label="فروشگاه">{{ $account->hasDatabase() ? 'دارد' : 'ندارد' }}</td>
                <td data-label="عمق">{{ fa_number($account->depth()) }}</td>
                <td><a href="{{ route('admin.accounts.show', $account->id) }}">مشاهده</a></td>
            </tr>
            @foreach ($descendants as $child)
                <tr>
                    <td style="padding-inline-start: {{ 1 + ($child->depth() - $account->depth()) * 1.5 }}rem">{{ $child->name }}</td>
                    <td data-label="شناسه" dir="ltr">{{ $child->slug }}</td>
                    <td data-label="وضعیت">{{ $child->status }}</td>
                    <td data-label="فروشگاه">{{ $child->hasDatabase() ? 'دارد' : 'ندارد' }}</td>
                    <td data-label="عمق">{{ fa_number($child->depth()) }}</td>
                    <td><a href="{{ route('admin.accounts.show', $child->id) }}">مشاهده</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
