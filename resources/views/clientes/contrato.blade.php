@extends('adminlte::page')

@section('title', 'Contrato')

@section('content_header')
    <h1>Contrato</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="text/javascript" src="https://form.jotform.com/jsform/231384136753659"></script>

    <iframe
        id="JotFormIFrame-231384136753659"
        title="ISP - Firma de Contrato"
        onload="window.parent.scrollTo(0,0)"
        allowtransparency="true"
        allowfullscreen="true"
        allow="geolocation; microphone; camera"
        src="https://form.jotform.com/231384136753659"
        frameborder="0"
        style="min-width:100%;max-width:100%;height:539px;border:none;"
        scrolling="no"
    >
    </iframe>

    <script type="text/javascript">
    var ifr = document.getElementById("JotFormIFrame-231384136753659");

    if (ifr) {
        var src = ifr.src;
        var iframeParams = [];
        if (window.location.href && window.location.href.indexOf("?") > -1) {
            iframeParams = iframeParams.concat(window.location.href.substr(window.location.href.indexOf("?") + 1).split('&'));
        }
        if (src && src.indexOf("?") > -1) {
            iframeParams = iframeParams.concat(src.substr(src.indexOf("?") + 1).split("&"));
            src = src.substr(0, src.indexOf("?"))
        }
        iframeParams.push("isIframeEmbed=1");
        ifr.src = src + "?" + iframeParams.join('&');
    }

    window.handleIFrameMessage = function(e) {
        if (typeof e.data === 'object') { return; }
        var args = e.data.split(":");
        if (args.length > 2) { 
            iframe = document.getElementById("JotFormIFrame-" + args[(args.length - 1)]); 
        } else { 
            iframe = document.getElementById("JotFormIFrame"); 
        }
        if (!iframe) { 
            return; 
        }
        switch (args[0]) {
            case "scrollIntoView":
                iframe.scrollIntoView();
                break;
            case "setHeight":
                iframe.style.height = args[1] + "px";
                if (!isNaN(args[1]) && parseInt(iframe.style.minHeight) > parseInt(args[1])) {
                    iframe.style.minHeight = args[1] + "px";
                }
                break;
            case "collapseErrorPage":
                if (iframe.clientHeight > window.innerHeight) {
                    iframe.style.height = window.innerHeight + "px";
                }
                break;
            case "reloadPage":
                window.location.reload();
                break;
            case "loadScript":
                if( !window.isPermitted(e.origin, ['jotform.com', 'jotform.pro']) ) { 
                    break; 
                }
                var src = args[1];
                if (args.length > 3) {
                    src = args[1] + ':' + args[2];
                }
                var script = document.createElement('script');
                script.src = src;
                script.type = 'text/javascript';
                document.body.appendChild(script);
                break;
            case "exitFullscreen":
                if      (window.document.exitFullscreen)        window.document.exitFullscreen();
                else if (window.document.mozCancelFullScreen)   window.document.mozCancelFullScreen();
                else if (window.document.mozCancelFullscreen)   window.document.mozCancelFullScreen();
                else if (window.document.webkitExitFullscreen)  window.document.webkitExitFullscreen();
                else if (window.document.msExitFullscreen)      window.document.msExitFullscreen();
                break;
        }
        var isJotForm = (e.origin.indexOf("jotform") > -1) ? true : false;
        if(isJotForm && "contentWindow" in iframe && "postMessage" in iframe.contentWindow) {
            var urls = {"docurl":encodeURIComponent(document.URL),"referrer":encodeURIComponent(document.referrer)};
            iframe.contentWindow.postMessage(JSON.stringify({"type":"urls","value":urls}), "*");
        }
    };

    window.isPermitted = function(originUrl, whitelisted_domains) {
        var url = document.createElement('a');
        url.href = originUrl;
        var hostname = url.hostname;
        var result = false;
        if( typeof hostname !== 'undefined' ) {
            whitelisted_domains.forEach(function(element) {
                if( hostname.slice((-1 * element.length - 1)) === '.'.concat(element) ||  hostname === element ) {
                    result = true;
                }
            });
            return result;
        }
    };

    if (window.addEventListener) {
        window.addEventListener("message", handleIFrameMessage, false);
    } else if (window.attachEvent) {
        window.attachEvent("onmessage", handleIFrameMessage);
    }
    
    </script>


@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
