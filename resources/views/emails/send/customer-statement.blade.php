@component('mail::layout')
    @slot('header')
        @component('mail::header', ['url' => ''])
            {{ $data['customer']->company->name }}
        @endcomponent
    @endslot

    @slot('subcopy')
        @component('mail::subcopy')
            {!! $data['body'] !!}
        @endcomponent
    @endslot

    @slot('footer')
        @component('mail::footer')
            Powered by <a class="footer-link" href="https://invoiceshelf.com" target="_blank">InvoiceShelf</a>
        @endcomponent
    @endslot
@endcomponent
