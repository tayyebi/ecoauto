@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>سبد خرید</h1>
    </header>

    @if (empty($lines))
        <p class="empty">سبد خرید شما خالی است. <a href="/products/">مشاهده محصولات</a></p>
    @else
        {{-- DECISION (mobile first): the table is a real table on wide screens
             and a stack of labelled rows on phones. The labels live in
             `data-label` so the CSS can reveal them without any JavaScript. --}}
        <form method="post" action="{{ route('cart.update', absolute: false) }}">
            @csrf
            <table class="table">
                <thead>
                    <tr><th>محصول</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td data-label="محصول"><a href="{{ $line['product']->url() }}">{{ $line['product']->title }}</a></td>
                            <td data-label="قیمت واحد">{{ format_price($line['product']->amount(), $line['product']->currency) }}</td>
                            <td data-label="تعداد">
                                <input class="qty" type="number" min="0" max="99"
                                       name="quantities[{{ $line['product']->id }}]"
                                       value="{{ $line['quantity'] }}" aria-label="تعداد">
                            </td>
                            <td data-label="جمع">{{ format_price($line['total'], $line['product']->currency) }}</td>
                            <td>
                                <button class="button button-ghost button-small" type="submit"
                                        formaction="{{ route('cart.remove', absolute: false) }}" name="product"
                                        value="{{ $line['product']->id }}">حذف</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">جمع کل</th>
                        <th colspan="2">{{ format_price($subtotal, config('store.currency')) }}</th>
                    </tr>
                </tfoot>
            </table>

            <div class="actions">
                <button class="button" type="submit">به‌روزرسانی سبد</button>
                <a class="button button-primary" href="{{ route('checkout.show', absolute: false) }}">ادامه خرید</a>
            </div>
        </form>
    @endif
@endsection
