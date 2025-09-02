<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
</head>
<body>
    <script>
        // Send the new URL to the parent window
        window.parent.postMessage({
            url: "{{ $redirect_url }}"
        }, "https://sefaruniversal.com");
    </script>
</body>
</html>
