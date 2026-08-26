@props(['errors'])

@if($errors->any())
    @php($encodedErrors = base64_encode(json_encode($errors->getMessages(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)))
    <script type="application/octet-stream" data-form-validation-errors data-validation-encoding="base64">{{ $encodedErrors }}</script>
@endif
