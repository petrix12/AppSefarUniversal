    <div class="alert d-flex justify-content-between align-items-center" id="cos-preview-control">
        <span>{{ $cosClientPreview ? 'Vista del representado · solo lectura' : 'Vista interna del COS' }}</span>
        <a id="cos-preview-toggle"
            class="cos-preview-toggle {{ $cosClientPreview ? 'cos-preview-toggle--return' : 'cos-preview-toggle--enter' }}"
            href="{{ $cosPreviewTargetUrl }}"
            role="switch"
            aria-checked="{{ $cosClientPreview ? 'true' : 'false' }}"
            data-loading-label="{{ $cosPreviewLoadingLabel }}">
            <i class="fas fa-{{ $cosClientPreview ? 'arrow-left' : 'eye' }}" aria-hidden="true"></i>
            <span>{{ $cosPreviewButtonLabel }}</span>
        </a>
    </div>

