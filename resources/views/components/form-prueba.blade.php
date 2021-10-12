<div {{$attributes->merge(["class" => "grid grid-cols-3 gap-6"])}}>

    <div class="col-span-2">
        <div>
            <div>
                {{$slot}}
            </div>
            @isset($actions)
                <div>
                    {{$actions}}
                </div>
            @endisset    
        </div>
    </div>
</div>