<div class="bg-white shadow rounded-xl p-6 news-card">

    {{-- HEADER --}}
    <div class="news-card__head">
        <h3 class="news-card__title">Noticias</h3>
    </div>

    {{-- LISTA --}}
    <div class="news-list">

        @if($news->count())

            @foreach($news as $n)

                @php
                    $img = !empty($n->header_image)
                        ? (\Illuminate\Support\Str::startsWith($n->header_image, ['http://','https://'])
                            ? $n->header_image
                            : asset($n->header_image))
                        : null;

                    $date = optional($n->created_at)->format('d/m/Y H:i');
                @endphp

                <div class="news-item border rounded-lg p-3">

                    {{-- Título arriba --}}
                    <div class="news-item__title">
                        {{ $n->title }}
                    </div>

                    {{-- Imagen full + botón overlay --}}
                    @if($img)
                        <div class="news-hero">
                            <img class="news-hero__img" src="{{ $img }}" alt="header image">

                            <button
                                type="button"
                                class="news-hero__btn"
                                onclick="openNewsModal({{ $n->id }})"
                            >
                                Ver detalles de esta noticia
                            </button>
                        </div>
                    @else
                        {{-- Si no hay imagen, mostramos el botón debajo del título --}}
                        <div class="news-hero news-hero--noimg">
                            <button
                                type="button"
                                class="news-hero__btn news-hero__btn--noimg"
                                onclick="openNewsModal({{ $n->id }})"
                            >
                                Ver detalles de esta noticia
                            </button>
                        </div>
                    @endif

                    {{-- Datos ocultos para el modal (JS los lee) --}}
                    <div id="news-title-{{ $n->id }}" style="display:none;">{{ $n->title }}</div>
                    <div id="news-date-{{ $n->id }}" style="display:none;">{{ $date }}</div>
                    <div id="news-img-{{ $n->id }}" style="display:none;">{{ $img }}</div>
                    <div id="news-desc-{{ $n->id }}" style="display:none;">{{ $n->description }}</div>

                </div>

            @endforeach

        @else

            <div class="border rounded-lg p-4 text-gray-400">
                No hay noticias en este momento
            </div>

        @endif

    </div>

    {{-- MODAL DETALLE (UNO SOLO) --}}
    <div id="newsDetailModal" class="news-modal is-hidden" aria-hidden="true">
        <div class="news-modal__backdrop" onclick="closeNewsModal()"></div>

        <div class="news-modal__dialog" role="dialog" aria-modal="true">
            <div class="news-modal__header">
                <div>
                    <div id="newsModalTitle" class="news-modal__title">—</div>
                    <div id="newsModalDate" class="news-modal__date">—</div>
                </div>

                <button type="button" class="news-modal__close" onclick="closeNewsModal()">
                    ✕
                </button>
            </div>

            <div class="news-modal__body">
                <img id="newsModalImg" class="news-modal__img" src="" alt="" style="display:none;">
                <div id="newsModalDesc" class="news-modal__desc"></div>
            </div>

            <div class="news-modal__footer">
                <button type="button" class="news-modal__btn" onclick="closeNewsModal()">Cerrar</button>
            </div>
        </div>
    </div>

    {{-- CSS PURO (dentro del root) --}}
    <style>
        .news-card__head{ margin-bottom: 12px; }
        .news-card__title{ margin:0; font-size:18px; font-weight:700; color:#0f172a; }

        .news-list{ display:flex; flex-direction:column; gap:12px; }

        .news-item__title{
            font-size:14px;
            font-weight:700;
            color:#0f172a;
            margin-bottom:10px;
        }

        .news-hero{
            position:relative;
            border-radius:10px;
            overflow:hidden;
            border:1px solid #e5e7eb;
            background:#fff;
        }

        .news-hero__img{
            width:100%;
            height:160px;
            object-fit:cover;
            display:block;
        }

        /* Botón en el medio vertical, pegado a la derecha */
        .news-hero__btn{
            position:absolute;
            right:12px;
            top:50%;
            transform:translateY(-50%);
            padding:10px 12px;
            border-radius:10px;
            border:1px solid rgba(255,255,255,.65);
            background:rgba(15, 23, 42, .75);
            color:#fff;
            font-size:12px;
            font-weight:600;
            cursor:pointer;
            backdrop-filter: blur(6px);
        }

        .news-hero__btn:hover{
            background:rgba(15, 23, 42, .88);
        }

        /* Caso sin imagen */
        .news-hero--noimg{
            border:1px dashed #cbd5e1;
            padding:14px;
            border-radius:10px;
            background:#f8fafc;
        }

        .news-hero__btn--noimg{
            position:static;
            transform:none;
            border:1px solid #cbd5e1;
            background:#0f172a;
            display:inline-block;
        }

        /* Modal */
        .news-modal{ position:fixed; inset:0; z-index:9999; }
        .news-modal.is-hidden{ display:none; }

        .news-modal__backdrop{
            position:absolute; inset:0;
            background:rgba(0,0,0,.65);
        }

        .news-modal__dialog{
            position:relative;
            width:min(900px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            margin:16px auto;
            background:#fff;
            border-radius:16px;
            box-shadow:0 30px 80px rgba(0,0,0,.35);
            overflow:hidden;
            display:flex;
            flex-direction:column;
        }

        .news-modal__header{
            padding:16px 18px;
            border-bottom:1px solid #e5e7eb;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:14px;
            background:#fff;
        }

        .news-modal__title{
            font-size:20px;
            font-weight:800;
            color:#0f172a;
            line-height:1.2;
        }

        .news-modal__date{
            margin-top:4px;
            font-size:12px;
            color:#64748b;
        }

        .news-modal__close{
            border:none;
            background:#f1f5f9;
            width:36px;
            height:36px;
            border-radius:10px;
            cursor:pointer;
            color:#334155;
            font-size:16px;
        }

        .news-modal__close:hover{ background:#e2e8f0; }

        .news-modal__body{
            padding:18px;
            overflow:auto;
        }

        .news-modal__img{
            width:100%;
            height:320px;
            object-fit:cover;
            border-radius:14px;
            border:1px solid #e5e7eb;
            margin-bottom:14px;
            display:block;
        }

        .news-modal__desc{
            white-space:pre-wrap;
            color:#0f172a;
            line-height:1.5;
            font-size:14px;
        }

        .news-modal__footer{
            padding:12px 18px;
            border-top:1px solid #e5e7eb;
            background:#f8fafc;
            display:flex;
            justify-content:flex-end;
        }

        .news-modal__btn{
            padding:10px 14px;
            border-radius:10px;
            border:1px solid #cbd5e1;
            background:#fff;
            cursor:pointer;
            font-weight:600;
            color:#0f172a;
        }

        .news-modal__btn:hover{ background:#f1f5f9; }
    </style>

    {{-- JS (dentro del root) --}}
    <script>
        function openNewsModal(id){
            const modal = document.getElementById('newsDetailModal');
            const titleEl = document.getElementById('newsModalTitle');
            const dateEl  = document.getElementById('newsModalDate');
            const imgEl   = document.getElementById('newsModalImg');
            const descEl  = document.getElementById('newsModalDesc');

            const t = document.getElementById('news-title-' + id)?.textContent ?? '';
            const d = document.getElementById('news-date-' + id)?.textContent ?? '';
            const i = document.getElementById('news-img-' + id)?.textContent ?? '';
            const b = document.getElementById('news-desc-' + id)?.textContent ?? '';

            titleEl.textContent = t;
            dateEl.textContent  = 'Publicado: ' + d;
            descEl.textContent  = b;

            if(i && i.trim() !== ''){
                imgEl.src = i.trim();
                imgEl.style.display = 'block';
                imgEl.alt = t;
            } else {
                imgEl.src = '';
                imgEl.style.display = 'none';
                imgEl.alt = '';
            }

            modal.classList.remove('is-hidden');
            document.body.classList.add('modal-open');
        }

        function closeNewsModal(){
            const modal = document.getElementById('newsDetailModal');
            modal.classList.add('is-hidden');
            document.body.classList.remove('modal-open');
        }

        // ESC para cerrar
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape'){
                const modal = document.getElementById('newsDetailModal');
                if(modal && !modal.classList.contains('is-hidden')){
                    closeNewsModal();
                }
            }
        });
    </script>

</div>
