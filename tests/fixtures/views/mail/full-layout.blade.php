<mjml>
    <mj-include path="mjml/head.mjml" />
    <mj-body>
        <mj-include path="mjml/header.mjml" />
        <mj-section>
            <mj-column>
                <mj-text font-size="18px">
                    {{ trans('mail.footer.team') }} — {{ $reference }}
                </mj-text>
                <mj-text>
                    {!! $introHtml !!}
                </mj-text>
                <mj-button href="{{ $actionUrl }}">
                    {{ $actionLabel }}
                </mj-button>
            </mj-column>
        </mj-section>
        <mj-include path="mjml/footer-rich.mjml" />
    </mj-body>
</mjml>
