@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ route('admin.accounts.update', $account->id) }}" class="stack card">
        @csrf
        @method('put')

        <label>نام
            <input name="name" value="{{ old('name', $account->name) }}" required>
        </label>
        <label>پلن
            <input name="plan" value="{{ old('plan', $account->plan) }}" dir="ltr">
        </label>

        @if ($parent)
            <p class="muted">این حساب زیرمجموعه «{{ $parent->name }}» است.</p>
        @endif

        <button type="submit" class="button button-primary">ذخیره</button>
    </form>
@endsection
