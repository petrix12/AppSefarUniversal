<style>
    /* Neutralizar restos del intento anterior */
    .select2-wrapper {
        position: static !important;
    }
    .select2-prefix-icon {
        display: none !important;
    }

    /* ── Dimensiones ── */
    .select2-container--bootstrap-4 .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
        border: 1px solid #ced4da;
        border-radius: .25rem;
        transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        display: flex !important;
        align-items: center !important;
    }

    .select2-container--bootstrap-4 .select2-selection--single .select2-selection__rendered {
        line-height: normal !important;   /* ← este era el culpable */
        color: #495057;
        padding-left: .75rem;
        padding-right: 2rem;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .select2-container--bootstrap-4 .select2-selection--single .select2-selection__arrow {
        height: 100% !important;
        top: 0 !important;
        right: 6px;
    }

    /* ── Placeholder ── */
    .select2-container--bootstrap-4 .select2-selection--single .select2-selection__placeholder {
        color: #adb5bd;
    }

    /* ── Foco ── */
    .select2-container--bootstrap-4.select2-container--focus .select2-selection--single,
    .select2-container--bootstrap-4.select2-container--open  .select2-selection--single {
        border-color: #80bdff;
        box-shadow: 0 0 0 .2rem rgba(0, 123, 255, .25);
        outline: 0;
    }

    /* ── Dropdown ── */
    .select2-container--bootstrap-4 .select2-dropdown {
        border-color: #80bdff;
        border-radius: .25rem;
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .1);
    }

    .select2-container--bootstrap-4 .select2-results__option--highlighted[aria-selected] {
        background-color: #007bff;
    }

    .select2-container--bootstrap-4 .select2-search--dropdown .select2-search__field {
        border-radius: .2rem;
        border: 1px solid #ced4da;
        padding: .3rem .5rem;
    }

    .select2-container--bootstrap-4 .select2-search--dropdown .select2-search__field:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 .15rem rgba(0, 123, 255, .2);
        outline: 0;
    }
</style>
