@extends('layouts.admin')

@section('content')
    <div class="panel-grid">
        <section class="card">
            <h2>مشخصات</h2>
            <dl class="spec-list">
                <dt>نام</dt><dd>{{ $account->name }}</dd>
                <dt>شناسه</dt><dd dir="ltr">{{ $account->slug }}</dd>
                <dt>وضعیت</dt><dd>{{ $account->status }}</dd>
                <dt>پلن</dt><dd>{{ $account->plan }}</dd>
                <dt>مسیر</dt><dd dir="ltr">{{ $account->path }}</dd>
                <dt>پایگاه‌داده</dt><dd dir="ltr">{{ $account->database ?? '—' }}</dd>
            </dl>

            @if ($canManage)
                <div class="actions">
                    <a class="button button-small" href="{{ route('admin.accounts.edit', $account->id) }}">ویرایش</a>
                    @if ($account->status === 'active')
                        <form method="post" action="{{ route('admin.accounts.suspend', $account->id) }}" class="inline">
                            @csrf
                            <button class="button button-small button-ghost" type="submit">تعلیق</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.accounts.resume', $account->id) }}" class="inline">
                            @csrf
                            <button class="button button-small" type="submit">فعال‌سازی</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

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

            @if ($canManage)
                <form method="post" action="{{ route('admin.accounts.domains.store', $account->id) }}" class="stack">
                    @csrf
                    <label>دامنه جدید
                        <input name="host" required dir="ltr" placeholder="shop.example.ir">
                    </label>
                    <label class="inline">
                        <input type="checkbox" name="is_primary" value="1"> دامنه اصلی
                    </label>
                    <button class="button button-small" type="submit">افزودن دامنه</button>
                </form>
            @endif
        </section>

        <section class="card">
            <h2>تامین منابع</h2>
            @if ($account->hasDatabase())
                <p>پایگاه‌داده این حساب آماده است.</p>
            @elseif ($children !== [])
                <p class="muted">این حساب زیرحساب دارد و نمی‌تواند فروشگاه داشته باشد.</p>
            @elseif ($canManage)
                <form method="post" action="{{ route('admin.accounts.provision', $account->id) }}">
                    @csrf
                    <button class="button" type="submit">ساخت پایگاه‌داده فروشگاه</button>
                </form>
            @endif
        </section>

        <section class="card">
            <h2>زیرحساب‌ها</h2>
            @if ($children === [])
                <p class="muted">زیرحسابی ندارد.</p>
            @else
                <ul class="plain">
                    @foreach ($children as $child)
                        <li><a href="{{ route('admin.accounts.show', $child->id) }}">{{ $child->name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card">
            <h2>اعضا</h2>
            <table class="table">
                <thead><tr><th>کاربر</th><th>نقش</th><th></th></tr></thead>
                <tbody>
                    @foreach ($memberships as $membership)
                        <tr>
                            <td>{{ $membership->user?->name }}</td>
                            <td>{{ $membership->roleLabel() }}</td>
                            <td>
                                @can('impersonate', $membership->user)
                                    <form method="post" action="{{ route('admin.impersonate.store', $membership->user) }}" class="inline">
                                        @csrf
                                        <button class="button button-small button-ghost" type="submit">جانشینی</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
@endsection
