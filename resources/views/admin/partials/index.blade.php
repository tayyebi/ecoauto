@extends('layouts.admin')

@section('content')
    <form method="get" action="{{ route('admin.'.$folder.'.index') }}" class="actions">
        <label>جستجو
            <input name="q" value="{{ $search }}" placeholder="عنوان">
        </label>
        <button class="button button-small" type="submit">جستجو</button>
        <a class="button button-primary button-small" href="{{ route('admin.'.$folder.'.create') }}">افزودن</a>
    </form>

    <table class="table">
        <thead>
            <tr><th>عنوان</th><th>شناسه</th><th>وضعیت</th><th>عملیات</th></tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td data-label="عنوان">{{ $item->title }}</td>
                    <td data-label="شناسه" dir="ltr">{{ $item->slug }}</td>
                    <td data-label="وضعیت">
                        @if ($item->is_published)
                            <span class="tag">منتشرشده</span>
                        @else
                            <span class="tag tag-warn">پیش‌نویس</span>
                        @endif
                    </td>
                    <td class="actions-cell">
                        <a class="button button-small" href="{{ route('admin.'.$folder.'.edit', $item) }}">ویرایش</a>
                        <form method="post" action="{{ route('admin.'.$folder.'.destroy', $item) }}" class="inline">
                            @csrf
                            @method('delete')
                            <button class="button button-small button-ghost" type="submit">حذف</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $items->links() }}
@endsection
